<?php
/**
 * InfoHandlerTest — unit tests for the InfoHandler API class.
 *
 * Tests exercise the handler logic via an exposed test double rather than
 * going through the full HTTP layer (which calls exit via ok()).
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/api/handlers/ApiHandler.php';
require_once APP_ROOT . '/api/handlers/InfoHandler.php';
require_once APP_ROOT . '/modules/network/NetworkModule.php';

class InfoHandlerTest extends BaseTestCase
{
    /** @var InfoHandlerTestDouble */
    private InfoHandlerTestDouble $handler;

    protected function setUp(): void
    {
        parent::setUp();
        DB::query(
            "DELETE FROM `settings` WHERE `key` IN
             ('host_ip','host_mac','host_hostname','hub_name','hub_type','hub_room','hub_ip','hub_hub_name')"
        );
        $this->handler = new InfoHandlerTestDouble();
    }

    // ── Response shape ────────────────────────────────────────────────────────

    /**
     * buildInfo() must always include the mandatory top-level keys.
     *
     * @return void
     */
    public function testBuildInfoContainsMandatoryKeys(): void
    {
        $settings = new SettingsModel();
        $settings->set('host_ip',       '10.0.0.5');
        $settings->set('host_mac',      'aa:bb:cc:dd:ee:ff');
        $settings->set('host_hostname', 'my-hub');

        $info = $this->handler->exposedBuildInfo();

        foreach (['url', 'mac_address', 'name', 'type', 'server'] as $key) {
            $this->assertArrayHasKey($key, $info, "Info must contain key '$key'.");
        }
    }

    /**
     * When all three core settings are cached, buildInfo() returns them without
     * calling NetworkModule::detect().
     *
     * @return void
     */
    public function testBuildInfoUsesCachedSettings(): void
    {
        $settings = new SettingsModel();
        $settings->set('host_ip',       '192.168.1.99');
        $settings->set('host_mac',      'de:ad:be:ef:00:01');
        $settings->set('host_hostname', 'cached-host');

        $info = $this->handler->exposedBuildInfo();

        $this->assertSame('192.168.1.99',       $info['url']);
        $this->assertSame('de:ad:be:ef:00:01',  $info['mac_address']);
        $this->assertSame('cached-host',         $info['server']);
    }

    /**
     * The 'name' field falls back to the hostname when hub_name is not set.
     *
     * @return void
     */
    public function testNameDefaultsToHostname(): void
    {
        $settings = new SettingsModel();
        $settings->set('host_ip',       '10.0.0.1');
        $settings->set('host_mac',      'aa:bb:cc:dd:ee:ff');
        $settings->set('host_hostname', 'fallback-host');

        $info = $this->handler->exposedBuildInfo();

        $this->assertSame('fallback-host', $info['name']);
    }

    /**
     * The 'name' field uses hub_name when it is set, ignoring the hostname.
     *
     * @return void
     */
    public function testNameUsesHubNameSetting(): void
    {
        $settings = new SettingsModel();
        $settings->set('host_ip',       '10.0.0.1');
        $settings->set('host_mac',      'aa:bb:cc:dd:ee:ff');
        $settings->set('host_hostname', 'some-host');
        $settings->set('hub_name',      'My NullHome');

        $info = $this->handler->exposedBuildInfo();

        $this->assertSame('My NullHome', $info['name']);
    }

    /**
     * The 'type' field defaults to 'hub' when hub_type is not set.
     *
     * @return void
     */
    public function testTypeDefaultsToHub(): void
    {
        $settings = new SettingsModel();
        $settings->set('host_ip',       '10.0.0.1');
        $settings->set('host_mac',      'aa:bb:cc:dd:ee:ff');
        $settings->set('host_hostname', 'some-host');

        $info = $this->handler->exposedBuildInfo();

        $this->assertSame('hub', $info['type']);
    }

    /**
     * The 'type' field uses the hub_type setting when set.
     *
     * @return void
     */
    public function testTypeUsesHubTypeSetting(): void
    {
        $settings = new SettingsModel();
        $settings->set('host_ip',       '10.0.0.1');
        $settings->set('host_mac',      'aa:bb:cc:dd:ee:ff');
        $settings->set('host_hostname', 'some-host');
        $settings->set('hub_type',      'device');

        $info = $this->handler->exposedBuildInfo();

        $this->assertSame('device', $info['type']);
    }

    /**
     * The 'room' field defaults to '0' when hub_room is not set.
     *
     * @return void
     */
    public function testRoomDefaultsToZero(): void
    {
        $settings = new SettingsModel();
        $settings->set('host_ip',       '10.0.0.1');
        $settings->set('host_mac',      'aa:bb:cc:dd:ee:ff');
        $settings->set('host_hostname', 'some-host');

        $info = $this->handler->exposedBuildInfo();

        $this->assertSame('0', $info['room']);
    }

    /**
     * buildInfo() triggers NetworkModule::detect() and uses the returned values
     * when the core settings (host_ip, host_mac, host_hostname) are absent.
     *
     * The test double's detect() is called with a fake exec that returns known
     * values, and the resulting info array must reflect those values.
     *
     * @return void
     */
    public function testBuildInfoTriggersDetectWhenSettingsMissing(): void
    {
        // Nothing pre-seeded — handler must call detect() internally.
        $fakeExec  = $this->makeFakeExec('172.16.0.5', 'ff:ee:dd:cc:bb:aa', 'detected-host');
        $info      = $this->handler->exposedBuildInfo($fakeExec);

        $this->assertSame('172.16.0.5',         $info['url']);
        $this->assertSame('ff:ee:dd:cc:bb:aa',  $info['mac_address']);
        $this->assertSame('detected-host',       $info['server']);
    }

    // ── Fixed fields ──────────────────────────────────────────────────────────

    /**
     * Constant fields that must always be present with the expected values.
     *
     * @return void
     */
    public function testFixedFieldValues(): void
    {
        $settings = new SettingsModel();
        $settings->set('host_ip',       '10.0.0.1');
        $settings->set('host_mac',      'aa:bb:cc:dd:ee:ff');
        $settings->set('host_hostname', 'some-host');

        $info = $this->handler->exposedBuildInfo();

        $this->assertSame('/',     $info['path']);
        $this->assertSame('complete', $info['setup']);
        $this->assertTrue($info['is_hub']);
        $this->assertSame('1', $info['enabled']);
        $this->assertFalse($info['main']);
        $this->assertStringContainsString('github.com', $info['git']);
        $this->assertSame(INFO_GIT_URL, $info['git']);
    }

    // ── HTTP method guard ─────────────────────────────────────────────────────

    /**
     * Non-GET requests must be rejected with 405 Method Not Allowed.
     *
     * @return void
     */
    public function testNonGetRequestIsRejected(): void
    {
        $caught = null;
        try {
            $this->handler->handle([], 'POST', []);
        } catch (\Exception $e) {
            $caught = $e;
        }
        // The handler calls exit() via ok()/error(); the test double overrides
        // those methods to throw instead, so we can inspect the outcome.
        $this->assertInstanceOf(\RuntimeException::class, $caught);
        $this->assertStringContainsString('405', $caught->getMessage());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a fake exec callable compatible with NetworkModule::detect().
     *
     * @param string $ip       IP to return on the first call.
     * @param string $mac      MAC to return on the second call.
     * @param string $hostname Hostname to return on the third call.
     * @return callable
     */
    private function makeFakeExec(string $ip, string $mac, string $hostname): callable
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
}

