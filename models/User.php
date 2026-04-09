<?php
require_once __DIR__ . '/Model.php';

/**
 * User — represents a human or device identity in NullHome.
 *
 * Table: users
 *
 * Roles: 'guest', 'resident', 'device', 'admin'.
 * Device users (role='device') are reserved for future device identity support.
 * The localhost system user (id=1, role='device') is seeded by DatabaseValidationService.
 */
class User extends Model
{
    protected static string $table = 'users';

    /** @var int Auto-increment primary key. */
    protected int $id = 0;

    /** @var string Display name, e.g. "Sophia". */
    protected string $name = '';

    /** @var string Role: 'guest', 'resident', 'device', or 'admin'. */
    protected string $role = 'guest';

    /** @var string|null MAC address (reserved for device users). */
    protected ?string $mac_address = null;

    /** @var string|null Optional hex accent color, e.g. '#16c5ea'. */
    protected ?string $color = null;

    /** @var int Whether this user sees admin tools (0 or 1). */
    protected int $show_admin_ui = 0;

    /** @var string Creation timestamp. */
    protected string $created_at = '';

    // ── Model interface ───────────────────────────────────────────────────────

    /**
     * Return the table name for this model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return 'users';
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
                'name'     => 'role',
                'type'     => 'VARCHAR',
                'length'   => 20,
                'nullable' => false,
                'default'  => 'guest',
            ],
            [
                'name'     => 'mac_address',
                'type'     => 'VARCHAR',
                'length'   => 17,
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
                'name'     => 'show_admin_ui',
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
        ];
    }

    // ── Static factory methods ────────────────────────────────────────────────

    /**
     * Create a hydrated User instance from a raw database row.
     *
     * @param array<string, mixed> $row A raw database row.
     * @return static
     */
    public static function fromRow(array $row): static
    {
        $instance              = new static();
        $instance->id          = (int) $row['id'];
        $instance->name        = (string) $row['name'];
        $instance->role        = (string) $row['role'];
        $instance->mac_address = isset($row['mac_address']) ? (string) $row['mac_address'] : null;
        $instance->color       = isset($row['color']) ? (string) $row['color'] : null;
        $instance->show_admin_ui = (int) ($row['show_admin_ui'] ?? 0);
        $instance->created_at  = (string) ($row['created_at'] ?? '');
        return $instance;
    }

    /**
     * Find a single User by primary key. Returns null if not found.
     *
     * @param int $id The user's primary key.
     * @return static|null
     */
    public static function findById(int $id): ?static
    {
        $row = static::query()->where('id', $id)->first();
        return $row ? static::fromRow($row) : null;
    }

    /**
     * Return all human users (role != 'device') ordered by name ASC.
     * Used to populate the identity switcher UI.
     *
     * @return array<int, static>
     */
    public static function findHumans(): array
    {
        $stmt = DB::query(
            "SELECT * FROM `users` WHERE `role` != 'device' ORDER BY `name` ASC"
        );
        $rows = $stmt->fetchAll();
        return array_map(static fn(array $r) => static::fromRow($r), $rows);
    }

    /**
     * Find a User by MAC address. Returns null if not found or if $mac is empty.
     * Reserved for device identity (future use).
     *
     * @param string $mac Lowercase colon-separated MAC, e.g. 'aa:bb:cc:dd:ee:ff'.
     * @return static|null
     */
    public static function findByMac(string $mac): ?static
    {
        if ($mac === '') {
            return null;
        }
        $row = static::query()->where('mac_address', $mac)->first();
        return $row ? static::fromRow($row) : null;
    }

    // ── Instance methods ──────────────────────────────────────────────────────

    /**
     * Return the user's primary key.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Return the user's display name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Return the user's role.
     *
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Return the user's MAC address, or null.
     *
     * @return string|null
     */
    public function getMacAddress(): ?string
    {
        return $this->mac_address;
    }

    /**
     * Return the user's hex accent color, or null.
     *
     * @return string|null
     */
    public function getColor(): ?string
    {
        return $this->color;
    }

    /**
     * Return whether this user should see admin UI tools.
     *
     * @return bool
     */
    public function showAdminUi(): bool
    {
        return (bool) $this->show_admin_ui;
    }

    /**
     * Return all columns as a plain associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'role'          => $this->role,
            'mac_address'   => $this->mac_address,
            'color'         => $this->color,
            'show_admin_ui' => $this->showAdminUi(),
            'created_at'    => $this->created_at,
        ];
    }
}
