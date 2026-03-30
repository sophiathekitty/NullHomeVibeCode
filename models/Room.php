<?php
require_once __DIR__ . '/Model.php';

/**
 * Room — represents a physical room or area in the home.
 *
 * Table: rooms
 *
 * Instances may be constructed via the static factory methods
 * fromRow(), findById(), and allRooms() to get hydrated objects
 * that can be passed to RoomLighting and other modules.
 */
class Room extends Model
{
    protected static string $table = 'rooms';

    /** @var int Auto-increment primary key. */
    public int $id = 0;

    /** @var string Unique machine-readable name, e.g. "basement". */
    public string $name = '';

    /** @var string|null Human-readable label, e.g. "Basement". */
    public ?string $display_name = null;

    /** @var string Creation timestamp. */
    public string $created_at = '';

    // ── Model interface ───────────────────────────────────────────────────────

    /**
     * Return the table name for this model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return 'rooms';
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
                'name'     => 'name',
                'type'     => 'VARCHAR',
                'length'   => 100,
                'nullable' => false,
                'default'  => null,
            ],
            [
                'name'     => 'display_name',
                'type'     => 'VARCHAR',
                'length'   => 100,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'created_at',
                'type'     => 'TIMESTAMP',
                'length'   => null,
                'nullable' => false,
                'default'  => 'CURRENT_TIMESTAMP',
            ],
        ];
    }

    // ── Static factory methods ────────────────────────────────────────────────

    /**
     * Create a hydrated Room instance from a database row array.
     *
     * @param array<string, mixed> $row A raw database row for a rooms record.
     * @return static
     */
    public static function fromRow(array $row): static
    {
        $instance               = new static();
        $instance->id           = (int) $row['id'];
        $instance->name         = (string) $row['name'];
        $instance->display_name = isset($row['display_name']) ? (string) $row['display_name'] : null;
        $instance->created_at   = (string) ($row['created_at'] ?? '');
        return $instance;
    }

    /**
     * Find a single Room by its primary key.
     *
     * @param int $id The room's primary key.
     * @return static|null The hydrated Room, or null if not found.
     */
    public static function findById(int $id): ?static
    {
        $row = static::query()->where('id', $id)->first();
        return $row ? static::fromRow($row) : null;
    }

    /**
     * Return all rooms as an array of hydrated Room instances.
     *
     * @return array<int, static>
     */
    public static function allRooms(): array
    {
        $rows = static::query()->get();
        return array_map(static fn(array $r) => static::fromRow($r), $rows);
    }

    // ── Relationship helpers ──────────────────────────────────────────────────

    /**
     * Return all lights belonging to this room.
     *
     * Filters at the SQL level: room_id = $this->id AND type = 'light'.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lights(): array
    {
        require_once __DIR__ . '/LightsModel.php';
        return LightsModel::query()
            ->where('room_id', $this->id)
            ->where('type', 'light')
            ->get();
    }

    /**
     * Return all neighboring rooms as hydrated Room instances.
     *
     * Queries via the room_neighbors join table. The relationship is
     * undirected: either column of the pair may contain this room's id.
     *
     * @return array<int, static>
     */
    public function neighbors(): array
    {
        $stmt = DB::connection()->prepare(
            'SELECT r.* FROM `rooms` r
             INNER JOIN `room_neighbors` rn
               ON (rn.room_id = r.id OR rn.neighbor_id = r.id)
             WHERE (rn.room_id = ? OR rn.neighbor_id = ?)
               AND r.id != ?'
        );
        $stmt->execute([$this->id, $this->id, $this->id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn(array $r) => static::fromRow($r), $rows);
    }
}
