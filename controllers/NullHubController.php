<?php
require_once __DIR__ . '/Controller.php';
require_once APP_ROOT . '/models/NmapScan.php';
require_once APP_ROOT . '/models/NullHub.php';
require_once APP_ROOT . '/modules/devices/NullHubDriver.php';

/**
 * NullHubController — coordinates the per-IP NullHub device scan step.
 *
 * Provides one method consumed by ScanHandler to advance the scan loop one
 * step for the NullHub device type. All business logic is delegated to
 * NullHubDriver and the model layer; this controller is HTTP-unaware.
 */
class NullHubController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Process the next unchecked IP in the nmap scan queue for NullHub detection.
     *
     * Instantiates NmapScan and NullHub models and delegates to
     * NullHubDriver::checkNextIp(). Returns the result array directly so the
     * calling handler can wrap it in the standard JSON envelope.
     *
     * Possible return values:
     *   - `['done' => true, 'remaining' => 0]` — scan complete.
     *   - `['done' => false, 'remaining' => N, 'result' => '...', 'ip' => '...']` — in progress.
     *
     * @return array<string, mixed> Status array from NullHubDriver::checkNextIp().
     */
    public function checkNext(): array
    {
        $nmapScan     = new NmapScan();
        $nullHubModel = new NullHub();

        return NullHubDriver::checkNextIp($nmapScan, $nullHubModel);
    }
}
