<?php
/**
 * NullHubDriverTest — unit/integration tests for NullHubDriver::checkNextIp().
 *
 * All external calls (cURL /api/info fetch) are injectable via optional callable
 * parameters so that tests run without any network access.
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/models/NmapScan.php';
require_once APP_ROOT . '/models/NullHub.php';
require_once APP_ROOT . '/modules/devices/NullHubDriver.php';

class NullHubDriverTest extends BaseTestCase
{
    // ── Setup ─────────────────────────────────────────────────────────────────

    /** @var NmapScan */
    private NmapScan $nmapScan;

    /** @var NullHub */
    private NullHub $nullHubModel;

    /**
     * Truncate all relevant tables before every test for a clean slate.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->nmapScan     = new NmapScan();
        $this->nullHubModel = new NullHub();

        DB::connection()->exec('TRUNCATE TABLE `nmap_scans`');
        DB::connection()->exec('TRUNCATE TABLE `null_hubs`');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a valid /api/info response for a main hub.
     *
     * @param string $mac  MAC address.
     * @param string $ip   URL/IP of the device.
     * @param string $name Friendly name.
     * @return string JSON response body.
     */
    private function mainHubInfo(string $mac, string $ip, string $name): string
    {
        return json_encode([
            'info' => [
                'url'         => $ip,
                'type'        => 'old_hub',
                'main'        => '1',
                'path'        => '/',
                'server'      => 'pi4b',
                'mac_address' => $mac,
                'name'        => $name,
            ],
        ]);
    }

    /**
     * Build a valid /api/info response for a child device.
     *
     * @param string $mac       MAC address.
     * @param string $ip        URL/IP of the device.
     * @param string $name      Friendly name.
     * @param string $hubIp     Parent hub IP.
     * @return string JSON response body.
     */
    private function childDeviceInfo(string $mac, string $ip, string $name, string $hubIp): string
    {
        return json_encode([
            'info' => [
                'url'         => $ip,
                'is_hub'      => false,
                'hub'         => $hubIp,
                'hub_name'    => 'null pi',
                'room'        => '0',
                'type'        => 'hub',
                'enabled'     => '1',
                'main'        => false,
                'dev'         => 'dev',
                'hash'        => '3af17efdc976cee4105b97cbc9947908d53420ed',
                'modified'    => '2024-04-17 15:49:25',
                'path'        => '/',
                'server'      => 'pi3ap',
                'mac_address' => $mac,
                'name'        => $name,
                'git'         => 'https://github.com/sophiathekitty/NullHub',
                'setup'       => 'complete',
            ],
        ]);
    }

    /**
     * Insert a single IP into nmap_scans and return the row's id.
     *
     * @param string $ip IP address to insert.
     * @return int The new row id.
     */
    private function insertIp(string $ip): int
    {
        $this->nmapScan->insertIps([$ip]);
        return (int) DB::query(
            "SELECT id FROM `nmap_scans` WHERE ip_address = ?",
            [$ip]
        )->fetchColumn();
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * checkNextIp() returns ['done' => true, 'remaining' => 0] when the
     * nmap queue is empty.
     *
     * @return void
     */
    public function testCheckNextIpReturnsDoneWhenQueueEmpty(): void
    {
        $result = NullHubDriver::checkNextIp($this->nmapScan, $this->nullHubModel);

        $this->assertSame(['done' => true, 'remaining' => 0], $result);
    }

    /**
     * checkNextIp() returns result='known_nullhub' and marks the record
     * 'nullhub' when the IP already exists in the null_hubs table.
     *
     * @return void
     */
    public function testCheckNextIpSkipsKnownNullHub(): void
    {
        $ip = '192.168.86.90';
        $id = $this->insertIp($ip);

        // Insert a known NullHub with the same URL/IP.
        $this->nullHubModel->createNullHub([
            'mac_address' => 'aa:bb:cc:dd:ee:01',
            'name'        => 'null pi',
            'url'         => $ip,
            'main'        => 1,
        ]);

        $fetchFn = fn(string $url): string|false => false;

        $result = NullHubDriver::checkNextIp(
            $this->nmapScan,
            $this->nullHubModel,
            $fetchFn
        );

        $this->assertFalse($result['done']);
        $this->assertSame('known_nullhub', $result['result']);
        $this->assertSame($ip, $result['ip']);

        // Verify nmap record was marked 'nullhub'.
        $row = DB::query(
            'SELECT type, checked_at FROM `nmap_scans` WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('nullhub', $row['type']);
        $this->assertNotNull($row['checked_at']);
    }

    /**
     * checkNextIp() returns result='not_nullhub' and marks the record 'other'
     * when the /api/info fetch returns false (no HTTP response).
     *
     * @return void
     */
    public function testCheckNextIpMarksOtherWhenFetchFails(): void
    {
        $ip = '192.168.86.91';
        $id = $this->insertIp($ip);

        $fetchFn = fn(string $url): string|false => false;

        $result = NullHubDriver::checkNextIp(
            $this->nmapScan,
            $this->nullHubModel,
            $fetchFn
        );

        $this->assertFalse($result['done']);
        $this->assertSame('not_nullhub', $result['result']);
        $this->assertSame($ip, $result['ip']);

        $row = DB::query(
            'SELECT type FROM `nmap_scans` WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('other', $row['type']);
    }

    /**
     * checkNextIp() returns result='not_nullhub' when the response is not
     * valid JSON.
     *
     * @return void
     */
    public function testCheckNextIpMarksOtherWhenResponseNotJson(): void
    {
        $ip = '192.168.86.92';
        $id = $this->insertIp($ip);

        $fetchFn = fn(string $url): string|false => '<html><body>Not a NullHub</body></html>';

        $result = NullHubDriver::checkNextIp(
            $this->nmapScan,
            $this->nullHubModel,
            $fetchFn
        );

        $this->assertFalse($result['done']);
        $this->assertSame('not_nullhub', $result['result']);

        $row = DB::query(
            'SELECT type FROM `nmap_scans` WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('other', $row['type']);
    }

    /**
     * checkNextIp() returns result='not_nullhub' when the JSON does not
     * contain the required "info.mac_address" field.
     *
     * @return void
     */
    public function testCheckNextIpMarksOtherWhenInfoMissingMac(): void
    {
        $ip = '192.168.86.93';
        $id = $this->insertIp($ip);

        $fetchFn = fn(string $url): string|false => json_encode(['info' => ['name' => 'something']]);

        $result = NullHubDriver::checkNextIp(
            $this->nmapScan,
            $this->nullHubModel,
            $fetchFn
        );

        $this->assertFalse($result['done']);
        $this->assertSame('not_nullhub', $result['result']);
    }

    /**
     * checkNextIp() creates a new NullHub record when /api/info is valid and
     * the MAC address is not yet in the database (main hub scenario).
     *
     * @return void
     */
    public function testCheckNextIpCreatesNewMainHub(): void
    {
        $ip  = '192.168.86.90';
        $id  = $this->insertIp($ip);
        $mac = 'fe80::8e07:3a25:840d:9821';
        $body = $this->mainHubInfo($mac, $ip, 'null pi');

        $fetchFn = fn(string $url): string|false => $body;

        $result = NullHubDriver::checkNextIp(
            $this->nmapScan,
            $this->nullHubModel,
            $fetchFn
        );

        $this->assertFalse($result['done']);
        $this->assertSame('found_nullhub', $result['result']);
        $this->assertSame($ip, $result['ip']);
        $this->assertSame('null pi', $result['name']);

        // NullHub record must exist.
        $row = $this->nullHubModel->findByMac($mac);
        $this->assertNotNull($row, 'NullHub record must be created');
        $this->assertSame($ip, $row['url']);
        $this->assertSame('null pi', $row['name']);
        $this->assertSame('old_hub', $row['type']);
        $this->assertSame('1', (string) $row['main']);

        // nmap record must be marked 'nullhub'.
        $nmapRow = DB::query(
            'SELECT type FROM `nmap_scans` WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('nullhub', $nmapRow['type']);
    }

    /**
     * checkNextIp() creates a new NullHub record for a child device, correctly
     * recording the parent hub IP and optional fields.
     *
     * @return void
     */
    public function testCheckNextIpCreatesChildDevice(): void
    {
        $ip    = '192.168.86.202';
        $hubIp = '192.168.86.90';
        $mac   = 'b8:27:eb:b5:b6:7c';
        $id    = $this->insertIp($ip);
        $body  = $this->childDeviceInfo($mac, $ip, 'dev', $hubIp);

        $fetchFn = fn(string $url): string|false => $body;

        $result = NullHubDriver::checkNextIp(
            $this->nmapScan,
            $this->nullHubModel,
            $fetchFn
        );

        $this->assertSame('found_nullhub', $result['result']);

        $row = $this->nullHubModel->findByMac($mac);
        $this->assertNotNull($row);
        $this->assertSame($hubIp, $row['hub'],           'hub IP must match parent hub');
        $this->assertSame('hub', $row['type']);
        $this->assertSame('0', (string) $row['main']);
        $this->assertSame('complete', $row['setup']);
        $this->assertSame('3af17efdc976cee4105b97cbc9947908d53420ed', $row['hash']);
        $this->assertSame('https://github.com/sophiathekitty/NullHub', $row['git']);
    }

    /**
     * checkNextIp() updates an existing NullHub when the MAC address already
     * exists in the database.
     *
     * @return void
     */
    public function testCheckNextIpUpdatesExistingNullHub(): void
    {
        $newIp = '192.168.86.99';
        $mac   = 'aa:bb:cc:dd:ee:05';
        $id    = $this->insertIp($newIp);

        // Pre-existing record with an old IP.
        $this->nullHubModel->createNullHub([
            'mac_address' => $mac,
            'name'        => 'old name',
            'url'         => '192.168.86.90',
            'main'        => 1,
        ]);

        $body    = $this->mainHubInfo($mac, $newIp, 'updated name');
        $fetchFn = fn(string $url): string|false => $body;

        $result = NullHubDriver::checkNextIp(
            $this->nmapScan,
            $this->nullHubModel,
            $fetchFn
        );

        $this->assertSame('found_nullhub', $result['result']);

        // URL must be updated, no second record inserted.
        $row = $this->nullHubModel->findByMac($mac);
        $this->assertSame($newIp, $row['url'], 'URL must be updated to the new IP');

        $count = (int) DB::query('SELECT COUNT(*) FROM `null_hubs`')->fetchColumn();
        $this->assertSame(1, $count, 'No extra NullHub record must be created');
    }

    /**
     * The remaining count decrements correctly as IPs are processed sequentially.
     *
     * @return void
     */
    public function testRemainingCountDecrements(): void
    {
        $this->insertIp('192.168.86.201');
        $this->insertIp('192.168.86.202');

        $fetchFn = fn(string $url): string|false => false;

        $result1 = NullHubDriver::checkNextIp($this->nmapScan, $this->nullHubModel, $fetchFn);
        $this->assertFalse($result1['done']);
        $this->assertSame(1, $result1['remaining'], 'remaining must be 1 after first step');

        $result2 = NullHubDriver::checkNextIp($this->nmapScan, $this->nullHubModel, $fetchFn);
        $this->assertFalse($result2['done']);
        $this->assertSame(0, $result2['remaining'], 'remaining must be 0 after second step');

        $result3 = NullHubDriver::checkNextIp($this->nmapScan, $this->nullHubModel, $fetchFn);
        $this->assertTrue($result3['done'], 'done must be true when queue is exhausted');
    }

    /**
     * checkNextIp() handles the "main" field as a truthy string "1" and stores
     * it as integer 1.
     *
     * @return void
     */
    public function testMainFieldParsedFromStringOne(): void
    {
        $ip   = '10.0.0.1';
        $mac  = 'aa:00:bb:11:cc:22';
        $id   = $this->insertIp($ip);
        $body = $this->mainHubInfo($mac, $ip, 'my hub');

        $fetchFn = fn(string $url): string|false => $body;

        NullHubDriver::checkNextIp($this->nmapScan, $this->nullHubModel, $fetchFn);

        $row = $this->nullHubModel->findByMac($mac);
        $this->assertSame('1', (string) $row['main'], '"main" = "1" must be stored as 1');
    }

    /**
     * checkNextIp() handles the "main" field as boolean false and stores it
     * as integer 0.
     *
     * @return void
     */
    public function testMainFieldParsedFromBooleanFalse(): void
    {
        $ip    = '10.0.0.2';
        $hubIp = '10.0.0.1';
        $mac   = 'bb:00:cc:11:dd:22';
        $id    = $this->insertIp($ip);
        $body  = $this->childDeviceInfo($mac, $ip, 'child', $hubIp);

        $fetchFn = fn(string $url): string|false => $body;

        NullHubDriver::checkNextIp($this->nmapScan, $this->nullHubModel, $fetchFn);

        $row = $this->nullHubModel->findByMac($mac);
        $this->assertSame('0', (string) $row['main'], '"main" = false must be stored as 0');
    }
}
