<?php
require_once __DIR__ . '/Model.php';

/**
 * Device — represents a controllable smart home device.
 *
 * Table: devices
 *
 * A device is any controllable thing in the home: a ceiling light, a fan,
 * a rice cooker, etc. Wemo and Tuya hardware records will reference a
 * device_id via foreign key in their own tables.
 *
 * Instances are hydrated via the static factory methods fromRow(),
 * findById(), findAll(), findByRoom(), and findByType().
 */
class Device extends Model
{
    protected static string $table = 'devices';

    // ── Allowed subtype values by type ────────────────────────────────────────

    /**
     * Allowed subtype values keyed by device type.
     *
     * @var array<string, list<string>>
     */
    public const ALLOWED_SUBTYPES = [
        'light'  => ['ambient', 'ceiling', 'lamp', 'led', 'mood', 'plug', 'star', 'bubbles', 'fan', 'painting', 'rice-cooker'],
        'device' => ['fan', 'rice-cooker'],
    ];

    // ── Properties ────────────────────────────────────────────────────────────

    /** @var int Auto-increment primary key. */
    protected int $id = 0;

    /** @var string Human-readable device name. */
    protected string $name = '';

    /** @var string Device type: 'light' or 'device'. */
    protected string $type = 'light';

    /** @var string|null Functional subtype, e.g. 'ceiling', 'fan'. Nullable. */
    protected ?string $subtype = null;

    /** @var string|null Hex colour string, e.g. '#ff8800'. Only meaningful for type = light. */
    protected ?string $color = null;

    /** @var int|null FK to rooms.id. Nullable; no FK constraint until rooms table is established. */
    protected ?int $roomId = null;

    /** @var int|null Brightness 0–100. Only meaningful for type = light. */
    protected ?int $brightness = null;

    /** @var bool Device state: true = on, false = off. */
    protected bool $state = false;

    /** @var string Creation timestamp. */
    protected string $createdAt = '';

    /** @var string Last-updated timestamp. */
    protected string $updatedAt = '';

    // ── Model interface ───────────────────────────────────────────────────────

    /**
     * Return the table name for this model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return 'devices';
    }

    /**
     * Return the ordered array of field definitions for this model.
     *
     * The `type` column is stored as VARCHAR(10) rather than an ENUM because
     * DB::sync does not support ENUM lengths; type values are validated in PHP.
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
                'name'     => 'type',
                'type'     => 'VARCHAR',
                'length'   => 10,
                'nullable' => false,
                'default'  => 'light',
            ],
            [
                'name'     => 'subtype',
                'type'     => 'VARCHAR',
                'length'   => 50,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'color',
                'type'     => 'VARCHAR',
                'length'   => 7,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'room_id',
                'type'     => 'INT UNSIGNED',
                'length'   => null,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'brightness',
                'type'     => 'TINYINT UNSIGNED',
                'length'   => null,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'state',
                'type'     => 'TINYINT',
                'length'   => 1,
                'nullable' => false,
                'default'  => '0',
            ],
            [
                'name'     => 'created_at',
                'type'     => 'TIMESTAMP',
                'length'   => null,
                'nullable' => false,
                'default'  => 'CURRENT_TIMESTAMP',
            ],
            [
                'name'     => 'updated_at',
                'type'     => 'TIMESTAMP',
                'length'   => null,
                'nullable' => false,
                'default'  => 'CURRENT_TIMESTAMP',
            ],
        ];
    }

    // ── Static factory methods ────────────────────────────────────────────────

    /**
     * Create a hydrated Device instance from a raw database row.
     *
     * @param array<string, mixed> $row A raw row from the devices table.
     * @return static
     */
    public static function fromRow(array $row): static
    {
        $instance             = new static();
        $instance->id         = (int)    $row['id'];
        $instance->name       = (string) $row['name'];
        $instance->type       = (string) $row['type'];
        $instance->subtype    = isset($row['subtype'])    ? (string) $row['subtype']    : null;
        $instance->color      = isset($row['color'])      ? (string) $row['color']      : null;
        $instance->roomId     = isset($row['room_id'])    ? (int)    $row['room_id']    : null;
        $instance->brightness = isset($row['brightness']) ? (int)    $row['brightness'] : null;
        $instance->state      = (bool) $row['state'];
        $instance->createdAt  = (string) ($row['created_at'] ?? '');
        $instance->updatedAt  = (string) ($row['updated_at'] ?? '');
        return $instance;
    }

    /**
     * Find a single Device by its primary key.
     *
     * @param int $id The device's primary key.
     * @return static|null The hydrated Device instance, or null if not found.
     */
    public static function findById(int $id): ?static
    {
        $row = static::query()->where('id', $id)->first();
        return $row ? static::fromRow($row) : null;
    }

