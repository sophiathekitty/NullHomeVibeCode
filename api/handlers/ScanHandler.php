<?php
require_once __DIR__ . '/ApiHandler.php';
require_once __DIR__ . '/../../controllers/ScanController.php';
require_once __DIR__ . '/../../controllers/WemoController.php';

/**
 * ScanHandler — handles /api/scan/… requests.
 *
 * Routes:
 *   POST /api/scan/reset        → reset nmap table and run ping sweep
 *   POST /api/scan/next/wemo    → check next unchecked IP for a Wemo device
 *
 * The `next/{type}` pattern is intentionally open-ended so that additional
 * device types (e.g. nullhub) can be added by registering their controller
 * under a new `$type` branch without changing the route structure.
 */
class ScanHandler extends ApiHandler
{
    private ScanController  $scanController;
    private WemoController  $wemoController;

    /**
     * Constructor — instantiate the scan and device controllers.
     */
    public function __construct()
    {
        $this->scanController = new ScanController();
        $this->wemoController = new WemoController();
    }

    /**
     * Route the scan request to the appropriate controller method.
     *
     * URL segments received in $params (everything after "scan"):
     *   ['reset']        → POST /api/scan/reset
     *   ['next', 'wemo'] → POST /api/scan/next/wemo
     *
     * @param array  $params URL path segments after the "scan" resource key.
     * @param string $method HTTP request method.
     * @param array  $body   Decoded JSON request body.
     * @return void
     */
    public function handle(array $params, string $method, array $body): void
    {
        if ($method !== 'POST') {
            $this->methodNotAllowed();
            return;
        }

        $action = $params[0] ?? null;

        // POST /api/scan/reset
        if ($action === 'reset') {
            try {
                $queued = $this->scanController->reset();
                $this->ok(['queued' => $queued]);
            } catch (\RuntimeException $e) {
                $this->error('Host subnet not configured', 500);
            }
            return;
        }

        // POST /api/scan/next/{type}
        if ($action === 'next') {
            $type = $params[1] ?? null;

            if ($type === 'wemo') {
                $this->ok($this->wemoController->checkNext());
                return;
            }

            $this->notFound($type !== null ? "Unknown device type: $type" : 'Device type missing from scan/next request');
            return;
        }

        $this->notFound($action !== null ? "Unknown scan action: $action" : 'Scan action is required');
    }
}
