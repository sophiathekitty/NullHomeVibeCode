<?php
require_once __DIR__ . '/Controller.php';
require_once APP_ROOT . '/models/NmapScan.php';
require_once APP_ROOT . '/models/Wemo.php';
require_once APP_ROOT . '/modules/network/NetworkModule.php';
require_once APP_ROOT . '/modules/devices/WemoDriver.php';

/**
 * WemoController — coordinates the Wemo device scan workflow.
 *
 * Provides two methods consumed by the frontend scan loop:
 *   - resetScan()  — clears stale nmap records and starts a fresh ping sweep.
 *   - checkNext()  — processes one IP and returns its classification result.
 *
 * Both methods output JSON directly. All business logic is delegated to
 * WemoDriver and the model layer; this class only reads HTTP inputs and
 * translates results into JSON responses.
 */
class WemoController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Reset the nmap scan table and run a fresh ping sweep to populate it.
     *
     * Calls NmapScan::resetScan() to clear non-known records, then calls
     * NetworkModule::discoverIps() to insert newly discovered live IPs.
     *
     * Outputs JSON:
     *   - Success: `{ "queued": <count> }`
     *   - Failure (subnet not configured): HTTP 500 + `{ "error": "Host subnet not configured" }`
     *
     * @return void
     */
    public function resetScan(): void
    {
        $nmapScan = new NmapScan();
        $nmapScan->resetScan();

        try {
            $queued = NetworkModule::discoverIps($nmapScan);
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Host subnet not configured']);
            return;
        }

        echo json_encode(['queued' => $queued]);
    }

    /**
     * Process the next unchecked IP in the nmap scan queue.
     *
     * Instantiates NmapScan and Wemo models, delegates to
     * WemoDriver::checkNextIp(), and outputs the result as JSON.
     *
     * Possible responses:
     *   - `{ "done": true, "remaining": 0 }` — scan complete.
     *   - `{ "done": false, "remaining": N, "result": "...", "ip": "..." }` — in progress.
     *
     * @return void
     */
    public function checkNext(): void
    {
        $nmapScan  = new NmapScan();
        $wemoModel = new Wemo();

        $result = WemoDriver::checkNextIp($nmapScan, $wemoModel);

        echo json_encode($result);
    }
}
