<?php
require_once __DIR__ . '/Model.php';

/**
 * NullHub — represents a legacy NullHub server device.
 *
 * Table: null_hubs
 *
 * Each row represents a discovered legacy NullHub device. The mac_address
 * column is used as the stable identifier. The hub column stores the parent
 * hub IP (nullable for main hubs). Fields mirror the shape returned by the
 * /api/info endpoint on legacy NullHub devices.
 */
class NullHub extends Model
{
    protected static string $table = 'null_hubs';

    // ── Model interface ────────────────────────────────────────────────────────

    /**
     * Return the table name for this model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return 'null_hubs';
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
                'name'     => 'mac_address',
                'type'     => 'VARCHAR',
                'length'   => 100,
                'nullable' => false,
                'default'  => null,
            ],
            [
                'name'     => 'name',
                'type'     => 'VARCHAR',
                'length'   => 100,
                'nullable' => false,
                'default'  => null,
            ],
            [
                'name'     => 'url',
                'type'     => 'VARCHAR',
                'length'   => 255,
                'nullable' => false,
                'default'  => null,
            ],
            [
                'name'     => 'hub',
                'type'     => 'VARCHAR',
                'length'   => 255,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'type',
                'type'     => 'VARCHAR',
                'length'   => 50,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'server',
                'type'     => 'VARCHAR',
                'length'   => 100,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'main',
                'type'     => 'TINYINT',
                'length'   => 1,
                'nullable' => false,
                'default'  => '0',
            ],
            [
                'name'     => 'last_ping',
                'type'     => 'DATETIME',
                'length'   => null,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'modified',
                'type'     => 'DATETIME',
                'length'   => null,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'online',
                'type'     => 'TINYINT',
                'length'   => 1,
                'nullable' => false,
                'default'  => '0',
            ],
            [
                'name'     => 'offline',
                'type'     => 'TINYINT',
                'length'   => 1,
                'nullable' => false,
                'default'  => '0',
            ],
            [
                'name'     => 'enabled',
                'type'     => 'TINYINT',
                'length'   => 1,
                'nullable' => false,
                'default'  => '1',
            ],
            [
                'name'     => 'room',
                'type'     => 'INT UNSIGNED',
                'length'   => null,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'dev',
                'type'     => 'VARCHAR',
                'length'   => 50,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'hash',
                'type'     => 'VARCHAR',
                'length'   => 255,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'git',
                'type'     => 'VARCHAR',
                'length'   => 255,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'setup',
                'type'     => 'VARCHAR',
                'length'   => 50,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'created_at',
                'type'     => 'DATETIME',
                'length'   => null,
                'nullable' => false,
                'default'  => 'CURRENT_TIMESTAMP',
            ],
        ];
    }

    // ── Lifecycle ──────────────────────────────────────────────────────────────

    /**
     * Ensure the null_hubs table and its unique index on mac_address exist.
     *
     * Creates the table via DB::sync() if it does not exist, then adds a
     * UNIQUE index on mac_address if one is not already present.
     *
     * @return void
     * @throws \PDOException If table creation or index creation fails.
     */
    public function validate(): void
    {
        DB::sync($this);

        $idx = DB::query(
            "SHOW INDEX FROM `null_hubs` WHERE Key_name = 'idx_null_hubs_mac'"
        )->fetchAll();

        if (empty($idx)) {
            DB::connection()->exec(
                'CREATE UNIQUE INDEX `idx_null_hubs_mac` ON `null_hubs` (`mac_address`)'
            );
        }
    }

    // ── Read methods ───────────────────────────────────────────────────────────

    /**
     * Find a NullHub record by MAC address.
     *
     * @param string $mac The MAC address to search for.
     * @return array<string, mixed>|null The matching row, or null if not found.
     */
    public function findByMac(string $mac): ?array
    {
        return static::query()
            ->where('mac_address', $mac)
            ->first();
    }

    /**
     * Find a NullHub record by URL (IP address).
     *
     * @param string $url The URL/IP to search for.
     * @return array<string, mixed>|null The matching row, or null if not found.
     */
    public function findByUrl(string $url): ?array
    {
        return static::query()
            ->where('url', $url)
            ->first();
    }

    /**
     * Find all NullHub records whose hub field matches the given parent IP.
     *
     * @param string $hub The parent hub IP address.
     * @return array<int, array<string, mixed>>
     */
    public function findByHub(string $hub): array
    {
        return static::query()
            ->where('hub', $hub)
            ->get();
    }

    /**
     * Return all rows from the null_hubs table.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allNullHubs(): array
    {
        return static::query()->get();
    }

    // ── Write methods ──────────────────────────────────────────────────────────

    /**
     * Insert a new NullHub record and return the new auto-increment id.
     *
     * @param array<string, mixed> $data Associative array of column => value pairs.
     * @return int The new auto-increment id.
     * @throws \PDOException If the database query fails.
     */
    public function createNullHub(array $data): int
    {
        return $this->insert($data);
    }

    /**
     * Update fields for the given NullHub id.
     *
     * Only the provided keys are updated; all other columns remain unchanged.
     *
     * @param int                  $id   Primary key of the record to update.
     * @param array<string, mixed> $data Associative array of column => value pairs to update.
     * @return void
     * @throws \PDOException If the database query fails.
     */
    public function updateNullHub(int $id, array $data): void
    {
        $this->update($id, $data);
    }

    /**
     * Update last_ping to now and mark the device as online.
     *
     * @param int $id Primary key of the record to update.
     * @return void
     * @throws \PDOException If the database query fails.
     */
    public function updateLastPing(int $id): void
    {
        $this->update($id, [
            'last_ping' => date('Y-m-d H:i:s'),
            'online'    => 1,
        ]);
    }
}
