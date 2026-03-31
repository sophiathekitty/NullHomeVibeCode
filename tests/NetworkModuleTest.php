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
        DB::query("DELETE FROM `settings` WHERE `key` IN ('host_ip','host_mac','host_subnet')");
    }

    /**
     * A fake exec that returns valid output for both the IP and MAC commands.
     *
     * @param string $ip  The IP address to return on the first call.
     * @param string $mac The MAC address to return on the second call.
     * @return callable Callable compatible with NetworkModule::detect()'s $execFn.
     */
    private function makeFakeExec(string $ip, string $mac): callable
    {
        $callCount = 0;
        return function (string $command) use (&$callCount, $ip, $mac): array {
            $callCount++;
            if ($callCount === 1) {
                return [$ip];
            }
            return [$mac];
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
        NetworkModule::detect($this->makeFakeExec('10.0.0.12', '11:22:33:44:55:66'));

        $settings = new SettingsModel();
        $this->assertSame('10.0.0.12', $settings->get('host_ip'));
        $this->assertSame('11:22:33:44:55:66', $settings->get('host_mac'));
        $this->assertSame('10.0.0.0/24', $settings->get('host_subnet'));
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
}
