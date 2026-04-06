<?php
require_once APP_ROOT . '/modules/network/NetworkModule.php';
require_once APP_ROOT . '/models/SettingsModel.php';

/**
 * NetworkModuleTest -- unit and integration tests for NetworkModule::detect().
 *
 * Tests do not shell out to the real system. The exec() call is replaced by
 * an injected callable so tests remain deterministic and portable.
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */
class NetworkModuleTest extends BaseTestCase
{
    /**
     * Clear relevant settings keys before each test so results are isolated.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        DB::query("DELETE FROM `settings` WHERE `key` IN ('host_ip','host_mac','host_subnet','host_hostname')");
    }

    /**
     * A fake exec that returns valid output for the IP, MAC, and hostname commands.
     *
     * @param string $ip       The IP address to return on the first call.
     * @param string $mac      The MAC address to return on the second call.
     * @param string $hostname The hostname to return on the third call.
     * @return callable Callable compatible with NetworkModule::detect()'s $execFn.
     */
    private function makeFakeExec(string $ip, string $mac, string $hostname = 'test-host'): callable
    {
        $callCount = 0;
        return function (string $command) use (&$callCount, $ip, $mac, $hostname): array {
            $callCount++;
            if ($callCount === 1) {
                return [$ip];
            }
            if ($callCount === 2) {
                return [$mac];
            }
            return [$hostname];
        };
    }

    /**
     * Inject a fake exec returning valid IP/MAC output and assert the returned
     * array contains correct 'ip', 'mac', and 'subnet' keys and values.
     *
     * @return void
     */
    public function testDetectReturnsIpMacSubnet(): void
    {
        $result = NetworkModule::detect($this->makeFakeExec('192.168.1.50', 'aa:bb:cc:dd:ee:ff'));

        $this->assertSame('192.168.1.50', $result['ip']);
        $this->assertSame('aa:bb:cc:dd:ee:ff', $result['mac']);
        $this->assertSame('192.168.1.0/24', $result['subnet']);
    }

    /**
     * Verify that the /24 subnet is correctly derived from various IPv4 addresses.
     *
     * @return void
     */
    public function testDetectDerivesSubnetCorrectly(): void
    {
        $cases = [
            '192.168.1.50' => '192.168.1.0/24',
            '10.0.5.200'   => '10.0.5.0/24',
        ];

        foreach ($cases as $ip => $expectedSubnet) {
            $result = NetworkModule::detect($this->makeFakeExec($ip, 'aa:bb:cc:dd:ee:ff'));
            $this->assertSame(
                $expectedSubnet,
                $result['subnet'],
                "Expected subnet '$expectedSubnet' for IP '$ip'."
            );
        }
    }

    /**
     * Assert that detect() writes the correct keys to the settings table after
     * a successful detection.
     *
     * @return void
     */
    public function testDetectWritesToSettings(): void
    {
        NetworkModule::detect($this->makeFakeExec('10.0.0.12', '11:22:33:44:55:66', 'my-server'));

        $settings = new SettingsModel();
        $this->assertSame('10.0.0.12', $settings->get('host_ip'));
        $this->assertSame('11:22:33:44:55:66', $settings->get('host_mac'));
        $this->assertSame('10.0.0.0/24', $settings->get('host_subnet'));
        $this->assertSame('my-server', $settings->get('host_hostname'));
    }

    /**
     * Assert that detect() includes the hostname in its returned array.
     *
     * @return void
     */
    public function testDetectReturnsHostname(): void
    {
        $result = NetworkModule::detect($this->makeFakeExec('192.168.1.10', 'aa:bb:cc:dd:ee:ff', 'nullhome'));

        $this->assertArrayHasKey('hostname', $result);
        $this->assertSame('nullhome', $result['hostname']);
    }

    /**
     * Assert that when the hostname command returns empty output, detect() still
     * succeeds and does not write host_hostname to settings.
     *
     * @return void
     */
    public function testDetectOmitsHostnameWhenEmpty(): void
    {
        $callCount = 0;
        $fakeExec  = static function (string $command) use (&$callCount): array {
            $callCount++;
            if ($callCount === 1) {
                return ['192.168.1.20'];
            }
            if ($callCount === 2) {
                return ['cc:dd:ee:ff:00:11'];
            }
            return []; // hostname returns nothing
        };

        $result = NetworkModule::detect($fakeExec);

        $this->assertSame('192.168.1.20', $result['ip']);
        $this->assertSame('', $result['hostname']);

        $settings = new SettingsModel();
        $this->assertNull($settings->get('host_hostname'), 'host_hostname must not be written when empty.');
    }

