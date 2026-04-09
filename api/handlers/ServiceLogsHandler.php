<?php
require_once __DIR__ . '/ApiHandler.php';
require_once __DIR__ . '/../../models/Service.php';
require_once __DIR__ . '/../../models/ServiceLog.php';

/**
 * ServiceLogsHandler — handles /api/service-logs/… requests.
 *
 * Routes:
 *   GET /api/service-logs                         → list all services with last run summary
 *   GET /api/service-logs/{service_id}            → 20 most recent runs for a service
 *   GET /api/service-logs/{service_id}/{log_id}   → single run with parsed log entries
 */
class ServiceLogsHandler extends ApiHandler
{
    /**
     * Route the request to the appropriate method.
     *
     * @param array  $params URL path segments after "service-logs".
     * @param string $method HTTP method.
     * @param array  $body   Decoded JSON request body.
     * @return void
     */
    public function handle(array $params, string $method, array $body): void
    {
        if ($method !== 'GET') {
            $this->methodNotAllowed();
            return;
        }

        $serviceId = isset($params[0]) && is_numeric($params[0]) ? (int) $params[0] : null;
        $logId     = isset($params[1]) && is_numeric($params[1]) ? (int) $params[1] : null;

        if ($serviceId === null) {
            $this->ok($this->listServices());
            return;
        }

        if ($logId === null) {
            $result = $this->listRuns($serviceId);
            if ($result === null) {
                $this->notFound("Service $serviceId not found");
                return;
            }
            $this->ok($result);
            return;
        }

        $result = $this->getLogDetail($serviceId, $logId);
        if ($result === null) {
            $this->notFound("Log $logId not found for service $serviceId");
            return;
        }
        $this->ok($result);
    }

    /**
     * Returns all services with their most recent run summary.
     *
     * Uses a single LEFT JOIN with a MAX(started_at) subquery to avoid N+1 queries.
     * The last_run key is null when the service has never run.
     *
     * @return array<int, array<string, mixed>>
     */
    private function listServices(): array
    {
        $rows = DB::query(
            'SELECT s.`id`, s.`name`, s.`retention_days`,'
            . '       sl.`id`           AS last_run_id,'
            . '       sl.`started_at`   AS last_run_started_at,'
            . '       sl.`completed_at` AS last_run_completed_at,'
            . '       sl.`error_count`  AS last_run_error_count,'
            . '       sl.`warn_count`   AS last_run_warn_count'
            . ' FROM `services` s'
            . ' LEFT JOIN `service_logs` sl'
            . '   ON sl.`service_id` = s.`id`'
            . '  AND sl.`started_at` = ('
            . '        SELECT MAX(sl2.`started_at`)'
            . '          FROM `service_logs` sl2'
            . '         WHERE sl2.`service_id` = s.`id`'
            . '      )'
        )->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $lastRun = null;
            if ($row['last_run_id'] !== null) {
                $lastRun = [
                    'id'           => (int) $row['last_run_id'],
                    'started_at'   => $row['last_run_started_at'],
                    'completed_at' => $row['last_run_completed_at'],
                    'error_count'  => (int) $row['last_run_error_count'],
                    'warn_count'   => (int) $row['last_run_warn_count'],
                ];
            }
            $result[] = [
                'id'             => (int) $row['id'],
                'name'           => $row['name'],
                'retention_days' => (int) $row['retention_days'],
                'last_run'       => $lastRun,
            ];
        }
        return $result;
    }

    /**
     * Returns the 20 most recent log rows for the given service, ordered by
     * started_at DESC. Does not include the log text field.
     *
     * Returns null when the service ID is not found.
     *
     * @param int $serviceId The service ID.
     * @return array<int, array<string, mixed>>|null
     */
    private function listRuns(int $serviceId): ?array
    {
        $service = DB::query(
            'SELECT `id` FROM `services` WHERE `id` = ?',
            [$serviceId]
        )->fetch();

        if (!$service) {
            return null;
        }

        $rows = DB::query(
            'SELECT `id`, `service_id`, `started_at`, `completed_at`, `error_count`, `warn_count`'
            . ' FROM `service_logs`'
            . ' WHERE `service_id` = ?'
            . ' ORDER BY `started_at` DESC'
            . ' LIMIT 20',
            [$serviceId]
        )->fetchAll();

        return array_map(function (array $row): array {
            return [
                'id'           => (int) $row['id'],
                'service_id'   => (int) $row['service_id'],
                'started_at'   => $row['started_at'],
                'completed_at' => $row['completed_at'],
                'error_count'  => (int) $row['error_count'],
                'warn_count'   => (int) $row['warn_count'],
            ];
        }, $rows);
    }

    /**
     * Returns a single log row including parsed log entries.
     *
     * Verifies that the log_id belongs to the given service_id.
     * Returns null when the log is not found or belongs to a different service.
     *
     * The raw log text is not included in the response — only the parsed
     * entries array.
     *
     * @param int $serviceId The service ID.
     * @param int $logId     The service_log row ID.
     * @return array<string, mixed>|null
     */
    private function getLogDetail(int $serviceId, int $logId): ?array
    {
        $row = DB::query(
            'SELECT `id`, `service_id`, `started_at`, `completed_at`, `error_count`, `warn_count`, `log`'
            . ' FROM `service_logs`'
            . ' WHERE `id` = ? AND `service_id` = ?',
            [$logId, $serviceId]
        )->fetch();

        if (!$row) {
            return null;
        }

        return [
            'id'           => (int) $row['id'],
            'service_id'   => (int) $row['service_id'],
            'started_at'   => $row['started_at'],
            'completed_at' => $row['completed_at'],
            'error_count'  => (int) $row['error_count'],
            'warn_count'   => (int) $row['warn_count'],
            'entries'      => $this->parseLog($row['log'] ?? ''),
        ];
    }

    /**
     * Parses a raw log string into an array of structured entry objects.
     *
     * Each line has the format: [HH:MM:SS] [LEVEL] message
     *
     * Lines that match are parsed into:
     *   { "time": "HH:MM:SS", "level": "LOG"|"WARN"|"ERROR", "message": "…" }
     *
     * Lines that do not match the pattern are included as:
     *   { "time": null, "level": "LOG", "message": <raw line> }
     *
     * Blank lines are skipped.
     *
     * @param string $log The raw log text from the database.
     * @return array<int, array<string, mixed>>
     */
    private function parseLog(string $log): array
    {
        $entries = [];
        $lines   = explode("\n", $log);

        foreach ($lines as $line) {
            $line = rtrim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^\[(\d{2}:\d{2}:\d{2})\] \[(\w+)\] (.+)$/', $line, $m)) {
                $entries[] = [
                    'time'    => $m[1],
                    'level'   => $m[2],
                    'message' => $m[3],
                ];
            } else {
                $entries[] = [
                    'time'    => null,
                    'level'   => 'LOG',
                    'message' => $line,
                ];
            }
        }

        return $entries;
    }
}
