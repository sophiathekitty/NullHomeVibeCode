<?php
/**
 * WemoDriverTest — unit/integration tests for WemoDriver.
 *
 * cURL calls and getBinaryState() are injectable via optional callable
 * parameters so tests run without any network access.
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/models/Wemo.php';
require_once APP_ROOT . '/models/Device.php';
require_once APP_ROOT . '/modules/devices/WemoDriver.php';

class WemoDriverTest extends BaseTestCase
{
    // ── Setup ─────────────────────────────────────────────────────────────────

    /**
     * Sample Wemo row used across multiple tests.
     *
     * @var array<string, mixed>
     */
    private array $wemoRow = [
        'id'          => 1,
        'name'        => 'Test Plug',
        'mac_address' => 'aa:bb:cc:dd:ee:ff',
        'ip_address'  => '192.168.1.100',
        'port'        => 49153,
        'state'       => 0,
        'last_checked' => null,
        'device_id'   => null,
    ];

    /**
     * Truncate wemos and devices tables before every test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection()->exec('TRUNCATE TABLE `wemos`');
        DB::connection()->exec('TRUNCATE TABLE `devices`');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a minimal SOAP response envelope wrapping the given body fragment.
     *
     * @param string $body XML body fragment to embed inside s:Body.
     * @return string A complete SOAP envelope string.
     */
    private function soapEnvelope(string $body): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<s:Body>' . $body . '</s:Body>'
            . '</s:Envelope>';
    }

    /**
     * Build a SOAP GetBinaryState response with the given state value.
     *
     * @param string $state The value to embed in <BinaryState>.
     * @return string A complete SOAP response envelope string.
     */
    private function binaryStateResponse(string $state): string
    {
        return $this->soapEnvelope(
            '<u:GetBinaryStateResponse xmlns:u="urn:Belkin:service:basicevent:1">'
            . '<BinaryState>' . $state . '</BinaryState>'
            . '</u:GetBinaryStateResponse>'
        );
    }

    /**
     * Build a SOAP fault response envelope.
     *
     * @return string A complete SOAP fault envelope string.
     */
    private function soapFaultResponse(): string
    {
        return $this->soapEnvelope(
            '<s:Fault>'
            . '<faultcode>s:Client</faultcode>'
            . '<faultstring>UPnPError</faultstring>'
            . '</s:Fault>'
        );
    }

    // ── getBinaryState ────────────────────────────────────────────────────────

    /**
     * getBinaryState() returns 1 when the SOAP response contains
     * <BinaryState>1</BinaryState>.
     *
     * @return void
     */
    public function testGetBinaryStateReturnsOne(): void
    {
        $curlFn = fn() => $this->binaryStateResponse('1');

        $result = WemoDriver::getBinaryState($this->wemoRow, $curlFn);

        $this->assertSame(1, $result);
    }

    /**
     * getBinaryState() returns 0 when the SOAP response contains
     * <BinaryState>0</BinaryState>.
     *
     * @return void
     */
    public function testGetBinaryStateReturnsZero(): void
    {
        $curlFn = fn() => $this->binaryStateResponse('0');

        $result = WemoDriver::getBinaryState($this->wemoRow, $curlFn);

        $this->assertSame(0, $result);
    }

    /**
     * getBinaryState() returns null when the SOAP response contains a
     * <faultstring> element.
     *
     * @return void
     */
    public function testGetBinaryStateReturnsNullOnFault(): void
    {
        $curlFn = fn() => $this->soapFaultResponse();

        $result = WemoDriver::getBinaryState($this->wemoRow, $curlFn);

        $this->assertNull($result);
    }

    /**
     * getBinaryState() returns null when the cURL call returns false
     * (connection error).
     *
     * @return void
     */
    public function testGetBinaryStateReturnsNullOnCurlFailure(): void
    {
        $curlFn = fn() => false;

        $result = WemoDriver::getBinaryState($this->wemoRow, $curlFn);

        $this->assertNull($result);
    }

    // ── setBinaryState ────────────────────────────────────────────────────────

    /**
     * setBinaryState() returns true when the SOAP response echoes back the
     * requested state value.
     *
     * @return void
     */
    public function testSetBinaryStateReturnsTrue(): void
    {
        $curlFn = fn() => $this->soapEnvelope(
            '<u:SetBinaryStateResponse xmlns:u="urn:Belkin:service:basicevent:1">'
            . '<BinaryState>1</BinaryState>'
            . '</u:SetBinaryStateResponse>'
        );

        $result = WemoDriver::setBinaryState($this->wemoRow, 1, $curlFn);

        $this->assertTrue($result);
    }

    /**
     * setBinaryState() returns false when the SOAP response contains a state
     * value that does not match the requested target state.
     *
     * @return void
     */
    public function testSetBinaryStateReturnsFalse(): void
    {
        // Response echoes state 0, but we requested state 1.
        $curlFn = fn() => $this->soapEnvelope(
            '<u:SetBinaryStateResponse xmlns:u="urn:Belkin:service:basicevent:1">'
            . '<BinaryState>0</BinaryState>'
            . '</u:SetBinaryStateResponse>'
        );

        $result = WemoDriver::setBinaryState($this->wemoRow, 1, $curlFn);

        $this->assertFalse($result);
    }

    // ── observe ───────────────────────────────────────────────────────────────

    /**
     * observe() updates wemo state and last_checked, and syncs the linked device
     * when getBinaryState() returns a valid state.
     *
     * @return void
     */
    public function testObserveUpdatesStateAndLastChecked(): void
    {
        $deviceModel = new Device();
        $deviceId    = $deviceModel->insert([
            'name'       => 'Wemo Device',
            'type'       => 'light',
            'state'      => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $wemoModel = new Wemo();
        $wemoId    = $wemoModel->createWemo([
            'name'        => 'Test Plug',
            'mac_address' => 'aa:bb:cc:dd:ee:ff',
            'ip_address'  => '192.168.1.100',
            'port'        => 49153,
            'state'       => 0,
            'device_id'   => $deviceId,
        ]);

        // Inject a getStateFn that always returns 1 (device is on).
        $getStateFn = fn(array $wemo): ?int => 1;

        WemoDriver::observe($getStateFn, $wemoModel, $deviceModel);

        $wemoRow   = DB::query('SELECT * FROM `wemos` WHERE id = ?', [$wemoId])->fetch(PDO::FETCH_ASSOC);
        $deviceRow = DB::query('SELECT * FROM `devices` WHERE id = ?', [$deviceId])->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('1', (string) $wemoRow['state'],    'Wemo state must be updated to 1');
        $this->assertNotNull($wemoRow['last_checked'],          'last_checked must be set after a successful poll');
        $this->assertSame('1', (string) $deviceRow['state'],  'Linked device state must be synced to 1');
    }

    /**
     * observe() does not update last_checked when getBinaryState() returns null
     * (device unreachable).
     *
     * @return void
     */
    public function testObserveDoesNotUpdateLastCheckedOnFailure(): void
    {
        $wemoModel = new Wemo();
        $wemoId    = $wemoModel->createWemo([
            'name'        => 'Unreachable Plug',
            'mac_address' => '11:22:33:44:55:66',
            'ip_address'  => '192.168.1.200',
            'port'        => 49153,
            'state'       => 0,
        ]);

        // Inject a getStateFn that always returns null (device unreachable).
        $getStateFn = fn(array $wemo): ?int => null;

        WemoDriver::observe($getStateFn, $wemoModel, new Device());

        $wemoRow = DB::query(
            'SELECT state, last_checked FROM `wemos` WHERE id = ?',
            [$wemoId]
        )->fetch(PDO::FETCH_ASSOC);

        $this->assertNull($wemoRow['last_checked'], 'last_checked must remain null when the device is unreachable');
        $this->assertSame('0', (string) $wemoRow['state'], 'state must remain unchanged when the device is unreachable');
    }
}
