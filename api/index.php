<?php
/**
 * API entry point — routes /api/{resource}/{id?}/{action?}/… requests
 * to the appropriate handler class.
 *
 * URL parsing:
 *   /api/devices/1/toggle  →  handler = "devices", params = ["1", "toggle"]
 *   /api/devices           →  handler = "devices", params = []
 *
 * All responses follow the JSON envelope:
 *   { "success": bool, "data": mixed, "error": string|null }
 */

// Bootstrap
$configFile = __DIR__ . '/../config.php';
if (!file_exists($configFile)) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'data'    => null,
        'error'   => 'NullHome is not configured yet. Please run the install wizard.',
    ]);
    exit;
}
require_once $configFile;
require_once __DIR__ . '/../modules/db/DB.php';
require_once __DIR__ . '/../modules/Debug.php';
require_once __DIR__ . '/handlers/ApiHandler.php';

header('Content-Type: application/json; charset=utf-8');

// Parse the request path into a parameter array.
// Apache/Nginx should rewrite /api/… to /api/index.php.
$requestUri  = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName  = dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php');
$path        = parse_url($requestUri, PHP_URL_PATH);
$path        = preg_replace('#^' . preg_quote(rtrim($scriptName, '/'), '#') . '#', '', $path);
$path        = trim($path, '/');

// Split path into segments and strip a leading "api" segment if present
$segments = $path !== '' ? explode('/', $path) : [];
if (isset($segments[0]) && $segments[0] === 'api') {
    array_shift($segments);
}

// $segments[0] = resource name, rest = params passed to the handler
$resource = array_shift($segments) ?? '';
$params   = $segments;

// HTTP method and decoded body
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$rawBody = file_get_contents('php://input');
$body    = json_decode($rawBody, true) ?? [];

// Enable debug output collection if ?debug=1 is present in the query string.
if (!empty($_GET['debug']) && $_GET['debug'] === '1') {
    Debug::enable();
}

// Handler registry — maps resource name to handler class file + class name
$handlers = [
    'devices'  => __DIR__ . '/handlers/DevicesHandler.php',
    'settings' => __DIR__ . '/handlers/SettingsHandler.php',
    'rooms'    => __DIR__ . '/handlers/RoomsHandler.php',
    'scan'     => __DIR__ . '/handlers/ScanHandler.php',
];

if (!isset($handlers[$resource])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'data' => null, 'error' => "Unknown resource: $resource"]);
    exit;
}

require_once $handlers[$resource];

$className = ucfirst($resource) . 'Handler';
if (!class_exists($className)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'data' => null, 'error' => "Handler class $className not found"]);
    exit;
}

$handler = new $className();
$handler->handle($params, $method, $body);
