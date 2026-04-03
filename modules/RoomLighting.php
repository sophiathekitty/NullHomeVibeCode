<?php
require_once __DIR__ . '/../models/Room.php';

/**
 * RoomLighting — HTTP-unaware service for querying lighting state of a room and its neighbors.
 *
 * All state calculations derive from the updated_at column stored on
 * devices rows — no log table queries are performed.
 * All filtering (type = 'light', room scoping) happens at the SQL level.
 *
 * This class has no knowledge of HTTP. It may be called from controllers,
 * cron jobs, API handlers, and the automation engine without modification.
 */
class RoomLighting
{
    /**
     * Return true if the room has at least one device (type='light') currently on.
     *
     * @param Room $room The room to check.
     * @return bool
     */
    public function isBright(Room $room): bool
    {
        $count = (int) DB::query(
            'SELECT COUNT(*) FROM `devices`
              WHERE `room_id` = ? AND `type` = ? AND `state` = 1',
            [$room->id, 'light']
        )->fetchColumn();

        return $count > 0;
    }

    /**
     * Return how many seconds the room has been continuously in its current
     * state (bright or dark).
     *
     * Derived from MAX(updated_at) on devices where type='light' and
     * room_id = $room->id. Returns 0 if no devices are associated with the room
     * or if updated_at is NULL for all devices.
     *
     * @param Room $room The room to check.
     * @return int Seconds since the most recent state change among the room's devices.
     */
    public function secondsSinceStateChange(Room $room): int
    {
        $maxTs = DB::query(
            'SELECT MAX(`updated_at`) FROM `devices`
              WHERE `room_id` = ? AND `type` = ?',
            [$room->id, 'light']
        )->fetchColumn();

        if ($maxTs === null || $maxTs === false) {
            return 0;
        }

        return max(0, (int) (time() - strtotime($maxTs)));
    }

    /**
     * Return how many neighbor rooms are currently bright.
     *
     * Aggregates across all neighbor rooms at the SQL level.
     *
     * @param Room $room The room whose neighbors are checked.
     * @return int
     */
    public function brightNeighborCount(Room $room): int
    {
        return (int) DB::query(
            'SELECT COUNT(DISTINCT neighbor_room.id)
               FROM `rooms` AS neighbor_room
               INNER JOIN `room_neighbors` rn
                 ON (rn.room_id = neighbor_room.id OR rn.neighbor_id = neighbor_room.id)
               INNER JOIN `devices` d ON d.room_id = neighbor_room.id
              WHERE (rn.room_id = ? OR rn.neighbor_id = ?)
                AND neighbor_room.id != ?
                AND d.type = ?
                AND d.state = 1',
            [$room->id, $room->id, $room->id, 'light']
        )->fetchColumn();
    }

    /**
     * Return how many seconds the neighbor aggregate state has been stable.
     *
     * "Neighbor state" = whether any neighbor is currently bright.
     * Derived from MAX(updated_at) across all devices in all neighbor rooms.
     * Returns 0 if no neighbor devices have an updated_at recorded.
     *
     * @param Room $room The room whose neighbors are examined.
     * @return int
     */
    public function secondsSinceNeighborStateChange(Room $room): int
    {
        $maxTs = DB::query(
            'SELECT MAX(d.updated_at)
               FROM `devices` d
               INNER JOIN `room_neighbors` rn
                 ON (rn.room_id = d.room_id OR rn.neighbor_id = d.room_id)
              WHERE (rn.room_id = ? OR rn.neighbor_id = ?)
                AND d.room_id != ?
                AND d.type = ?',
            [$room->id, $room->id, $room->id, 'light']
        )->fetchColumn();

        if ($maxTs === null || $maxTs === false) {
            return 0;
        }

        return max(0, (int) (time() - strtotime($maxTs)));
    }

    /**
     * Return a state snapshot for a room, suitable for API responses and
     * automation engine consumption.
     *
     * @param Room $room The room to snapshot.
     * @return array{
     *   is_bright: bool,
     *   seconds_since_state_change: int,
     *   bright_neighbor_count: int,
     *   seconds_since_neighbor_state_change: int
     * }
     */
    public function getRoomState(Room $room): array
    {
        return [
            'is_bright'                           => $this->isBright($room),
            'seconds_since_state_change'          => $this->secondsSinceStateChange($room),
            'bright_neighbor_count'               => $this->brightNeighborCount($room),
            'seconds_since_neighbor_state_change' => $this->secondsSinceNeighborStateChange($room),
        ];
    }

    /**
     * Return state snapshots for each neighbor of the given room.
     *
     * @param Room $room The room whose neighbors are snapshotted.
     * @return array<int, array{room: Room, state: array<string, mixed>}>
     *   An array of pairs, each containing a 'room' key (Room instance) and a
     *   'state' key (the result of getRoomState() for that neighbor).
     */
    public function getNeighborStates(Room $room): array
    {
        $neighbors = $room->neighbors();
        $result    = [];

        foreach ($neighbors as $neighbor) {
            $result[] = [
                'room'  => $neighbor,
                'state' => $this->getRoomState($neighbor),
            ];
        }

        return $result;
    }
}