    /**
     * Return all devices as an array of hydrated Device instances.
     *
     * @return array<int, static>
     */
    public static function findAll(): array
    {
        $rows = static::query()->get();
        return array_map(static fn(array $r) => static::fromRow($r), $rows);
    }

    /**
     * Return all devices assigned to a given room.
     *
     * @param int $roomId The room's primary key.
     * @return array<int, static>
     */
    public static function findByRoom(int $roomId): array
    {
        $rows = static::query()->where('room_id', $roomId)->get();
        return array_map(static fn(array $r) => static::fromRow($r), $rows);
    }

    /**
     * Return all devices of a given type.
     *
     * @param string $type One of: 'light', 'device'.
     * @return array<int, static>
     */
    public static function findByType(string $type): array
    {
        $rows = static::query()->where('type', $type)->get();
        return array_map(static fn(array $r) => static::fromRow($r), $rows);
    }

    // ── Persistence methods ───────────────────────────────────────────────────

    /**
     * Persist this instance to the database.
     *
     * Performs an INSERT when id is 0 (new record) or an UPDATE when id is
     * non-zero (existing record). Sets updated_at on every write.
     *
     * @return bool True on success.
     * @throws \PDOException If the database query fails.
     */
    public function save(): bool
    {
        $now  = date('Y-m-d H:i:s');
        $data = [
            'name'       => $this->name,
            'type'       => $this->type,
            'subtype'    => $this->subtype,
            'color'      => $this->color,
            'room_id'    => $this->roomId,
            'brightness' => $this->brightness,
            'state'      => $this->state ? 1 : 0,
            'updated_at' => $now,
        ];

        if ($this->id === 0) {
            $this->id = $this->insert($data);
        } else {
            $this->update($this->id, $data);
        }

        return true;
    }

    /**
     * Delete this device from the database.
     *
     * Making $id optional (default 0) preserves compatibility with the parent
     * Model::delete(int $id): bool signature while supporting instance-based
     * deletion via $this->id.
     *
     * @param int $id Ignored by callers; kept for parent signature compatibility.
     * @return bool True if the row was deleted, false if the device has no id.
     * @throws \PDOException If the database query fails.
     */
    public function delete(int $id = 0): bool
    {
        if ($this->id === 0) {
            return false;
        }
        $result   = parent::delete($this->id);
        $this->id = 0;
        return $result;
    }

    /**
     * Update only the state column for this device.
     *
     * @param bool $state True = on, false = off.
     * @return bool True on success.
     * @throws \PDOException If the database query fails.
     */
    public function setState(bool $state): bool
    {
        $this->state = $state;
        $this->update($this->id, [
            'state'      => $state ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    /**
     * Update only the brightness column for this device.
     *
     * @param int $brightness Value between 0 and 100 inclusive.
     * @return bool True on success.
     * @throws \InvalidArgumentException If brightness is outside 0–100.
     * @throws \PDOException             If the database query fails.
     */
    public function setBrightness(int $brightness): bool
    {
        if ($brightness < 0 || $brightness > 100) {
            throw new \InvalidArgumentException(
                "Brightness must be between 0 and 100, got {$brightness}."
            );
        }
        $this->brightness = $brightness;
        $this->update($this->id, [
            'brightness' => $brightness,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    /**
     * Return the device's primary key.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Return the device's name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Return the device type ('light' or 'device').
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Return the device subtype, or null if not set.
     *
     * @return string|null
     */
    public function getSubtype(): ?string
    {
        return $this->subtype;
    }

    /**
     * Return the hex colour string (e.g. '#ff8800'), or null if not set.
     *
     * @return string|null
     */
    public function getColor(): ?string
    {
        return $this->color;
    }

    /**
     * Return the room id, or null if the device is not assigned to a room.
     *
     * @return int|null
     */
    public function getRoomId(): ?int
    {
        return $this->roomId;
    }

    /**
     * Return the brightness (0–100), or null if not set.
     *
     * @return int|null
     */
    public function getBrightness(): ?int
    {
        return $this->brightness;
    }

    /**
     * Return the device state (true = on, false = off).
     *
     * @return bool
     */
    public function getState(): bool
    {
        return $this->state;
    }

    // ── Serialisation helper ──────────────────────────────────────────────────

    /**
     * Return a plain associative array representation of this device.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'type'       => $this->type,
            'subtype'    => $this->subtype,
            'color'      => $this->color,
            'room_id'    => $this->roomId,
            'brightness' => $this->brightness,
            'state'      => $this->state,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
