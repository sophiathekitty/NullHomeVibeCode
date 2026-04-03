<?php
require_once __DIR__ . '/ApiHandler.php';
require_once __DIR__ . '/../../modules/RoomLighting.php';
require_once __DIR__ . '/../../controllers/RoomController.php';

/**
 * RoomsHandler — handles /api/rooms/… requests.
 *
 * Routes:
 *   GET    /api/rooms                              → list all rooms with state
 *   POST   /api/rooms                              → create a room
 *   GET    /api/rooms/{id}                         → single room with state
 *   PUT    /api/rooms/{id}                         → update a room
 *   DELETE /api/rooms/{id}                         → delete a room
 *   GET    /api/rooms/{id}/devices               → devices in the room
 *   GET    /api/rooms/{id}/neighbors               → neighbors with state
 *   POST   /api/rooms/{id}/neighbors               → add a neighbor relationship
 *   DELETE /api/rooms/{id}/neighbors/{neighborId}  → remove a neighbor relationship
 */
class RoomsHandler extends ApiHandler
{
    private RoomController $controller;

    /**
     * Constructor — wires up the RoomLighting service and RoomController.
     */
    public function __construct()
    {
        $this->controller = new RoomController(new RoomLighting());
    }

    /**
     * Route the request to the appropriate controller method.
     *
     * @param array  $params URL path segments after "rooms".
     * @param string $method HTTP method.
     * @param array  $body   Decoded JSON request body.
     * @return void
     */
    public function handle(array $params, string $method, array $body): void
    {
        // $params[0] = optional room id
        // $params[1] = optional sub-resource ("devices" or "neighbors")
        // $params[2] = optional sub-resource id (neighbor id for DELETE)
        $id      = isset($params[0]) && is_numeric($params[0]) ? (int) $params[0] : null;
        $sub     = $params[1] ?? null;
        $subId   = isset($params[2]) && is_numeric($params[2]) ? (int) $params[2] : null;

        // ── Collection-level routes (no room id) ──────────────────────────────
        if ($id === null) {
            if ($method === 'GET') {
                $this->ok($this->controller->index());
                return;
            }
            if ($method === 'POST') {
                $name = trim($body['name'] ?? '');
                if ($name === '') {
                    $this->error('name is required');
                    return;
                }
                $displayName = isset($body['display_name']) ? trim($body['display_name']) : null;
                $this->ok($this->controller->store($name, $displayName ?: null));
                return;
            }
            $this->methodNotAllowed();
            return;
        }

        // ── Sub-resource: devices ─────────────────────────────────────────────
        if ($sub === 'devices') {
            if ($method !== 'GET') {
                $this->methodNotAllowed();
                return;
            }
            $devices = $this->controller->devices($id);
            if ($devices === null) {
                $this->notFound("Room $id not found");
                return;
            }
            $this->ok($devices);
            return;
        }

        // ── Sub-resource: neighbors ───────────────────────────────────────────
        if ($sub === 'neighbors') {
            // DELETE /api/rooms/{id}/neighbors/{neighborId}
            if ($method === 'DELETE') {
                if ($subId === null) {
                    $this->error('neighborId is required in the path');
                    return;
                }
                if (!$this->controller->unlinkNeighbor($id, $subId)) {
                    $this->notFound("Neighbor relationship not found");
                    return;
                }
                $this->ok();
                return;
            }

            // GET /api/rooms/{id}/neighbors
            if ($method === 'GET') {
                $result = $this->controller->neighbors($id);
                if ($result === null) {
                    $this->notFound("Room $id not found");
                    return;
                }
                $this->ok($result);
                return;
            }

            // POST /api/rooms/{id}/neighbors
            if ($method === 'POST') {
                $neighborId = isset($body['neighbor_id']) && is_numeric($body['neighbor_id'])
                    ? (int) $body['neighbor_id']
                    : null;
                if ($neighborId === null) {
                    $this->error('neighbor_id is required');
                    return;
                }
                $result = $this->controller->linkNeighbor($id, $neighborId);
                if ($result === null) {
                    $this->notFound('One or both rooms not found');
                    return;
                }
                $this->ok($result);
                return;
            }

            $this->methodNotAllowed();
            return;
        }

        // ── Item-level routes (room id, no sub-resource) ──────────────────────
        if ($method === 'GET') {
            $room = $this->controller->show($id);
            if ($room === null) {
                $this->notFound("Room $id not found");
                return;
            }
            $this->ok($room);
            return;
        }

        if ($method === 'PUT') {
            $data = array_filter([
                'name'         => $body['name'] ?? null,
                'display_name' => $body['display_name'] ?? null,
            ], fn($v) => $v !== null);
            $result = $this->controller->update($id, $data);
            if ($result === null) {
                $this->notFound("Room $id not found");
                return;
            }
            $this->ok($result);
            return;
        }

        if ($method === 'DELETE') {
            if (!$this->controller->destroy($id)) {
                $this->notFound("Room $id not found");
                return;
            }
            $this->ok();
            return;
        }

        $this->methodNotAllowed();
    }
}
