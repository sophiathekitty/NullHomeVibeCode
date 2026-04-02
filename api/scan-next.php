<?php
/**
 * POST /api/scan-next.php — process the next unchecked IP in the scan queue.
 *
 * Returns JSON:
 *   { "done": true,  "remaining": 0 }                          — scan complete
 *   { "done": false, "remaining": N, "result": "...", "ip": "..." } — in progress
 *
 * Possible result values: "found_wemo", "known_wemo", "not_wemo", "no_ports".
 */

$configFile = __DIR__ . '/../config.php';
if (!file_exists($configFile)) {
    http_response_code(503);
    echo json_encode(['error' => 'NullHome is not configured yet.']);
    exit;
}
require_once $configFile;
require_once __DIR__ . '/../modules/db/DB.php';
require_once APP_ROOT . '/controllers/WemoController.php';

header('Content-Type: application/json; charset=utf-8');

$controller = new WemoController();
$controller->checkNext();
