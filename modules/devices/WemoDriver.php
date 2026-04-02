<?php
require_once APP_ROOT . '/models/Wemo.php';
require_once APP_ROOT . '/models/LightsModel.php';

/**
 * WemoDriver — HTTP-unaware driver for Belkin Wemo smart plug devices.
 *
 * Communicates with Wemo devices via SOAP over HTTP using cURL.
 * All database access goes through the Wemo and LightsModel model instances.
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

    // ── Public methods ─────────────────────────────────────────────────────────

    /**
     * Poll all known Wemo devices and sync their state to the database.
     *
     * Retrieves all wemos from the database, calls getBinaryState() for each,
     * and persists the result via Wemo::updateState(). If the wemo has a linked
     * light, Light::updateState() is also called to keep the wrapper in sync.
     *
     * Devices that fail to respond are skipped; last_checked is only updated
     * on a successful SOAP response.
     *
     * @param callable|null    $getStateFn  Optional callable replacing getBinaryState()
     *                                      for testing. Receives the wemo row array and
     *                                      must return int|null (1, 0, or null on failure).
     * @param Wemo|null        $wemoModel   Optional Wemo model instance for testing.
     * @param LightsModel|null $lightsModel Optional LightsModel instance for testing.
     * @return void
     */
    public static function observe(
        ?callable $getStateFn = null,
        ?Wemo $wemoModel = null,
        ?LightsModel $lightsModel = null
    ): void {
        $wemoModel   ??= new Wemo();
        $lightsModel ??= new LightsModel();
        $getStateFn  ??= static fn(array $wemo): ?int => static::getBinaryState($wemo);

        $wemos = $wemoModel->allWemos();

        foreach ($wemos as $wemo) {
            $state = $getStateFn($wemo);

            if ($state === null) {
                continue;
            }

            $wemoModel->updateState((int) $wemo['id'], $state);

            if (!empty($wemo['light_id'])) {
                $lightsModel->updateState((int) $wemo['light_id'], $state);
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
