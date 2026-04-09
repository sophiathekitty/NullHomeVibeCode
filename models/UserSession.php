<?php
require_once __DIR__ . '/Model.php';

/**
 * UserSession — represents an active login session for a user.
 *
 * Table: sessions
 *
 * Human sessions (role IN ('guest', 'resident', 'admin')) have no expiry
 * (expires_at = NULL). Device session expiry is deferred to a future issue.
 */
class UserSession extends Model
{
    protected static string $table = 'sessions';

    /** @var int Auto-increment primary key. */
    protected int $id = 0;

    /** @var int FK → users.id */
    protected int $user_id = 0;

    /** @var string 64-char hex token. */
    protected string $token = '';

    /** @var string IP address at session creation. */
    protected string $ip_address = '';

    /** @var string Creation datetime string. */
    protected string $created_at = '';

    /** @var string Last seen datetime string. */
    protected string $last_seen_at = '';

    /** @var string|null Expiry datetime, or null for no expiry. */
    protected ?string $expires_at = null;

    // ── Model interface ───────────────────────────────────────────────────────

    /**
     * Return the table name for this model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return 'sessions';
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
                'name'     => 'user_id',
                'type'     => 'INT',
                'length'   => null,
                'nullable' => false,
                'default'  => null,
            ],
            [
                'name'     => 'token',
                'type'     => 'VARCHAR',
                'length'   => 64,
                'nullable' => false,
                'default'  => null,
            ],
            [
                'name'     => 'ip_address',
                'type'     => 'VARCHAR',
                'length'   => 45,
                'nullable' => false,
                'default'  => null,
            ],
            [
                'name'     => 'created_at',
                'type'     => 'DATETIME',
                'length'   => null,
                'nullable' => false,
                'default'  => 'CURRENT_TIMESTAMP',
            ],
            [
                'name'     => 'last_seen_at',
                'type'     => 'DATETIME',
                'length'   => null,
                'nullable' => false,
                'default'  => 'CURRENT_TIMESTAMP',
            ],
            [
                'name'     => 'expires_at',
                'type'     => 'DATETIME',
                'length'   => null,
                'nullable' => true,
                'default'  => null,
            ],
        ];
    }

    // ── Static factory methods ────────────────────────────────────────────────

    /**
     * Create a hydrated UserSession instance from a raw database row.
     *
     * @param array<string, mixed> $row A raw database row.
     * @return static
     */
    public static function fromRow(array $row): static
    {
        $instance               = new static();
        $instance->id           = (int) $row['id'];
        $instance->user_id      = (int) $row['user_id'];
        $instance->token        = (string) $row['token'];
        $instance->ip_address   = (string) $row['ip_address'];
        $instance->created_at   = (string) ($row['created_at'] ?? '');
        $instance->last_seen_at = (string) ($row['last_seen_at'] ?? '');
        $instance->expires_at   = isset($row['expires_at']) ? (string) $row['expires_at'] : null;
        return $instance;
    }

    /**
     * Find a session by token. Returns null if not found.
     *
     * @param string $token The session token.
     * @return static|null
     */
    public static function findByToken(string $token): ?static
    {
        $row = static::query()->where('token', $token)->first();
        return $row ? static::fromRow($row) : null;
    }

    /**
     * Return all non-expired sessions for a user.
     * For human users this is effectively all their sessions (expires_at is null).
     *
     * @param int $userId The user's primary key.
     * @return array<int, static>
     */
    public static function findActiveByUserId(int $userId): array
    {
        $stmt = DB::query(
            "SELECT * FROM `sessions`
              WHERE `user_id` = ?
                AND (`expires_at` IS NULL OR `expires_at` > NOW())",
            [$userId]
        );
        $rows = $stmt->fetchAll();
        return array_map(static fn(array $r) => static::fromRow($r), $rows);
    }

    // ── Instance methods ──────────────────────────────────────────────────────

    /**
     * Return the user ID for this session.
     *
     * @return int
     */
    public function getUserId(): int
    {
        return $this->user_id;
    }

    /**
     * Return the session token.
     *
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Return the IP address at session creation.
     *
     * @return string
     */
    public function getIpAddress(): string
    {
        return $this->ip_address;
    }

    /**
     * Return true if expires_at is set and is in the past.
     * Always returns false for human sessions (expires_at = null).
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }
        return strtotime($this->expires_at) < time();
    }

    /**
     * Return all columns as a plain associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'token'        => $this->token,
            'ip_address'   => $this->ip_address,
            'created_at'   => $this->created_at,
            'last_seen_at' => $this->last_seen_at,
            'expires_at'   => $this->expires_at,
        ];
    }
}
