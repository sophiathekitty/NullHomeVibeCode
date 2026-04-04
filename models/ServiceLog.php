<?php
require_once __DIR__ . '/Model.php';

/**
 * ServiceLog — represents a single cron service run in the database.
 *
 * Table: service_logs
 *
 * One instance is created per service invocation via start(), which inserts a
 * new row and stores the new row ID internally. All subsequent appendLine() and
 * complete() calls operate on that row.
 *
 * appendLine() uses a SQL CONCAT update to append log lines atomically, avoiding
 * read-modify-write races if two processes somehow share a log row.
 */
class ServiceLog extends Model
{
    protected static string $table = 'service_logs';

    /** @var int The ID of the currently active service_logs row, or 0 if not started. */
    private int $logId = 0;

    // ── Model interface ───────────────────────────────────────────────────────

    /**
     * Return the table name for this model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return 'service_logs';
    }

    /**
     * Return the ordered array of field definitions for this model.
     *
     * Note: completed_at is nullable with no default (NULL by default).
     * The log TEXT column has no DEFAULT to ensure broad MySQL compatibility;
     * start() always inserts an explicit empty string.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFields(): array
    {
        return [
            [
                'name'     => 'service_id',
                'type'     => 'INT UNSIGNED',
                'length'   => null,
                'nullable' => false,
                'default'  => null,
            ],
            [
                'name'     => 'started_at',
                'type'     => 'DATETIME',
                'length'   => null,
                'nullable' => false,
                'default'  => null,
            ],
            [
                'name'     => 'completed_at',
                'type'     => 'DATETIME',
                'length'   => null,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'log',
                'type'     => 'TEXT',
                'length'   => null,
                'nullable' => false,
                'default'  => null,
            ],
            [
                'name'     => 'error_count',
                'type'     => 'INT',
                'length'   => null,
                'nullable' => false,
                'default'  => '0',
            ],
            [
                'name'     => 'warn_count',
                'type'     => 'INT',
                'length'   => null,
                'nullable' => false,
                'default'  => '0',
            ],
        ];
    }

    // ── Run lifecycle ─────────────────────────────────────────────────────────

    /**
     * Creates a new service_log row for the given service ID, sets started_at to NOW(),
     * and stores the new row ID internally. Returns the new log ID.
     *
     * @param int $serviceId The ID of the service being started.
     * @return int The new service_log row ID.
     */
    public function start(int $serviceId): int
    {
        $this->logId = $this->insert([
            'service_id'  => $serviceId,
            'started_at'  => date('Y-m-d H:i:s'),
            'log'         => '',
            'error_count' => 0,
            'warn_count'  => 0,
        ]);
        return $this->logId;
    }

    /**
     * Appends a formatted log line to the log text field for the current run.
     * Also increments warn_count or error_count if the level is WARN or ERROR.
     *
     * Uses a SQL CONCAT update to avoid read-modify-write races.
     *
     * Line format: [HH:MM:SS] [LEVEL] message\n
     *
     * @param string $level   Log level: 'LOG', 'WARN', or 'ERROR'.
     * @param string $message The message to append.
     * @return void
     */
    public function appendLine(string $level, string $message): void
    {
        $line = sprintf("[%s] [%s] %s\n", date('H:i:s'), $level, $message);

        if ($level === 'WARN') {
            DB::query(
                'UPDATE `service_logs` SET `log` = CONCAT(`log`, ?), `warn_count` = `warn_count` + 1 WHERE `id` = ?',
                [$line, $this->logId]
            );
        } elseif ($level === 'ERROR') {
            DB::query(
                'UPDATE `service_logs` SET `log` = CONCAT(`log`, ?), `error_count` = `error_count` + 1 WHERE `id` = ?',
                [$line, $this->logId]
            );
        } else {
            DB::query(
                'UPDATE `service_logs` SET `log` = CONCAT(`log`, ?) WHERE `id` = ?',
                [$line, $this->logId]
            );
        }
    }

    /**
     * Sets completed_at to NOW() for the current run.
     *
     * @return void
     */
    public function complete(): void
    {
        DB::query(
            'UPDATE `service_logs` SET `completed_at` = ? WHERE `id` = ?',
            [date('Y-m-d H:i:s'), $this->logId]
        );
    }

    // ── Query methods ─────────────────────────────────────────────────────────

    /**
     * Returns recent log rows for the given service ID, ordered by started_at DESC.
     *
     * @param int $serviceId The service ID to query.
     * @param int $limit     Maximum number of rows to return. Default 20.
     * @return array<int, array<string, mixed>>
     */
    public function getRecentByService(int $serviceId, int $limit = 20): array
    {
        return DB::query(
            'SELECT * FROM `service_logs` WHERE `service_id` = ? ORDER BY `started_at` DESC LIMIT ?',
            [$serviceId, $limit]
        )->fetchAll();
    }

    /**
     * Deletes service_log rows older than the retention period for each service.
     * Joins service_logs against services on service_id to get retention_days per service.
     * Called by the every_day service.
     *
     * @return int The number of rows deleted.
     */
    public function pruneExpired(): int
    {
        $stmt = DB::query(
            'DELETE sl FROM `service_logs` sl'
            . ' JOIN `services` s ON sl.`service_id` = s.`id`'
            . ' WHERE sl.`started_at` < DATE_SUB(NOW(), INTERVAL s.`retention_days` DAY)'
        );
        return $stmt->rowCount();
    }
}
