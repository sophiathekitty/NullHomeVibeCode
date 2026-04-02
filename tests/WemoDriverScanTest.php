<?php
/**
 * WemoDriverScanTest — unit/integration tests for WemoDriver::checkNextIp().
 *
 * All external calls (cURL setup.xml fetch and NetworkModule::getOpenPorts)
 * are injectable via optional callable parameters so that tests run without
 * any network access.
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/models/NmapScan.php';
require_once APP_ROOT . '/models/Wemo.php';
require_once APP_ROOT . '/models/LightsModel.php';
require_once APP_ROOT . '/modules/network/NetworkModule.php';
require_once APP_ROOT . '/modules/devices/WemoDriver.php';

class WemoDriverScanTest extends BaseTestCase
{
    // ── Setup ─────────────────────────────────────────────────────────────────

    /** @var NmapScan */
    private NmapScan $nmapScan;

    /** @var Wemo */
    private Wemo $wemoModel;

    /** @var LightsModel */
    private LightsModel $lightsModel;

    /**
     * Truncate all relevant tables before every test for a clean slate.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->nmapScan    = new NmapScan();
        $this->wemoModel   = new Wemo();
        $this->lightsModel = new LightsModel();

        DB::connection()->exec('TRUNCATE TABLE `nmap_scans`');
        DB::connection()->exec('TRUNCATE TABLE `wemos`');
        DB::connection()->exec('TRUNCATE TABLE `lights`');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a minimal Wemo setup.xml response string.
     *
     * @param string $friendlyName Device friendly name.
     * @param string $macAddress   Device MAC address.
     * @return string XML response body.
     */
    private function setupXml(string $friendlyName, string $macAddress): string
    {
        return '<?xml version="1.0"?>'
            . '<root xmlns="urn:Belkin:device-1-0">'
            . '<device>'
            . '<friendlyName>' . htmlspecialchars($friendlyName) . '</friendlyName>'
            . '<macAddress>' . $macAddress . '</macAddress>'
            . '</device>'
            . '</root>';
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
     * checkNextIp() returns ['done' => true, 'remaining' => 0] when the nmap
     * queue is empty.
     *
     * @return void
     */
    public function testCheckNextIpReturnsDoneWhenQueueEmpty(): void
    {
        $result = WemoDriver::checkNextIp($this->nmapScan, $this->wemoModel);

        $this->assertSame(['done' => true, 'remaining' => 0], $result);
    }

    /**
     * checkNextIp() returns result='known_wemo' and marks the record 'wemo'
     * when the IP already exists in the wemos table.
     *
     * @return void
     */
    public function testCheckNextIpSkipsKnownWemo(): void
    {
        $ip = '192.168.1.50';
        $id = $this->insertIp($ip);

        // Insert a known Wemo with the same IP.
        $this->wemoModel->createWemo([
            'name'        => 'Known Lamp',
            'mac_address' => 'aa:bb:cc:dd:ee:01',
            'ip_address'  => $ip,
            'port'        => 49153,
        ]);

        $portsFn  = fn(string $scanIp): array => [];
        $fetchFn  = fn(string $url): string|false => false;

        $result = WemoDriver::checkNextIp(
            $this->nmapScan,
            $this->wemoModel,
            $fetchFn,
            $portsFn
        );

        $this->assertFalse($result['done']);
        $this->assertSame('known_wemo', $result['result']);
        $this->assertSame($ip, $result['ip']);

        // Verify nmap record was marked 'wemo'.
        $row = DB::query(
            'SELECT type, checked_at FROM `nmap_scans` WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('wemo', $row['type']);
        $this->assertNotNull($row['checked_at']);
    }

    /**
     * checkNextIp() returns result='no_ports' and marks the record 'other'
     * when getOpenPorts() returns an empty array.
     *
     * @return void
     */
    public function testCheckNextIpMarksOtherWhenNoPorts(): void
    {
        $ip = '192.168.1.51';
        $id = $this->insertIp($ip);

        $portsFn = fn(string $scanIp): array => [];
        $fetchFn = fn(string $url): string|false => false;

        $result = WemoDriver::checkNextIp(
            $this->nmapScan,
            $this->wemoModel,
            $fetchFn,
            $portsFn
        );

        $this->assertFalse($result['done']);
        $this->assertSame('no_ports', $result['result']);
        $this->assertSame($ip, $result['ip']);

        $row = DB::query(
            'SELECT type FROM `nmap_scans` WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('other', $row['type']);
    }

    /**
     * checkNextIp() returns result='not_wemo' and marks the record 'other'
     * when setup.xml fetch returns false for all ports.
     *
     * @return void
     */
    public function testCheckNextIpMarksOtherWhenSetupXmlMissing(): void
    {
        $ip = '192.168.1.52';
        $id = $this->insertIp($ip);

        $portsFn = fn(string $scanIp): array => [49153];
        $fetchFn = fn(string $url): string|false => false;

        $result = WemoDriver::checkNextIp(
            $this->nmapScan,
            $this->wemoModel,
            $fetchFn,
            $portsFn
        );

        $this->assertFalse($result['done']);
        $this->assertSame('not_wemo', $result['result']);
        $this->assertSame($ip, $result['ip']);

        $row = DB::query(
            'SELECT type FROM `nmap_scans` WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('other', $row['type']);
    }

    /**
     * checkNextIp() creates a new Wemo record and linked Light when setup.xml
     * is valid and the MAC address is not yet in the database.
     *
     * @return void
     */
    public function testCheckNextIpCreatesNewWemoAndLight(): void
    {
        $ip  = '192.168.1.53';
        $id  = $this->insertIp($ip);
        $xml = $this->setupXml('Desk Lamp', 'aa:bb:cc:dd:ee:02');

        $portsFn = fn(string $scanIp): array => [49153];
        $fetchFn = fn(string $url): string|false => $xml;

        $result = WemoDriver::checkNextIp(
            $this->nmapScan,
            $this->wemoModel,
            $fetchFn,
            $portsFn,
            $this->lightsModel
        );

        $this->assertFalse($result['done']);
        $this->assertSame('found_wemo', $result['result']);
        $this->assertSame($ip, $result['ip']);
        $this->assertSame('Desk Lamp', $result['name']);

        // Wemo record must exist.
        $wemoRow = $this->wemoModel->findByMac('aa:bb:cc:dd:ee:02');
        $this->assertNotNull($wemoRow, 'Wemo record must be created');
        $this->assertSame($ip, $wemoRow['ip_address']);

        // Linked Light must exist.
        $this->assertNotNull($wemoRow['light_id'], 'light_id must be set');
        $lightRow = DB::query(
            'SELECT * FROM `lights` WHERE id = ?',
            [(int) $wemoRow['light_id']]
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertNotNull($lightRow, 'Light record must be created');
        $this->assertSame('Desk Lamp', $lightRow['name']);

        // nmap record must be marked 'wemo'.
        $nmapRow = DB::query(
            'SELECT type FROM `nmap_scans` WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('wemo', $nmapRow['type']);
    }

    /**
     * checkNextIp() updates an existing Wemo with the new IP when setup.xml
     * returns a MAC that already exists in the database, and does NOT create
     * a second Light record.
     *
     * @return void
     */
    public function testCheckNextIpUpdatesExistingWemo(): void
    {
        $newIp = '192.168.1.54';
        $mac   = 'aa:bb:cc:dd:ee:03';
        $id    = $this->insertIp($newIp);

        // Pre-existing Wemo with an old IP.
        $lightId = $this->lightsModel->create(['name' => 'Bookshelf Light']);
        $this->wemoModel->createWemo([
            'name'        => 'Bookshelf Light',
            'mac_address' => $mac,
            'ip_address'  => '192.168.1.100',
            'port'        => 49153,
            'light_id'    => $lightId,
        ]);

        $lightCountBefore = (int) DB::query('SELECT COUNT(*) FROM `lights`')->fetchColumn();

        $xml     = $this->setupXml('Bookshelf Light', $mac);
        $portsFn = fn(string $scanIp): array => [49153];
        $fetchFn = fn(string $url): string|false => $xml;

        $result = WemoDriver::checkNextIp(
            $this->nmapScan,
            $this->wemoModel,
            $fetchFn,
            $portsFn,
            $this->lightsModel
        );

        $this->assertSame('found_wemo', $result['result']);

        // IP must be updated.
        $wemoRow = $this->wemoModel->findByMac($mac);
        $this->assertSame($newIp, $wemoRow['ip_address'], 'IP address must be updated');

        // No extra Light must have been created.
        $lightCountAfter = (int) DB::query('SELECT COUNT(*) FROM `lights`')->fetchColumn();
        $this->assertSame(
            $lightCountBefore,
            $lightCountAfter,
            'Light::create() must NOT be called for an existing Wemo'
        );
    }

    /**
     * checkNextIp() stops checking ports after the first valid setup.xml is found,
     * so the second port is never fetched.
     *
     * @return void
     */
    public function testCheckNextIpStopsAfterFirstWemoPort(): void
    {
        $ip  = '192.168.1.55';
        $id  = $this->insertIp($ip);
        $xml = $this->setupXml('Ceiling Light', 'aa:bb:cc:dd:ee:04');

        $portsFn   = fn(string $scanIp): array => [49153, 80];
        $callCount = 0;
        $fetchFn   = function (string $url) use ($xml, &$callCount): string|false {
            $callCount++;
            // Only the first port should be fetched.
            return $xml;
        };

        WemoDriver::checkNextIp(
            $this->nmapScan,
            $this->wemoModel,
            $fetchFn,
            $portsFn,
            $this->lightsModel
        );

        $this->assertSame(1, $callCount, 'fetchFn must be called exactly once (first matching port)');
    }

    /**
     * The remaining count decrements correctly as IPs are processed sequentially.
     *
     * @return void
     */
    public function testRemainingCountDecrements(): void
    {
        $this->insertIp('192.168.1.60');
        $this->insertIp('192.168.1.61');

        $portsFn = fn(string $scanIp): array => [];
        $fetchFn = fn(string $url): string|false => false;

        $result1 = WemoDriver::checkNextIp(
            $this->nmapScan,
            $this->wemoModel,
            $fetchFn,
            $portsFn
        );
        $this->assertFalse($result1['done']);
        $this->assertSame(1, $result1['remaining'], 'remaining must be 1 after first step');

        $result2 = WemoDriver::checkNextIp(
            $this->nmapScan,
            $this->wemoModel,
            $fetchFn,
            $portsFn
        );
        $this->assertFalse($result2['done']);
        $this->assertSame(0, $result2['remaining'], 'remaining must be 0 after second step');

        $result3 = WemoDriver::checkNextIp(
            $this->nmapScan,
            $this->wemoModel,
            $fetchFn,
            $portsFn
        );
        $this->assertTrue($result3['done'], 'done must be true when queue is exhausted');
    }
}