    /**
     * Inject a fake exec returning empty output and assert that detect() returns
     * an empty array and writes nothing to the settings table.
     *
     * @return void
     */
    public function testDetectReturnsEmptyOnFailure(): void
    {
        $fakeExec = static function (string $command): array {
            return [];
        };

        $result = NetworkModule::detect($fakeExec);

        $this->assertSame([], $result);

        $settings = new SettingsModel();
        $this->assertNull($settings->get('host_ip'));
        $this->assertNull($settings->get('host_mac'));
        $this->assertNull($settings->get('host_subnet'));
    }

    /**
     * Inject a fake ping sweep with 3 hosts and assert the method returns 3
     * and calls NmapScan::insertIps() with all 3 IPs.
     *
     * @return void
     */
    public function testDiscoverIpsReturnsParsedCount(): void
    {
        $fakeExec = static function (string $command): array {
            return [
                'Host: 192.168.1.1 (router.local)   Status: Up',
                'Host: 192.168.1.50 ()              Status: Up',
                'Host: 192.168.1.101 ()             Status: Up',
            ];
        };

        $nmapScan = $this->createMock(NmapScan::class);
        $nmapScan->expects($this->once())
                 ->method('insertIps')
                 ->with(['192.168.1.1', '192.168.1.50', '192.168.1.101']);

        $count = NetworkModule::discoverIps($nmapScan, '192.168.1.0/24', $fakeExec);

        $this->assertSame(3, $count);
    }

    /**
     * Inject ping sweep output that includes the host's own IP and assert that
     * IP is excluded from the list passed to insertIps().
     *
     * @return void
     */
    public function testDiscoverIpsFiltersHostIp(): void
    {
        $settings = new SettingsModel();
        $settings->set('host_ip', '192.168.1.50');

        $fakeExec = static function (string $command): array {
            return [
                'Host: 192.168.1.1 ()   Status: Up',
                'Host: 192.168.1.50 ()  Status: Up',
                'Host: 192.168.1.101 () Status: Up',
            ];
        };

        $nmapScan = $this->createMock(NmapScan::class);
        $nmapScan->expects($this->once())
                 ->method('insertIps')
                 ->with(['192.168.1.1', '192.168.1.101']);

        $count = NetworkModule::discoverIps($nmapScan, '192.168.1.0/24', $fakeExec);

        $this->assertSame(2, $count);
    }

    /**
     * Assert that discoverIps() throws a RuntimeException when no subnet is
     * provided and host_subnet is not set in settings.
     *
     * @return void
     */
    public function testDiscoverIpsThrowsWhenNoSubnet(): void
    {
        $nmapScan = $this->createMock(NmapScan::class);
        $nmapScan->expects($this->never())->method('insertIps');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Host subnet not configured');

        NetworkModule::discoverIps($nmapScan);
    }

    /**
     * Pass an explicit $subnet argument and assert it is used in the exec command
     * instead of the value stored in settings.
     *
     * @return void
     */
    public function testDiscoverIpsUsesProvidedSubnet(): void
    {
        $settings = new SettingsModel();
        $settings->set('host_subnet', '10.0.0.0/24');

        $capturedCmd = '';
        $fakeExec = static function (string $command) use (&$capturedCmd): array {
            $capturedCmd = $command;
            return [];
        };

        $nmapScan = $this->createMock(NmapScan::class);

        NetworkModule::discoverIps($nmapScan, '172.16.0.0/24', $fakeExec);

        $this->assertStringContainsString('172.16.0.0/24', $capturedCmd);
        $this->assertStringNotContainsString('10.0.0.0/24', $capturedCmd);
    }

    /**
     * Inject fake port scan output with ports 49153 and 80 open and assert
     * getOpenPorts() returns [49153, 80].
     *
     * @return void
     */
    public function testGetOpenPortsReturnsParsedPorts(): void
    {
        $fakeExec = static function (string $command): array {
            return [
                'Host: 192.168.1.101 ()  Ports: 49153/open/tcp/////, 80/open/tcp/////',
            ];
        };

        $ports = NetworkModule::getOpenPorts('192.168.1.101', $fakeExec);

        $this->assertSame([49153, 80], $ports);
    }

    /**
     * Inject empty exec output and assert getOpenPorts() returns an empty array.
     *
     * @return void
     */
    public function testGetOpenPortsReturnsEmptyOnNoOutput(): void
    {
        $fakeExec = static function (string $command): array {
            return [];
        };

        $ports = NetworkModule::getOpenPorts('192.168.1.101', $fakeExec);

        $this->assertSame([], $ports);
    }

    /**
     * Inject output with a mix of open and closed ports and assert that only
     * the open port numbers are returned.
     *
     * @return void
     */
    public function testGetOpenPortsIgnoresClosedPorts(): void
    {
        $fakeExec = static function (string $command): array {
            return [
                'Host: 192.168.1.101 ()  Ports: 80/open/tcp/////, 443/closed/tcp/////, 8080/open/tcp/////',
            ];
        };

        $ports = NetworkModule::getOpenPorts('192.168.1.101', $fakeExec);

        $this->assertSame([80, 8080], $ports);
    }
}
