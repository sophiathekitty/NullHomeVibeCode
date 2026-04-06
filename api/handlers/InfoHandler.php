<?php
require_once __DIR__ . '/ApiHandler.php';
require_once __DIR__ . '/../../models/SettingsModel.php';
require_once __DIR__ . '/../../modules/network/NetworkModule.php';

/** Source repository URL included in every info response. */
define('INFO_GIT_URL', 'https://github.com/sophiathekitty/NullHomeVibeCode');

/**
 * InfoHandler — handles /api/info requests.
 *
 * Returns identifying information about this NullHome hub so that other
 * devices on the network can discover and recognise it.
 *
 * The MAC address and hostname are cached in the settings table so the
 * device identity remains stable even when the active network interface
 * changes. If they have not yet been cached, NetworkModule::detect() is
 * called to populate the settings before building the response.
 *
 * Routes:
 *   GET /api/info → device info object
 */
class InfoHandler extends ApiHandler
{
    /** @var SettingsModel */
    private SettingsModel $settings;

    /**
     * Constructor — instantiates the SettingsModel dependency.
     */
    public function __construct()
    {
        $this->settings = new SettingsModel();
    }

    /**
     * Dispatch the incoming request.
     *
     * @param array  $params URL path segments after the handler key (unused).
     * @param string $method HTTP method (only GET is supported).
     * @param array  $body   Decoded JSON request body (unused).
     * @return void
     */
    public function handle(array $params, string $method, array $body): void
    {
        if ($method !== 'GET') {
            $this->methodNotAllowed();
            return;
        }

        $this->ok(['info' => $this->buildInfo()]);
    }

    /**
     * Build the info array from cached settings, falling back to live network
     * detection when required fields are absent.
     *
     * Required fields (url, mac_address, server) are sourced from settings keys
     * host_ip, host_mac, and host_hostname respectively. If any of those are
     * missing, NetworkModule::detect() is invoked to populate them.
     *
     * Optional/configurable fields are read from additional settings keys and
     * fall back to sensible defaults when not set:
     *   - hub_name  → name  (defaults to hostname)
     *   - hub_type  → type  (defaults to 'hub')
     *   - hub_ip    → hub
     *   - hub_hub_name → hub_name
     *   - hub_room  → room  (defaults to '0')
     *
     * @return array<string, mixed>
     */
    protected function buildInfo(): array
    {
        $ip       = $this->settings->get('host_ip');
        $mac      = $this->settings->get('host_mac');
        $hostname = $this->settings->get('host_hostname');

        // If any of the core network fields are missing, detect them now and
        // refresh from the values returned (settings are also updated by detect).
        if ($ip === null || $mac === null || $hostname === null) {
            $detected = NetworkModule::detect();
            $ip       = $ip       ?? ($detected['ip']       ?? null);
            $mac      = $mac      ?? ($detected['mac']       ?? null);
            $hostname = $hostname ?? ($detected['hostname']  ?? null);
        }

        $name = $this->settings->get('hub_name') ?? $hostname;
        $type = $this->settings->get('hub_type') ?? 'hub';

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
            'room'        => $this->settings->get('hub_room') ?? '0',
            'hub'         => $this->settings->get('hub_ip'),
            'hub_name'    => $this->settings->get('hub_hub_name'),
            'main'        => false,
        ];
    }
}
