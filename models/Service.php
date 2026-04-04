<?php
require_once __DIR__ . '/Model.php';

/**
 * Service — represents a known cron service and its log retention policy.
 *
 * Table: services
 *
 * Each row registers a cron service by name and specifies how many days of
 * service_logs rows to retain. Seed rows are inserted by
 * DatabaseValidationService on first install.
 */
class Service extends Model
{
    protected static string $table = 'services';

    // ── Model interface ───────────────────────────────────────────────────────

    /**
     * Return the table name for this model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return 'services';
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
                'name'     => 'retention_days',
                'type'     => 'INT',
                'length'   => null,
                'nullable' => false,
                'default'  => '7',
            ],
        ];
    }

    // ── Query methods ─────────────────────────────────────────────────────────

    /**
     * Returns the Service row for the given name, or null if not found.
     *
     * @param string $name The service name (e.g. 'every_minute').
     * @return array<string, mixed>|null
     */
    public function getByName(string $name): ?array
    {
        $row = DB::query(
            'SELECT * FROM `services` WHERE `name` = ?',
            [$name]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Returns all service rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        return DB::query('SELECT * FROM `services`')->fetchAll();
    }
}
