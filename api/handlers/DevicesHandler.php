<?php
require_once __DIR__ . '/ApiHandler.php';
require_once __DIR__ . '/../../controllers/DeviceController.php';

/**
 * DevicesHandler — handles /api/devices/… requests.
 *
 * Routes:
 *   GET    /api/devices                   → list all devices
 *   GET    /api/devices/{id}              → get a single device
 *   POST   /api/devices                   → create a device
 *                                           body: { name, type?, subtype?, color?, room_id?, brightness? }
 *   DELETE /api/devices/{id}              → delete a device
 *   POST   /api/devices/{id}/toggle       → toggle on/off
 *   POST   /api/devices/{id}/on           → turn on
 *   POST   /api/devices/{id}/off          → turn off
 *   POST   /api/devices/{id}/brightness   → set brightness (body: { value: 0-100 })
 */
class DevicesHandler extends ApiHandler
{
    /** @var DeviceController */
    private DeviceController $controller;

    /**
     * Constructor — instantiates the underlying controller.
     */
    public function __construct()
    {
        $this->controller = new DeviceController();
    }

    /**
     * Dispatch the incoming request to the appropriate controller method.
     *
     * @param array  $params URL path segments after the handler key.
     *                       e.g. /api/devices/1/toggle → ["1", "toggle"]
     * @param string $method HTTP method (GET, POST, DELETE, etc.)
     * @param array  $body   Decoded JSON request body (may be empty).
     * @return void
     */
    public function handle(array $params, string $method, array $body): void
    {
        // $params[0] = optional device id, $params[1] = optional action
        $id     = isset($params[0]) && is_numeric($params[0]) ? (int) $params[0] : null;
        $action = $params[1] ?? null;

        // ── Collection-level routes (no id) ───────────────────────────────────
        if ($id === null) {
            if ($method === 'GET') {
                $this->ok($this->controller->getAll());
                return;
            }

            if ($method === 'POST') {
                $name       = trim($body['name'] ?? '');
                $type       = isset($body['type'])       ? trim($body['type'])       : 'light';
                $subtype    = isset($body['subtype'])    ? trim($body['subtype'])    : null;
                $color      = isset($body['color'])      ? trim($body['color'])      : null;
                $roomId     = isset($body['room_id']) && is_numeric($body['room_id'])
                    ? (int) $body['room_id']
                    : null;
                $brightness = isset($body['brightness']) && is_numeric($body['brightness'])
                    ? (int) $body['brightness']
                    : null;

                if ($name === '') {
                    $this->error('name is required');
                    return;
                }

                $this->ok($this->controller->create(
                    $name,
                    $type ?: 'light',
                    $subtype ?: null,
                    $color ?: null,
                    $roomId,
                    $brightness
                ));
                return;
            }

            $this->methodNotAllowed();
            return;
        }

        // ── Item-level routes (with id, no action) ────────────────────────────
        if ($action === null) {
            if ($method === 'GET') {
                $device = $this->controller->getById($id);
                if ($device === null) {
                    $this->notFound("Device $id not found");
                    return;
                }
                $this->ok($device);
                return;
            }

            if ($method === 'DELETE') {
                if (!$this->controller->delete($id)) {
                    $this->notFound("Device $id not found");
                    return;
                }
                $this->ok();
                return;
            }

            $this->methodNotAllowed();
            return;
        }

        // ── Action routes (id + action) ───────────────────────────────────────
        if ($method !== 'POST') {
            $this->methodNotAllowed();
            return;
        }

        $knownActions = ['toggle', 'on', 'off', 'brightness'];

        $result = match ($action) {
            'toggle'     => $this->controller->toggle($id),
            'on'         => $this->controller->turnOn($id),
            'off'        => $this->controller->turnOff($id),
            'brightness' => $this->controller->setBrightness(
                                $id,
                                (int) ($body['value'] ?? 100)
                            ),
            default      => null,
        };

        if ($result === null && in_array($action, $knownActions, true)) {
            $this->notFound("Device $id not found");
            return;
        }

        if ($result === null) {
            $this->notFound("Unknown action: $action");
            return;
        }

        $this->ok($result);
    }
}
