<?php
require_once __DIR__ . '/Controller.php';
require_once APP_ROOT . '/models/NmapScan.php';
require_once APP_ROOT . '/models/Wemo.php';
require_once APP_ROOT . '/modules/devices/WemoDriver.php';

/**
 * WemoController — coordinates the per-IP Wemo device scan step.
 *
 * Provides one method consumed by ScanHandler to advance the scan loop one
 * step for the Wemo device type. All business logic is delegated to
 * WemoDriver and the model layer; this controller is HTTP-unaware.
 */
class WemoController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Process the next unchecked IP in the nmap scan queue for Wemo detection.
     *
     * Instantiates NmapScan and Wemo models and delegates to
     * WemoDriver::checkNextIp(). Returns the result array directly so the
     * calling handler can wrap it in the standard JSON envelope.
     *
     * Possible return values:
     *   - `['done' => true, 'remaining' => 0]` — scan complete.
     *   - `['done' => false, 'remaining' => N, 'result' => '...', 'ip' => '...']` — in progress.
     *
     * @return array<string, mixed> Status array from WemoDriver::checkNextIp().
     */
    public function checkNext(): array
    {
        $nmapScan  = new NmapScan();
        $wemoModel = new Wemo();

        return WemoDriver::checkNextIp($nmapScan, $wemoModel);
    }
}

