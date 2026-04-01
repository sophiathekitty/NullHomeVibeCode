<?php
require_once __DIR__ . '/Model.php';

/**
 * NmapScan — records IP addresses discovered during an nmap network scan.
 *
 * Table: nmap_scans
 *
 * Each row represents a single discovered IP address. The `type` column tracks
 * whether the address has been identified as a known device type. `checked_at`
 * is null until a scanner has examined the address.
 */
class NmapScan extends Model
{
    protected static string $table = 'nmap_scans';

    // ── Model interface ────────────────────────────────────────────────────────

    /**
     * Return the table name for this model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return 'nmap_scans';
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
                'name'     => 'ip_address',
                'type'     => 'VARCHAR',
                'length'   => 45,
                'nullable' => false,
                'default'  => null,
            ],
            [
                'name'     => 'type',
                'type'     => 'VARCHAR',
                'length'   => 20,
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
            [
                'name'     => 'checked_at',
                'type'     => 'DATETIME',
                'length'   => null,
                'nullable' => true,
                'default'  => null,
            ],
        ];
    }

    // ── Lifecycle ──────────────────────────────────────────────────────────────

    /**
     * Ensure the nmap_scans table and its unique index on ip_address exist.
     *
     * Creates the table via DB::sync() if it does not exist, then adds a
     * UNIQUE index on ip_address if one is not already present. This enables
     * INSERT IGNORE to silently skip duplicate IP addresses.
     *
     * @return void
     * @throws \PDOException If table creation or index creation fails.
     */
    public function validate(): void
    {
        DB::sync($this);

        $idx = DB::query(
            "SHOW INDEX FROM `nmap_scans` WHERE Key_name = 'idx_nmap_scans_ip'"
        )->fetchAll();

        if (empty($idx)) {
            DB::connection()->exec(
                'CREATE UNIQUE INDEX `idx_nmap_scans_ip` ON `nmap_scans` (`ip_address`)'
            );
        }
    }

    // ── Write methods ──────────────────────────────────────────────────────────

    /**
     * Insert a list of IP addresses, silently skipping any that already exist.
     *
     * Uses INSERT IGNORE so existing records are not modified — their type and
     * checked_at values remain unchanged.
     *
     * @param array<int, string> $ips List of IP address strings to insert.
     * @return void
     * @throws \PDOException If the database query fails.
     */
    public function insertIps(array $ips): void
    {
        if (empty($ips)) {
            return;
        }

        $stmt = DB::connection()->prepare(
            'INSERT IGNORE INTO `nmap_scans` (`ip_address`) VALUES (?)'
        );

        foreach ($ips as $ip) {
            $stmt->execute([(string) $ip]);
        }
    }

    /**
     * Mark a record as checked and record its device type.
     *
     * Sets checked_at to the current datetime and type to the provided value.
     *
     * @param int         $id   Primary key of the record to update.
     * @param string|null $type Device type: 'wemo', 'nullhub', 'other', or null.
     * @return void
     * @throws \PDOException If the database query fails.
     */
    public function markChecked(int $id, ?string $type): void
    {
        DB::query(
            'UPDATE `nmap_scans` SET `checked_at` = NOW(), `type` = ? WHERE `id` = ?',
            [$type, $id]
        );
    }

    /**
     * Reset all non-known records for a fresh scan.
     *
     * Deletes all records where type is null or type = 'other'. Sets
     * checked_at to null on surviving known-device records (wemo, nullhub)
     * so they are re-examined in the new scan.
     *
     * @return void
     * @throws \PDOException If a database query fails.
     */
    public function resetScan(): void
    {
        static::query()
            ->beginGroup()
                ->whereNull('type')
                ->where('type', 'other', '=', 'OR')
            ->endGroup()
            ->delete();

        static::query()
            ->whereIn('type', ['wemo', 'nullhub'])
            ->update(['checked_at' => null]);
    }

    // ── Read methods ───────────────────────────────────────────────────────────

    /**
     * Return the single oldest unchecked record, or null if none remain.
     *
     * Selects the row with the oldest created_at among records where
     * checked_at IS NULL. This is the primary poll method for the scan loop.
     *
     * @return array<string, mixed>|null The oldest unchecked row, or null.
     */
    public function getNextUnchecked(): ?array
    {
        return static::query()
            ->whereNull('checked_at')
            ->orderBy('created_at', 'ASC')
            ->first();
    }

    /**
     * Return the count of records that have not yet been checked.
     *
     * @return int Number of rows where checked_at IS NULL.
     */
    public function getRemainingCount(): int
    {
        return static::query()->whereNull('checked_at')->count();
    }

    /**
     * Delete stale records and return the number of rows deleted.
     *
     * A record is eligible for deletion if:
     *   - checked_at is not null AND checked_at is older than $hours hours, or
     *   - checked_at is null AND created_at is older than $hours hours.
     *
     * If $includeOther is true, also deletes all records where type = 'other'
     * regardless of age.
     *
     * Known device records (type IN ('wemo', 'nullhub')) are never deleted.
     *
     * @param int  $hours        Age threshold in hours.
     * @param bool $includeOther When true, also delete all type = 'other' records.
     * @return int Number of rows deleted.
     * @throws \PDOException If the database query fails.
     */
    public function pruneStale(int $hours, bool $includeOther = false): int
    {
        $qb = static::query()
            ->beginGroup()
                ->whereNull('type')
                ->whereNotIn('type', ['wemo', 'nullhub'], 'OR')
            ->endGroup()
            ->beginGroup()
                ->beginGroup()
                    ->whereNotNull('checked_at')
                    ->whereRaw('`checked_at` < DATE_SUB(NOW(), INTERVAL ? HOUR)', [$hours])
                ->endGroup()
                ->beginGroup('OR')
                    ->whereNull('checked_at')
                    ->whereRaw('`created_at` < DATE_SUB(NOW(), INTERVAL ? HOUR)', [$hours])
                ->endGroup();

        if ($includeOther) {
            $qb = $qb->where('type', 'other', '=', 'OR');
        }

        return $qb->endGroup()->delete();
    }
}
