<?php
require_once __DIR__ . '/Model.php';

/**
 * Wemo — represents a Belkin Wemo smart plug device.
 *
 * Table: wemos
 *
 * Each row represents a discovered Wemo device. The mac_address column
 * is used as the stable identifier across IP address changes. The device_id
 * column links to a corresponding devices row, created automatically on first
 * discovery and independent thereafter.
 */
class Wemo extends Model
{
    protected static string $table = 'wemos';

    // ── Model interface ────────────────────────────────────────────────────────

    /**
     * Return the table name for this model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return 'wemos';
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
                'name'     => 'mac_address',
                'type'     => 'VARCHAR',
                'length'   => 17,
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
                'name'     => 'port',
                'type'     => 'SMALLINT UNSIGNED',
                'length'   => null,
                'nullable' => false,
                'default'  => '49153',
            ],
            [
                'name'     => 'state',
                'type'     => 'TINYINT',
                'length'   => 1,
                'nullable' => false,
                'default'  => '0',
            ],
            [
                'name'     => 'last_checked',
                'type'     => 'DATETIME',
                'length'   => null,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'device_id',
                'type'     => 'INT UNSIGNED',
                'length'   => null,
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
     * Ensure the wemos table and its unique index on mac_address exist.
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
            "SHOW INDEX FROM `wemos` WHERE Key_name = 'idx_wemos_mac'"
        )->fetchAll();

        if (empty($idx)) {
            DB::connection()->exec(
                'CREATE UNIQUE INDEX `idx_wemos_mac` ON `wemos` (`mac_address`)'
            );
        }
    }

    // ── Read methods ───────────────────────────────────────────────────────────

    /**
     * Find a Wemo record by MAC address.
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
     * Find a Wemo record by IP address.
     *
     * @param string $ip The IP address to search for.
     * @return array<string, mixed>|null The matching row, or null if not found.
     */
    public function findByIp(string $ip): ?array
    {
        return static::query()
            ->where('ip_address', $ip)
            ->first();
    }

    /**
     * Return all rows from the wemos table.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allWemos(): array
    {
        return static::query()->get();
    }

    // ── Write methods ──────────────────────────────────────────────────────────

    /**
     * Insert a new Wemo record and return the new auto-increment id.
     *
     * @param array<string, mixed> $data Associative array of column => value pairs.
     * @return int The new auto-increment id.
     * @throws \PDOException If the database query fails.
     */
    public function createWemo(array $data): int
    {
        return $this->insert($data);
    }

    /**
     * Update fields for the given Wemo id.
     *
     * Only the provided keys are updated; all other columns remain unchanged.
     *
     * @param int                  $id   Primary key of the record to update.
     * @param array<string, mixed> $data Associative array of column => value pairs to update.
     * @return void
     * @throws \PDOException If the database query fails.
     */
    public function updateWemo(int $id, array $data): void
    {
        $this->update($id, $data);
    }

    /**
     * Update the state and set last_checked to the current datetime.
     *
     * Only called after a successful SOAP response so that last_checked
     * always reflects the last confirmed contact with the device.
     *
     * @param int $id    Primary key of the record to update.
     * @param int $state New state value: 1 = on, 0 = off.
     * @return void
     * @throws \PDOException If the database query fails.
     */
    public function updateState(int $id, int $state): void
    {
        $this->update($id, [
            'state'        => $state,
            'last_checked' => date('Y-m-d H:i:s'),
        ]);
    }
}
