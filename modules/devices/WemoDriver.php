<?php
require_once APP_ROOT . '/models/Wemo.php';
require_once APP_ROOT . '/models/Device.php';
require_once APP_ROOT . '/models/NmapScan.php';
require_once APP_ROOT . '/modules/network/NetworkModule.php';

/**
 * WemoDriver — HTTP-unaware driver for Belkin Wemo smart plug devices.
 *
 * Communicates with Wemo devices via SOAP over HTTP using cURL.
 * All database access goes through the Wemo and Device model instances.
 * The exec() and cURL calls are injectable via optional callable parameters
 * so that unit tests can supply fake implementations without network access.
 */
class WemoDriver
{
    /** @var int cURL request timeout in seconds. */
    const REQUEST_TIMEOUT = 10;

    /** @var int Default Wemo UPnP control port. */
    const WEMO_CONTROL_PORT = 49153;

    /** @var int Return code when a SOAP fault is detected in the response. */
    const DEVICE_FAULT = 98;

    /** @var int Return code when the parsed tag value equals "Error". */
    const DEVICE_ERROR = 99;

    // ── Scan methods ───────────────────────────────────────────────────────────

    /**
     * Check the next unchecked IP from the nmap scan queue.
     *
     * Each call dequeues one IP, determines whether it is a Wemo device, updates
     * the nmap record with the result, and returns status information for the
     * frontend scan loop.
     *
     * Steps:
     *   1. If no unchecked IPs remain, returns `['done' => true, 'remaining' => 0]`.
     *   2. If the IP is already a known Wemo (by IP), marks it as 'wemo' and
     *      returns `result => 'known_wemo'`.
     *   3. Calls `NetworkModule::getOpenPorts()` (or the injected callable). If no
     *      ports are open, marks the record 'other' and returns `result => 'no_ports'`.
     *   4. For each open port, fetches `http://{ip}:{port}/setup.xml` via the
     *      `$fetchFn` callable. Parses `<friendlyName>` and `<macAddress>`.
     *   5. On a valid setup.xml: creates or updates the Wemo record, creates a
     *      linked Device on first discovery, marks the nmap record 'wemo', and
     *      returns `result => 'found_wemo'`. Stops after the first matching port.
     *   6. If no port yields a valid setup.xml, marks as 'other' and returns
     *      `result => 'not_wemo'`.
     *
     * @param NmapScan           $nmapScan  NmapScan model instance.
     * @param Wemo               $wemoModel Wemo model instance.
     * @param callable|null      $fetchFn   Optional callable replacing the cURL
     *                                      setup.xml fetch for testing.
     *                                      Signature: (string $url): string|false
     * @param callable|null      $portsFn   Optional callable replacing
     *                                      NetworkModule::getOpenPorts() for testing.
     *                                      Signature: (string $ip): array<int, int>
     * @param Device|null        $deviceModel Optional Device instance for testing.
     * @return array<string, mixed> Status array with at minimum `done` and `remaining`.
     */
    public static function checkNextIp(
        NmapScan $nmapScan,
        Wemo $wemoModel,
        ?callable $fetchFn = null,
        ?callable $portsFn = null,
        ?Device $deviceModel = null
    ): array {
        $deviceModel ??= new Device();

        $record = $nmapScan->getNextUnchecked();

        if ($record === null) {
            return ['done' => true, 'remaining' => 0];
        }

        $ip = (string) $record['ip_address'];
        $id = (int) $record['id'];

        // Step 2 — already a known Wemo at this IP.
        if ($wemoModel->findByIp($ip) !== null) {
            $nmapScan->markChecked($id, 'wemo');
            return [
                'done'      => false,
                'remaining' => $nmapScan->getRemainingCount(),
                'result'    => 'known_wemo',
                'ip'        => $ip,
            ];
        }

        // Step 3 — discover open ports.
        $ports = $portsFn !== null
            ? $portsFn($ip)
            : NetworkModule::getOpenPorts($ip);

        if (empty($ports)) {
            $nmapScan->markChecked($id, 'other');
            return [
                'done'      => false,
                'remaining' => $nmapScan->getRemainingCount(),
                'result'    => 'no_ports',
                'ip'        => $ip,
            ];
        }

        // Steps 4–6 — probe each port for a Wemo setup.xml.
        foreach ($ports as $port) {
            $url = 'http://' . $ip . ':' . $port . '/setup.xml';

            $xml = $fetchFn !== null
                ? $fetchFn($url)
                : static::fetchSetupXml($url);

            if ($xml === false || $xml === '') {
                continue;
            }

            $dom = @simplexml_load_string($xml);
            if ($dom === false) {
                continue;
            }

            $nameNodes = $dom->xpath('//*[local-name()="friendlyName"]');
            $macNodes  = $dom->xpath('//*[local-name()="macAddress"]');

            if (empty($nameNodes) || empty($macNodes)) {
                continue;
            }

            $name = trim((string) $nameNodes[0]);
            $mac  = strtolower(trim((string) $macNodes[0]));

            if ($name === '' || $mac === '') {
                continue;
            }

            // Valid Wemo found — create or update the DB record.
            $existing = $wemoModel->findByMac($mac);

            if ($existing !== null) {
                // Update only fields that have changed.
                $changes = [];
                if ($existing['ip_address'] !== $ip) {
                    $changes['ip_address'] = $ip;
                }
                if ((int) $existing['port'] !== (int) $port) {
                    $changes['port'] = $port;
                }
                if ($existing['name'] !== $name) {
                    $changes['name'] = $name;
                }
                if (!empty($changes)) {
                    $wemoModel->updateWemo((int) $existing['id'], $changes);
                }
            } else {
                // First discovery — create Device and linked Wemo.
                $deviceId = $deviceModel->insert([
                    'name'       => $name,
                    'type'       => 'light',
                    'state'      => 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $wemoModel->createWemo([
                    'name'        => $name,
                    'mac_address' => $mac,
                    'ip_address'  => $ip,
                    'port'        => $port,
                    'device_id'   => $deviceId,
                ]);
            }

            $nmapScan->markChecked($id, 'wemo');
            return [
                'done'      => false,
                'remaining' => $nmapScan->getRemainingCount(),
                'result'    => 'found_wemo',
                'ip'        => $ip,
                'name'      => $name,
            ];
        }

        // No port yielded a valid setup.xml.
        $nmapScan->markChecked($id, 'other');
        return [
            'done'      => false,
            'remaining' => $nmapScan->getRemainingCount(),
            'result'    => 'not_wemo',
            'ip'        => $ip,
        ];
    }

    // ── Public methods ─────────────────────────────────────────────────────────

    /**
     * Poll all known Wemo devices and sync their state to the database.
     *
     * Retrieves all wemos from the database, calls getBinaryState() for each,
     * and persists the result via Wemo::updateState(). If the wemo has a linked
     * device, Device::update() is also called to keep the wrapper in sync.
     *
     * Devices that fail to respond are skipped; last_checked is only updated
     * on a successful SOAP response.
     *
     * @param callable|null  $getStateFn  Optional callable replacing getBinaryState()
     *                                    for testing. Receives the wemo row array and
     *                                    must return int|null (1, 0, or null on failure).
     * @param Wemo|null      $wemoModel   Optional Wemo model instance for testing.
     * @param Device|null    $deviceModel Optional Device instance for testing.
     * @return void
     */
    public static function observe(
        ?callable $getStateFn = null,
        ?Wemo $wemoModel = null,
        ?Device $deviceModel = null
    ): void {
        $wemoModel   ??= new Wemo();
        $deviceModel ??= new Device();
        $getStateFn  ??= static fn(array $wemo): ?int => static::getBinaryState($wemo);

        $wemos = $wemoModel->allWemos();

        foreach ($wemos as $wemo) {
            $state = $getStateFn($wemo);

            if ($state === null) {
                continue;
            }

            $wemoModel->updateState((int) $wemo['id'], $state);

            if (!empty($wemo['device_id'])) {
                $deviceModel->update((int) $wemo['device_id'], [
                    'state'      => $state,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /**
     * Send a SetBinaryState SOAP command to turn a Wemo device on or off.
     *
     * Returns true only when the device echoes back the requested state value
     * in a successful SOAP response.
     *
     * @param array<string, mixed> $wemo        Wemo row from the database.
     * @param int                  $targetState Desired state: 1 = on, 0 = off.
     * @param callable|null        $curlFn      Optional callable replacing the cURL call
     *                                          for testing. Receives (string $url,
     *                                          array $headers, string $envelope) and must
     *                                          return string|false.
     * @return bool True on success with matching state, false on failure or mismatch.
     */
    public static function setBinaryState(array $wemo, int $targetState, ?callable $curlFn = null): bool
    {
        $body     = '<BinaryState>' . $targetState . '</BinaryState>';
        $response = static::sendRequest($wemo, 'SetBinaryState', $body, $curlFn);

        if ($response === false) {
            return false;
        }

        $result = static::parseResponse($response, 'BinaryState');

        if ($result === false || $result === static::DEVICE_FAULT || $result === static::DEVICE_ERROR) {
            return false;
        }

        return (int) $result === $targetState;
    }

    /**
     * Send a GetBinaryState SOAP command and return the current device state.
     *
     * @param array<string, mixed> $wemo   Wemo row from the database.
     * @param callable|null        $curlFn Optional callable replacing the cURL call
     *                                     for testing. Receives (string $url,
     *                                     array $headers, string $envelope) and must
     *                                     return string|false.
     * @return int|null 1 (on), 0 (off), or null on any failure.
     */
    public static function getBinaryState(array $wemo, ?callable $curlFn = null): ?int
    {
        $response = static::sendRequest($wemo, 'GetBinaryState', null, $curlFn);

        if ($response === false) {
            return null;
        }

        $result = static::parseResponse($response, 'BinaryState');

        if ($result === false || $result === static::DEVICE_FAULT || $result === static::DEVICE_ERROR) {
            return null;
        }

        return (int) $result;
    }

    // ── Private methods ────────────────────────────────────────────────────────

    /**
     * Fetch the setup.xml from a Wemo device via cURL.
     *
     * @param string $url The full URL to fetch (e.g. 'http://192.168.1.5:49153/setup.xml').
     * @return string|false The raw response body, or false on failure.
     */
    private static function fetchSetupXml(string $url): string|false
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_0,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    /**
     * Send a SOAP request to a Wemo device and return the raw response.
     *
     * Builds the SOAP envelope and dispatches it via cURL (or the injected
     * callable). Uses HTTP/1.0 and an empty User-Agent header as required by
     * the Wemo UPnP stack.
     *
     * @param array<string, mixed> $wemo   Wemo row from the database.
     * @param string               $action SOAP action name (e.g. 'GetBinaryState').
     * @param string|null          $body   Optional XML content for the action element body.
     * @param callable|null        $curlFn Optional callable replacing cURL execution
     *                                     for testing. Receives (string $url,
     *                                     array $headers, string $envelope) and must
     *                                     return string|false.
     * @return string|false The raw SOAP response string, or false on cURL failure.
     */
    private static function sendRequest(
        array $wemo,
        string $action,
        ?string $body = null,
        ?callable $curlFn = null
    ): string|false {
        $url        = 'http://' . $wemo['ip_address'] . ':' . $wemo['port']
                      . '/upnp/control/basicevent1';
        $soapAction = 'urn:Belkin:service:basicevent:1#' . $action;
        $envelope   = '<?xml version="1.0" encoding="utf-8"?>'
            . '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"'
            . ' s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">'
            . '<s:Body>'
            . '<u:' . $action . ' xmlns:u="urn:Belkin:service:basicevent:1">'
            . ($body ?? '')
            . '</u:' . $action . '>'
            . '</s:Body>'
            . '</s:Envelope>';

        $headers = [
            'Content-type: text/xml; charset="utf-8"',
            'SOAPACTION: "' . $soapAction . '"',
        ];

        if ($curlFn !== null) {
            return $curlFn($url, $headers, $envelope);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $envelope,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => static::REQUEST_TIMEOUT,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_0,
            CURLOPT_USERAGENT      => '',
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    /**
     * Parse a SOAP XML response and extract the value of a named element.
     *
     * Detection priority:
     *   1. If <faultstring> is present anywhere → returns DEVICE_FAULT (98).
     *   2. If the target tag value equals "Error" → returns DEVICE_ERROR (99).
     *   3. If the target tag is found → returns its string value.
     *   4. If the XML cannot be parsed or the tag is absent → returns false.
     *
     * @param string $xml The raw SOAP XML response string.
     * @param string $tag The XML element tag name to extract.
     * @return string|int|false The extracted value, DEVICE_FAULT, DEVICE_ERROR, or false.
     */
    private static function parseResponse(string $xml, string $tag): string|int|false
    {
        $dom = @simplexml_load_string($xml);

        if ($dom === false) {
            return false;
        }

        $faults = $dom->xpath('//faultstring');
        if (!empty($faults)) {
            return static::DEVICE_FAULT;
        }

        $matches = $dom->xpath('//' . $tag);
        if (empty($matches)) {
            return false;
        }

        $value = (string) $matches[0];

        if ($value === 'Error') {
            return static::DEVICE_ERROR;
        }

        return $value;
    }
}
