<?php
require_once __DIR__ . '/Model.php';

/**
 * RoomNeighbor — represents an undirected neighbor relationship between two rooms.
 *
 * Table: room_neighbors
 *
 * The relationship is stored with the lower room id in room_id and the higher
 * in neighbor_id so each pair is recorded exactly once and a unique index on
 * (room_id, neighbor_id) prevents duplicates at the database level.
 *
 * Enforcement at the model level:
 *   - room_id < neighbor_id is guaranteed by link() before any INSERT.
 *   - Self-referencing rows (room_id = neighbor_id) are rejected by link().
 */
class RoomNeighbor extends Model
{
    protected static string $table = 'room_neighbors';

    /** @var int Auto-increment primary key. */
    public int $id = 0;

    /** @var int The lower of the two room ids in the pair. */
    public int $room_id = 0;

    /** @var int The higher of the two room ids in the pair. */
    public int $neighbor_id = 0;

    // ── Model interface ───────────────────────────────────────────────────────

    /**
     * Return the table name for this model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return 'room_neighbors';
    }

    /**
     * Return the ordered array of field definitions for this model.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFields(): array
    {
        return [
            [
                'name'     => 'room_id',
                'type'     => 'INT UNSIGNED',
                'length'   => null,
                'nullable' => false,
                'default'  => null,
            ],
            [
                'name'     => 'neighbor_id',
                'type'     => 'INT UNSIGNED',
                'length'   => null,
                'nullable' => false,
                'default'  => null,
            ],
        ];
    }

    // ── Static factory ────────────────────────────────────────────────────────

    /**
     * Create a hydrated RoomNeighbor instance from a database row array.
     *
     * @param array<string, mixed> $row A raw database row.
     * @return static
     */
    private static function fromRow(array $row): static
    {
        $instance              = new static();
        $instance->id          = (int) $row['id'];
        $instance->room_id     = (int) $row['room_id'];
        $instance->neighbor_id = (int) $row['neighbor_id'];
        return $instance;
    }

    // ── Canonical pair ordering ───────────────────────────────────────────────

    /**
     * Return the canonical (lower, upper) ordering of two room ids.
     *
     * @param int $roomIdA One room id.
     * @param int $roomIdB The other room id.
     * @return array{0: int, 1: int} [lower, upper].
     */
    private static function ordered(int $roomIdA, int $roomIdB): array
    {
        return $roomIdA < $roomIdB ? [$roomIdA, $roomIdB] : [$roomIdB, $roomIdA];
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Link two rooms as neighbors. Stores the pair with the lower id as room_id
     * to enforce uniqueness regardless of argument order.
     *
     * If the pair is already linked the existing record is returned without
     * creating a duplicate.
     *
     * @param int $roomIdA One room's primary key.
     * @param int $roomIdB The other room's primary key.
     * @return static The new or existing RoomNeighbor record.
     * @throws \InvalidArgumentException If both ids are the same.
     */
    public static function link(int $roomIdA, int $roomIdB): static
    {
        if ($roomIdA === $roomIdB) {
            throw new \InvalidArgumentException('A room cannot be its own neighbor.');
        }

        [$lower, $upper] = self::ordered($roomIdA, $roomIdB);

        // Return the existing row if the pair is already linked.
        $existing = static::query()
            ->where('room_id', $lower)
            ->where('neighbor_id', $upper)
            ->first();

        if ($existing) {
            return static::fromRow($existing);
        }

        $model = new static();
        $newId = $model->insert(['room_id' => $lower, 'neighbor_id' => $upper]);

        return static::fromRow(['id' => $newId, 'room_id' => $lower, 'neighbor_id' => $upper]);
    }

    /**
     * Return true if the two rooms are already linked as neighbors.
     *
     * Checks both orderings so argument order does not matter.
     *
     * @param int $roomIdA One room's primary key.
     * @param int $roomIdB The other room's primary key.
     * @return bool
     */
    public static function areNeighbors(int $roomIdA, int $roomIdB): bool
    {
        [$lower, $upper] = self::ordered($roomIdA, $roomIdB);

        return static::query()
            ->where('room_id', $lower)
            ->where('neighbor_id', $upper)
            ->count() > 0;
    }

    /**
     * Remove the neighbor relationship between two rooms.
     *
     * @param int $roomIdA One room's primary key.
     * @param int $roomIdB The other room's primary key.
     * @return bool True if a row was deleted, false if the pair was not linked.
     */
    public static function unlink(int $roomIdA, int $roomIdB): bool
    {
        [$lower, $upper] = self::ordered($roomIdA, $roomIdB);

        $deleted = static::query()
            ->where('room_id', $lower)
            ->where('neighbor_id', $upper)
            ->delete();

        return $deleted > 0;
    }
}
