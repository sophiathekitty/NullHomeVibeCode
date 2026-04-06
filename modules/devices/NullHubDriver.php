<?php
require_once APP_ROOT . '/models/NullHub.php';
require_once APP_ROOT . '/models/NmapScan.php';

/**
 * NullHubDriver — HTTP-unaware driver for discovering legacy NullHub devices.
 *
 * Communicates with candidate hosts by requesting /api/info over HTTP using
 * cURL. A valid response must contain an "info" object with at least a
 * "mac_address" field. All database access goes through the NullHub and
 * NmapScan model instances. The cURL call is injectable via an optional
 * callable parameter so that unit tests can supply fake implementations
 * without network access.
 */
class NullHubDriver
{
    /** @var int cURL request timeout in seconds. */
    const REQUEST_TIMEOUT = 5;

    /** @var int Default HTTP port for NullHub /api/info requests. */
    const API_PORT = 80;

    // ── Scan methods ───────────────────────────────────────────────────────────

    /**
     * Check the next unchecked IP from the nmap scan queue for a NullHub device.
     *
     * Each call dequeues one IP, determines whether it is a NullHub device,
     * updates the nmap record with the result, and returns status information
     * for the frontend scan loop.
     *
     * Steps:
     *   1. If no unchecked IPs remain, returns `['done' => true, 'remaining' => 0]`.
     *   2. If the IP is already a known NullHub (by URL), marks it 'nullhub'
     *      and returns `result => 'known_nullhub'`.
     *   3. Fetches `http://{ip}/api/info` via the `$fetchFn` callable. If the
     *      response is empty or not valid JSON, marks as 'other' and returns
     *      `result => 'not_nullhub'`.
     *   4. If the JSON does not have an "info" key with a "mac_address" field,
     *      marks as 'other' and returns `result => 'not_nullhub'`.
     *   5. On a valid /api/info response: creates or updates the NullHub record,
     *      marks the nmap record 'nullhub', and returns `result => 'found_nullhub'`.
     *
     * @param NmapScan      $nmapScan    NmapScan model instance.
     * @param NullHub       $nullHubModel NullHub model instance.
     * @param callable|null $fetchFn     Optional callable replacing the cURL
     *                                   /api/info fetch for testing.
     *                                   Signature: (string $url): string|false
     * @return array<string, mixed> Status array with at minimum `done` and `remaining`.
     */
    public static function checkNextIp(
        NmapScan $nmapScan,
        NullHub $nullHubModel,
        ?callable $fetchFn = null
    ): array {
        $record = $nmapScan->getNextUnchecked();

        if ($record === null) {
            return ['done' => true, 'remaining' => 0];
        }

        $ip = (string) $record['ip_address'];
        $id = (int) $record['id'];

        // Step 2 — already a known NullHub at this URL/IP.
        if ($nullHubModel->findByUrl($ip) !== null) {
            $nmapScan->markChecked($id, 'nullhub');
            return [
                'done'      => false,
                'remaining' => $nmapScan->getRemainingCount(),
                'result'    => 'known_nullhub',
                'ip'        => $ip,
            ];
        }

        // Step 3 — fetch /api/info from the candidate host.
        $url = 'http://' . $ip . '/api/info';

        $body = $fetchFn !== null
            ? $fetchFn($url)
            : static::fetchApiInfo($url);

        if ($body === false || $body === '') {
            $nmapScan->markChecked($id, 'other');
            return [
                'done'      => false,
                'remaining' => $nmapScan->getRemainingCount(),
                'result'    => 'not_nullhub',
                'ip'        => $ip,
            ];
        }

        $decoded = @json_decode($body, true);

        // Step 4 — validate response shape.
        if (
            !is_array($decoded)
            || !isset($decoded['info'])
            || !is_array($decoded['info'])
            || empty($decoded['info']['mac_address'])
        ) {
            $nmapScan->markChecked($id, 'other');
            return [
                'done'      => false,
                'remaining' => $nmapScan->getRemainingCount(),
                'result'    => 'not_nullhub',
                'ip'        => $ip,
            ];
        }

        // Step 5 — parse info and persist.
        $info = $decoded['info'];
        $mac  = (string) $info['mac_address'];
        $name = isset($info['name']) ? (string) $info['name'] : $ip;

        $data = static::parseInfo($info, $ip);

        $existing = $nullHubModel->findByMac($mac);

        if ($existing !== null) {
            // Update only fields that have changed.
            $changes = [];
            foreach ($data as $key => $value) {
                if ((string) ($existing[$key] ?? '') !== (string) $value) {
                    $changes[$key] = $value;
                }
            }
            if (!empty($changes)) {
                $nullHubModel->updateNullHub((int) $existing['id'], $changes);
            }
        } else {
            $nullHubModel->createNullHub($data);
        }

        $nmapScan->markChecked($id, 'nullhub');
        return [
            'done'      => false,
            'remaining' => $nmapScan->getRemainingCount(),
            'result'    => 'found_nullhub',
            'ip'        => $ip,
            'name'      => $name,
        ];
    }

    // ── Private methods ────────────────────────────────────────────────────────

    /**
     * Fetch /api/info from a candidate host via cURL.
     *
     * @param string $url The full URL to fetch (e.g. 'http://192.168.1.5/api/info').
     * @return string|false The raw response body, or false on failure.
     */
    private static function fetchApiInfo(string $url): string|false
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => static::REQUEST_TIMEOUT,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_0,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    /**
     * Parse a /api/info response payload into a NullHub field array.
     *
     * Handles legacy quirks such as "main" being "1" (string) or false
     * (boolean), and optional fields that may not be present.
     *
     * @param array<string, mixed> $info The decoded "info" object from /api/info.
     * @param string               $ip   The IP address of the scanned host.
     * @return array<string, mixed> Normalized field array ready for insert/update.
     */
    private static function parseInfo(array $info, string $ip): array
    {
        $main    = isset($info['main']) ? (int) filter_var($info['main'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (int) $info['main'] : 0;
        $enabled = isset($info['enabled']) ? (int) filter_var($info['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (int) $info['enabled'] : 1;
        $room    = isset($info['room']) && $info['room'] !== '' ? (int) $info['room'] : null;

        $modified = null;
        if (!empty($info['modified'])) {
            $modified = (string) $info['modified'];
        }

        return [
            'mac_address' => (string) $info['mac_address'],
            'name'        => isset($info['name'])   ? (string) $info['name']   : $ip,
            'url'         => isset($info['url'])     ? (string) $info['url']    : $ip,
            'hub'         => isset($info['hub'])     ? (string) $info['hub']    : null,
            'type'        => isset($info['type'])    ? (string) $info['type']   : null,
            'server'      => isset($info['server'])  ? (string) $info['server'] : null,
            'main'        => $main,
            'last_ping'   => date('Y-m-d H:i:s'),
            'modified'    => $modified,
            'online'      => 1,
            'offline'     => 0,
            'enabled'     => $enabled,
            'room'        => $room,
            'dev'         => isset($info['dev'])   ? (string) $info['dev']   : null,
            'hash'        => isset($info['hash'])  ? (string) $info['hash']  : null,
            'git'         => isset($info['git'])   ? (string) $info['git']   : null,
            'setup'       => isset($info['setup']) ? (string) $info['setup'] : null,
        ];
    }
}
