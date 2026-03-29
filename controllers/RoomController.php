<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/Room.php';
require_once __DIR__ . '/../models/RoomNeighbor.php';
require_once __DIR__ . '/../modules/RoomLighting.php';

/**
 * RoomController — business logic for rooms, their lights, and neighbor relationships.
 *
 * All JSON API responses are handled by the handler layer. This controller
 * returns plain arrays or scalar values and never accesses request globals.
 *
 * RoomLighting is injected at construction time so the controller remains
 * testable without live HTTP context.
 */
class RoomController extends Controller
{
    private RoomLighting $lighting;

    /**
     * Constructor — inject the RoomLighting service.
     *
     * @param RoomLighting $lighting The lighting state service.
     */
    public function __construct(RoomLighting $lighting)
    {
        parent::__construct();
        $this->lighting = $lighting;
    }

    // ── Room CRUD ─────────────────────────────────────────────────────────────

    /**
     * Return all rooms, each merged with its current lighting state.
     *
     * @return array<int, array<string, mixed>>
     */
    public function index(): array
    {
        $rooms = Room::allRooms();
        return array_map(fn(Room $r) => $this->roomWithState($r), $rooms);
    }

    /**
     * Return a single room with its current lighting state, or null if not found.
     *
     * @param int $id The room's primary key.
     * @return array<string, mixed>|null
     */
    public function show(int $id): ?array
    {
        $room = Room::findById($id);
        if ($room === null) {
            return null;
        }
        return $this->roomWithState($room);
    }

    /**
     * Create a new room and return it with its initial lighting state.
     *
     * @param string      $name        Machine-readable room name (unique).
     * @param string|null $displayName Optional human-readable label.
     * @return array<string, mixed>
     */
    public function store(string $name, ?string $displayName = null): array
    {
        $model = new Room();
        $newId = $model->insert([
            'name'         => $name,
            'display_name' => $displayName,
        ]);
        $room = Room::findById($newId);
        return $this->roomWithState($room);
    }

    /**
     * Update an existing room and return it with its current lighting state.
     * Returns null if the room does not exist.
     *
     * @param int                  $id   The room's primary key.
     * @param array<string, mixed> $data Column => value pairs to update.
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $data): ?array
    {
        $room = Room::findById($id);
        if ($room === null) {
            return null;
        }
        if (!empty($data)) {
            (new Room())->update($id, $data);
        }
        $updated = Room::findById($id);
        return $this->roomWithState($updated);
    }

    /**
     * Delete a room by id.
     *
     * @param int $id The room's primary key.
     * @return bool True if the room existed and was deleted, false otherwise.
     */
    public function destroy(int $id): bool
    {
        if (Room::findById($id) === null) {
            return false;
        }
        (new Room())->delete($id);
        return true;
    }

    // ── Sub-resource routes ───────────────────────────────────────────────────

    /**
     * Return all lights (type='light') belonging to the given room.
     *
     * Each light row is augmented with an is_on boolean derived from state.
     * Returns null if the room does not exist.
     *
     * @param int $id The room's primary key.
     * @return array<int, array<string, mixed>>|null
     */
    public function lights(int $id): ?array
    {
        $room = Room::findById($id);
        if ($room === null) {
            return null;
        }
        $rows = $room->lights();
        return array_map(function (array $light): array {
            $light['is_on'] = (bool) $light['state'];
            return $light;
        }, $rows);
    }

    /**
     * Return each neighbor of a room with its current lighting state.
     *
     * Returns null if the room does not exist.
     *
     * @param int $id The room's primary key.
     * @return array<int, array<string, mixed>>|null
     */
    public function neighbors(int $id): ?array
    {
        $room = Room::findById($id);
        if ($room === null) {
            return null;
        }
        $pairs = $this->lighting->getNeighborStates($room);
        return array_map(function (array $pair): array {
            /** @var Room $r */
            $r = $pair['room'];
            $s = $pair['state'];
            return [
                'id'                        => $r->id,
                'name'                      => $r->name,
                'display_name'              => $r->display_name,
                'is_bright'                 => $s['is_bright'],
                'seconds_since_state_change' => $s['seconds_since_state_change'],
            ];
        }, $pairs);
    }

    /**
     * Link two rooms as neighbors.
     *
     * Returns an array with room_id and neighbor_id on success, or null if
     * either room does not exist.
     *
     * @param int $id         The first room's primary key.
     * @param int $neighborId The second room's primary key.
     * @return array<string, int>|null
     */
    public function linkNeighbor(int $id, int $neighborId): ?array
    {
        if (Room::findById($id) === null || Room::findById($neighborId) === null) {
            return null;
        }
        $link = RoomNeighbor::link($id, $neighborId);
        return ['room_id' => $link->room_id, 'neighbor_id' => $link->neighbor_id];
    }

    /**
     * Remove the neighbor relationship between two rooms.
     *
     * @param int $id         One room's primary key.
     * @param int $neighborId The other room's primary key.
     * @return bool True if the relationship was removed, false if it did not exist.
     */
    public function unlinkNeighbor(int $id, int $neighborId): bool
    {
        return RoomNeighbor::unlink($id, $neighborId);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Merge a room's scalar fields with its current RoomLighting state.
     *
     * @param Room $room The hydrated room instance.
     * @return array<string, mixed>
     */
    private function roomWithState(Room $room): array
    {
        return array_merge(
            [
                'id'           => $room->id,
                'name'         => $room->name,
                'display_name' => $room->display_name,
            ],
            $this->lighting->getRoomState($room)
        );
    }
}
