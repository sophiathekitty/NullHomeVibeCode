<?php
require_once __DIR__ . '/ApiHandler.php';
require_once __DIR__ . '/../../controllers/LightsController.php';

/**
 * LightsHandler — handles /api/lights/… requests.
 *
 * Routes:
 *   GET    /api/lights            → list all lights
 *   GET    /api/lights/{id}       → get a single light
 *   POST   /api/lights            → create a light  (body: { name, location? })
 *   DELETE /api/lights/{id}       → delete a light
 *   POST   /api/lights/{id}/toggle     → toggle on/off
 *   POST   /api/lights/{id}/on         → turn on
 *   POST   /api/lights/{id}/off        → turn off
 *   POST   /api/lights/{id}/brightness → set brightness (body: { value: 0-100 })
 */
class LightsHandler extends ApiHandler {
    private LightsController $controller;

    public function __construct() {
        $this->controller = new LightsController();
    }

    public function handle(array $params, string $method, array $body): void {
        // $params[0] = optional light id, $params[1] = optional action
        $id     = isset($params[0]) && is_numeric($params[0]) ? (int) $params[0] : null;
        $action = $params[1] ?? null;

        // Collection-level routes (no id)
        if ($id === null) {
            if ($method === 'GET') {
                $this->ok($this->controller->getAll());
                return;
            }
            if ($method === 'POST') {
                $name    = trim($body['name'] ?? '');
                $type    = isset($body['type'])    ? trim($body['type'])    : 'light';
                $subtype = isset($body['subtype']) ? trim($body['subtype']) : null;
                $roomId  = isset($body['room_id']) && is_numeric($body['room_id'])
                    ? (int) $body['room_id']
                    : null;
                if ($name === '') {
                    $this->error('name is required');
                    return;
                }
                $this->ok($this->controller->create($name, $type ?: 'light', $subtype ?: null, $roomId));
                return;
            }
            $this->methodNotAllowed();
            return;
        }

        // Item-level routes (with id)
        if ($action === null) {
            if ($method === 'GET') {
                $light = $this->controller->getById($id);
                if ($light === null) {
                    $this->notFound("Light $id not found");
                    return;
                }
                $this->ok($light);
                return;
            }
            if ($method === 'DELETE') {
                if (!$this->controller->delete($id)) {
                    $this->notFound("Light $id not found");
                    return;
                }
                $this->ok();
                return;
            }
            $this->methodNotAllowed();
            return;
        }

        // Action routes (id + action)
        if ($method !== 'POST') {
            $this->methodNotAllowed();
            return;
        }

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

        if ($result === null && in_array($action, ['toggle', 'on', 'off', 'brightness'], true)) {
            $this->notFound("Light $id not found");
            return;
        }

        if ($result === null) {
            $this->notFound("Unknown action: $action");
            return;
        }

        $this->ok($result);
    }
}