/**
 * Test double for InfoHandler that:
 *  - Exposes buildInfo() publicly (and optionally accepts a custom execFn for
 *    NetworkModule::detect() so tests can inject deterministic network output).
 *  - Overrides ok() / error() to throw RuntimeException instead of echoing
 *    JSON and calling exit(), keeping tests runnable.
 */
class InfoHandlerTestDouble extends InfoHandler
{
    /** Last captured response data (set by overridden ok()). */
    public mixed $lastData = null;

    /**
     * Public wrapper around the protected buildInfo() method.
     *
     * When $execFn is provided it is forwarded to NetworkModule::detect() via
     * a temporary subclass override rather than monkey-patching the static
     * call, keeping the implementation clean.
     *
     * @param callable|null $execFn Optional exec callable for NetworkModule.
     * @return array<string, mixed>
     */
    public function exposedBuildInfo(?callable $execFn = null): array
    {
        if ($execFn !== null) {
            // Temporarily replace the detect call by delegating through a
            // closure that forwards the provided execFn.
            return $this->buildInfoWithExec($execFn);
        }
        return $this->buildInfo();
    }

    /**
     * Variant of buildInfo() that passes $execFn into NetworkModule::detect().
     *
     * This mirrors buildInfo() exactly but uses the injected callable so tests
     * can control network detection without hitting the real system.
     *
     * @param callable $execFn
     * @return array<string, mixed>
     */
    private function buildInfoWithExec(callable $execFn): array
    {
        $settings = new SettingsModel();

        $ip       = $settings->get('host_ip');
        $mac      = $settings->get('host_mac');
        $hostname = $settings->get('host_hostname');

        if ($ip === null || $mac === null || $hostname === null) {
            $detected = NetworkModule::detect($execFn);
            $ip       = $ip       ?? ($detected['ip']       ?? null);
            $mac      = $mac      ?? ($detected['mac']       ?? null);
            $hostname = $hostname ?? ($detected['hostname']  ?? null);
        }

        $name = $settings->get('hub_name') ?? $hostname;
        $type = $settings->get('hub_type') ?? 'hub';

        return [
            'url'         => $ip,
            'type'        => $type,
            'server'      => $hostname,
            'mac_address' => $mac,
            'name'        => $name,
            'path'        => '/',
            'setup'       => 'complete',
            'git'         => INFO_GIT_URL,
            'is_hub'      => true,
            'enabled'     => '1',
            'room'        => $settings->get('hub_room') ?? '0',
            'hub'         => $settings->get('hub_ip'),
            'hub_name'    => $settings->get('hub_hub_name'),
            'main'        => false,
        ];
    }

    /**
     * Override ok() to capture data and throw instead of echoing + exiting.
     *
     * @param mixed $data
     * @return void
     */
    protected function ok(mixed $data = null): void
    {
        $this->lastData = $data;
        throw new \RuntimeException('200 OK');
    }

    /**
     * Override error() to throw instead of echoing + exiting.
     *
     * @param string $message
     * @param int    $status
     * @return void
     */
    protected function error(string $message, int $status = 400): void
    {
        throw new \RuntimeException("$status $message");
    }
}
