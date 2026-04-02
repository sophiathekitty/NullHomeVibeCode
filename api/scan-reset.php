<?php
/**
 * POST /api/scan-reset.php — reset the nmap scan table and start a fresh ping sweep.
 *
 * Returns JSON:
 *   { "queued": <count> }          on success
 *   { "error": "..." }             on failure (HTTP 500)
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
$controller->resetScan();
