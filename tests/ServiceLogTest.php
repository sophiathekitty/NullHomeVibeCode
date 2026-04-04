<?php
/**
 * ServiceLogTest — integration tests for the Service and ServiceLog models.
 *
 * Uses the test database configured via config.test.php.
 * All tests operate against real DB rows; tables are created by BaseTestCase
 * via DatabaseValidationService before the suite runs.
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/models/Service.php';
require_once APP_ROOT . '/models/ServiceLog.php';

class ServiceLogTest extends BaseTestCase
{
    /** @var int The ID of the 'every_minute' service row used across tests. */
    private int $serviceId = 0;

    // ── Setup ─────────────────────────────────────────────────────────────────

    /**
     * Truncate service_logs (and re-fetch the service ID) before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection()->exec('DELETE FROM `service_logs`');

        $serviceModel    = new Service();
        $row             = $serviceModel->getByName('every_minute');
        $this->serviceId = (int) $row['id'];
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    /**
     * The services table is created by DatabaseValidationService.
     *
     * @return void
     */
    public function testServicesTableExists(): void
    {
        $this->assertTableExists('services');
    }

    /**
     * The service_logs table is created by DatabaseValidationService.
     *
     * @return void
     */
    public function testServiceLogsTableExists(): void
    {
        $this->assertTableExists('service_logs');
    }

    /**
     * The services table is seeded with the expected default rows.
     *
     * @return void
     */
    public function testServicesTableIsSeeded(): void
    {
        $serviceModel = new Service();
        $all          = $serviceModel->getAll();
        $names        = array_column($all, 'name');

        $this->assertContains('every_minute', $names);
        $this->assertContains('every_hour',   $names);
        $this->assertContains('every_day',    $names);
        $this->assertContains('every_month',  $names);
    }

    // ── Service::getByName() ──────────────────────────────────────────────────

    /**
     * getByName() returns the correct row for a known service name.
     *
     * @return void
     */
    public function testGetByNameReturnsRow(): void
    {
        $serviceModel = new Service();
        $row          = $serviceModel->getByName('every_minute');

        $this->assertIsArray($row);
        $this->assertSame('every_minute', $row['name']);
        $this->assertSame(1, (int) $row['retention_days']);
    }

    /**
     * getByName() returns null for an unknown service name.
     *
     * @return void
     */
    public function testGetByNameReturnsNullForUnknown(): void
    {
        $serviceModel = new Service();
        $row          = $serviceModel->getByName('nonexistent_service');

        $this->assertNull($row);
    }

    // ── ServiceLog::start() ───────────────────────────────────────────────────

    /**
     * start() inserts a row and returns a positive integer ID.
     *
     * @return void
     */
    public function testStartInsertsRowAndReturnsPositiveId(): void
    {
        $log = new ServiceLog();
        $id  = $log->start($this->serviceId);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $row = DB::query(
            'SELECT * FROM `service_logs` WHERE `id` = ?',
            [$id]
        )->fetch();

        $this->assertIsArray($row);
        $this->assertSame($this->serviceId, (int) $row['service_id']);
        $this->assertNotEmpty($row['started_at']);
        $this->assertNull($row['completed_at']);
    }

    // ── ServiceLog::appendLine() ──────────────────────────────────────────────

    /**
     * appendLine() with level LOG appends a correctly formatted line and does not
     * increment error_count or warn_count.
     *
     * @return void
     */
    public function testAppendLineLogDoesNotIncrementCounts(): void
    {
        $log = new ServiceLog();
        $log->start($this->serviceId);
        $log->appendLine('LOG', 'test log message');

        $row = DB::query(
            'SELECT * FROM `service_logs` WHERE `service_id` = ? ORDER BY `id` DESC LIMIT 1',
            [$this->serviceId]
        )->fetch();

        $this->assertIsArray($row);
        $this->assertSame(0, (int) $row['error_count']);
        $this->assertSame(0, (int) $row['warn_count']);
        $this->assertStringContainsString('[LOG]', $row['log']);
        $this->assertStringContainsString('test log message', $row['log']);
    }

    /**
     * appendLine() with level WARN increments warn_count by 1.
     *
     * @return void
     */
    public function testAppendLineWarnIncrementsWarnCount(): void
    {
        $log = new ServiceLog();
        $log->start($this->serviceId);
        $log->appendLine('WARN', 'something warned');

        $row = DB::query(
            'SELECT * FROM `service_logs` WHERE `service_id` = ? ORDER BY `id` DESC LIMIT 1',
            [$this->serviceId]
        )->fetch();

        $this->assertSame(1, (int) $row['warn_count']);
        $this->assertSame(0, (int) $row['error_count']);
        $this->assertStringContainsString('[WARN]', $row['log']);
    }

    /**
     * appendLine() with level ERROR increments error_count by 1.
     *
     * @return void
     */
    public function testAppendLineErrorIncrementsErrorCount(): void
    {
        $log = new ServiceLog();
        $log->start($this->serviceId);
        $log->appendLine('ERROR', 'something failed');

        $row = DB::query(
            'SELECT * FROM `service_logs` WHERE `service_id` = ? ORDER BY `id` DESC LIMIT 1',
            [$this->serviceId]
        )->fetch();

        $this->assertSame(1, (int) $row['error_count']);
        $this->assertSame(0, (int) $row['warn_count']);
        $this->assertStringContainsString('[ERROR]', $row['log']);
    }

    // ── ServiceLog::complete() ────────────────────────────────────────────────

    /**
     * complete() sets completed_at to a non-null datetime.
     *
     * @return void
     */
    public function testCompleteSetCompletedAt(): void
    {
        $log = new ServiceLog();
        $id  = $log->start($this->serviceId);

        $before = DB::query(
            'SELECT `completed_at` FROM `service_logs` WHERE `id` = ?',
            [$id]
        )->fetchColumn();
        $this->assertNull($before);

        $log->complete();

        $after = DB::query(
            'SELECT `completed_at` FROM `service_logs` WHERE `id` = ?',
            [$id]
        )->fetchColumn();
        $this->assertNotNull($after);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $after);
    }

    // ── ServiceLog::getRecentByService() ─────────────────────────────────────

    /**
     * getRecentByService() returns rows in descending started_at order.
     *
     * @return void
     */
    public function testGetRecentByServiceReturnsRowsDescending(): void
    {
        // Insert three rows with known timestamps.
        DB::query(
            'INSERT INTO `service_logs` (`service_id`, `started_at`, `log`, `error_count`, `warn_count`)'
            . ' VALUES (?, ?, ?, 0, 0)',
            [$this->serviceId, '2024-01-01 10:00:00', '']
        );
        DB::query(
            'INSERT INTO `service_logs` (`service_id`, `started_at`, `log`, `error_count`, `warn_count`)'
            . ' VALUES (?, ?, ?, 0, 0)',
            [$this->serviceId, '2024-01-01 12:00:00', '']
        );
        DB::query(
            'INSERT INTO `service_logs` (`service_id`, `started_at`, `log`, `error_count`, `warn_count`)'
            . ' VALUES (?, ?, ?, 0, 0)',
            [$this->serviceId, '2024-01-01 11:00:00', '']
        );

        $log  = new ServiceLog();
        $rows = $log->getRecentByService($this->serviceId);

        $this->assertCount(3, $rows);
        $this->assertSame('2024-01-01 12:00:00', $rows[0]['started_at']);
        $this->assertSame('2024-01-01 11:00:00', $rows[1]['started_at']);
        $this->assertSame('2024-01-01 10:00:00', $rows[2]['started_at']);
    }

    // ── ServiceLog::pruneExpired() ────────────────────────────────────────────

    /**
     * pruneExpired() deletes rows older than retention_days and returns the correct count.
     *
     * every_minute has retention_days = 1.
     * We insert two rows: one 2 days old (should be pruned) and one fresh (should remain).
     *
     * @return void
     */
    public function testPruneExpiredDeletesOldRows(): void
    {
        $old   = date('Y-m-d H:i:s', strtotime('-2 days'));
        $fresh = date('Y-m-d H:i:s');

        DB::query(
            'INSERT INTO `service_logs` (`service_id`, `started_at`, `log`, `error_count`, `warn_count`)'
            . ' VALUES (?, ?, ?, 0, 0)',
            [$this->serviceId, $old, '']
        );
        DB::query(
            'INSERT INTO `service_logs` (`service_id`, `started_at`, `log`, `error_count`, `warn_count`)'
            . ' VALUES (?, ?, ?, 0, 0)',
            [$this->serviceId, $fresh, '']
        );

        $log    = new ServiceLog();
        $pruned = $log->pruneExpired();

        $this->assertSame(1, $pruned);

        $remaining = (int) DB::query(
            'SELECT COUNT(*) FROM `service_logs` WHERE `service_id` = ?',
            [$this->serviceId]
        )->fetchColumn();
        $this->assertSame(1, $remaining);
    }
}
