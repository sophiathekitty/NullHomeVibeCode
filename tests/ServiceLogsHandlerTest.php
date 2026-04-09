<?php
/**
 * ServiceLogsHandlerTest — unit tests for the ServiceLogsHandler API class.
 *
 * Tests exercise the handler logic via a test double that overrides ok()/error()
 * to throw instead of echoing JSON and calling exit().
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/api/handlers/ApiHandler.php';
require_once APP_ROOT . '/api/handlers/ServiceLogsHandler.php';
require_once APP_ROOT . '/models/Service.php';
require_once APP_ROOT . '/models/ServiceLog.php';

class ServiceLogsHandlerTest extends BaseTestCase
{
    /** @var ServiceLogsHandlerTestDouble */
    private ServiceLogsHandlerTestDouble $handler;

    /** @var int */
    private int $serviceId = 0;

    /**
     * Truncate service_logs and grab the every_minute service ID before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection()->exec('DELETE FROM `service_logs`');

        $service        = new Service();
        $row            = $service->getByName('every_minute');
        $this->serviceId = (int) $row['id'];

        $this->handler = new ServiceLogsHandlerTestDouble();
    }

    // ── GET /api/service-logs ─────────────────────────────────────────────────

    /**
     * List endpoint returns a success response with an array of services.
     *
     * @return void
     */
    public function testListServicesReturnsArray(): void
    {
        $caught = null;
        try {
            $this->handler->handle([], 'GET', []);
        } catch (\RuntimeException $e) {
            $caught = $e;
        }
        $this->assertInstanceOf(\RuntimeException::class, $caught);
        $this->assertStringContainsString('200', $caught->getMessage());
        $this->assertIsArray($this->handler->lastData);
    }

    /**
     * Each service entry has the required keys.
     *
     * @return void
     */
    public function testListServicesEntryShape(): void
    {
        try {
            $this->handler->handle([], 'GET', []);
        } catch (\RuntimeException $e) {
            // expected
        }
        $this->assertNotEmpty($this->handler->lastData);
        $entry = $this->handler->lastData[0];
        foreach (['id', 'name', 'retention_days', 'last_run'] as $key) {
            $this->assertArrayHasKey($key, $entry, "Service entry must have key '$key'.");
        }
    }

    /**
     * last_run is null when the service has never run.
     *
     * @return void
     */
    public function testListServicesLastRunNullWhenNoRuns(): void
    {
        try {
            $this->handler->handle([], 'GET', []);
        } catch (\RuntimeException $e) {
            // expected
        }
        $entry = $this->findServiceEntry($this->serviceId);
        $this->assertNull($entry['last_run']);
    }

    /**
     * last_run is populated after a run has been recorded.
     *
     * @return void
     */
    public function testListServicesLastRunPopulatedAfterRun(): void
    {
        $log = new ServiceLog();
        $log->start($this->serviceId);
        $log->complete();

        try {
            $this->handler->handle([], 'GET', []);
        } catch (\RuntimeException $e) {
            // expected
        }

        $entry   = $this->findServiceEntry($this->serviceId);
        $lastRun = $entry['last_run'];
        $this->assertIsArray($lastRun);
        foreach (['id', 'started_at', 'completed_at', 'error_count', 'warn_count'] as $key) {
            $this->assertArrayHasKey($key, $lastRun, "last_run must have key '$key'.");
        }
    }

    // ── GET /api/service-logs/{service_id} ────────────────────────────────────

    /**
     * Returns 404 when the service does not exist.
     *
     * @return void
     */
    public function testListRunsReturns404ForUnknownService(): void
    {
        $caught = null;
        try {
            $this->handler->handle([99999], 'GET', []);
        } catch (\RuntimeException $e) {
            $caught = $e;
        }
        $this->assertInstanceOf(\RuntimeException::class, $caught);
        $this->assertStringContainsString('404', $caught->getMessage());
    }

    /**
     * Returns an array of runs (without the log field) for a known service.
     *
     * @return void
     */
    public function testListRunsReturnsRunsWithoutLogField(): void
    {
        $log = new ServiceLog();
        $log->start($this->serviceId);
        $log->appendLine('LOG', 'hello');
        $log->complete();

        try {
            $this->handler->handle([$this->serviceId], 'GET', []);
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertIsArray($this->handler->lastData);
        $this->assertCount(1, $this->handler->lastData);
        $row = $this->handler->lastData[0];

        foreach (['id', 'service_id', 'started_at', 'completed_at', 'error_count', 'warn_count'] as $key) {
            $this->assertArrayHasKey($key, $row, "Run row must have key '$key'.");
        }
        $this->assertArrayNotHasKey('log', $row, 'Run row must not expose the log field.');
    }

    /**
     * Returns an empty array when the service has no runs.
     *
     * @return void
     */
    public function testListRunsEmptyWhenNoRuns(): void
    {
        try {
            $this->handler->handle([$this->serviceId], 'GET', []);
        } catch (\RuntimeException $e) {
            // expected
        }
        $this->assertSame([], $this->handler->lastData);
    }

    /**
     * Returns at most 20 runs.
     *
     * @return void
     */
    public function testListRunsLimitedTo20(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $ts = date('Y-m-d H:i:s', strtotime("-{$i} minutes"));
            DB::query(
                'INSERT INTO `service_logs` (`service_id`, `started_at`, `log`, `error_count`, `warn_count`)'
                . ' VALUES (?, ?, ?, 0, 0)',
                [$this->serviceId, $ts, '']
            );
        }

        try {
            $this->handler->handle([$this->serviceId], 'GET', []);
        } catch (\RuntimeException $e) {
            // expected
        }
        $this->assertCount(20, $this->handler->lastData);
    }

    // ── GET /api/service-logs/{service_id}/{log_id} ───────────────────────────

    /**
     * Returns 404 when the log ID does not exist for the service.
     *
     * @return void
     */
    public function testGetLogDetailReturns404ForUnknownLog(): void
    {
        $caught = null;
        try {
            $this->handler->handle([$this->serviceId, 99999], 'GET', []);
        } catch (\RuntimeException $e) {
            $caught = $e;
        }
        $this->assertInstanceOf(\RuntimeException::class, $caught);
        $this->assertStringContainsString('404', $caught->getMessage());
    }

    /**
     * Returns 404 when the log ID belongs to a different service.
     *
     * @return void
     */
    public function testGetLogDetailReturns404WhenServiceMismatch(): void
    {
        // Insert a run under every_minute, then try to fetch it via every_hour's id.
        $log   = new ServiceLog();
        $logId = $log->start($this->serviceId);

        $service      = new Service();
        $otherService = $service->getByName('every_hour');
        $otherId      = (int) $otherService['id'];

        $caught = null;
        try {
            $this->handler->handle([$otherId, $logId], 'GET', []);
        } catch (\RuntimeException $e) {
            $caught = $e;
        }
        $this->assertInstanceOf(\RuntimeException::class, $caught);
        $this->assertStringContainsString('404', $caught->getMessage());
    }

    /**
     * Returns log detail including parsed entries.
     *
     * @return void
     */
    public function testGetLogDetailReturnsParsedEntries(): void
    {
        $log   = new ServiceLog();
        $logId = $log->start($this->serviceId);
        $log->appendLine('LOG',   'every_minute started');
        $log->appendLine('WARN',  'something warned');
        $log->appendLine('ERROR', 'something failed');
        $log->appendLine('LOG',   'every_minute done');
        $log->complete();

        try {
            $this->handler->handle([$this->serviceId, $logId], 'GET', []);
        } catch (\RuntimeException $e) {
            // expected
        }

        $data = $this->handler->lastData;
        $this->assertArrayHasKey('entries', $data);
        $this->assertArrayNotHasKey('log', $data, 'Raw log field must not be in response.');
        $this->assertCount(4, $data['entries']);

        $this->assertSame('LOG',   $data['entries'][0]['level']);
        $this->assertSame('WARN',  $data['entries'][1]['level']);
        $this->assertSame('ERROR', $data['entries'][2]['level']);
        $this->assertSame('LOG',   $data['entries'][3]['level']);

        $this->assertNotNull($data['entries'][0]['time']);
        $this->assertSame('every_minute started', $data['entries'][0]['message']);
    }

    /**
     * Non-GET requests are rejected with 405.
     *
     * @return void
     */
    public function testNonGetIsRejected(): void
    {
        $caught = null;
        try {
            $this->handler->handle([], 'POST', []);
        } catch (\RuntimeException $e) {
            $caught = $e;
        }
        $this->assertInstanceOf(\RuntimeException::class, $caught);
        $this->assertStringContainsString('405', $caught->getMessage());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Find a service entry in lastData by service ID.
     *
     * @param int $serviceId
     * @return array<string, mixed>
     */
    private function findServiceEntry(int $serviceId): array
    {
        foreach ($this->handler->lastData as $entry) {
            if ((int) $entry['id'] === $serviceId) {
                return $entry;
            }
        }
        $this->fail("Service $serviceId not found in response.");
    }
}

/**
 * Test double for ServiceLogsHandler — overrides ok()/error() to throw
 * RuntimeException instead of echoing JSON and calling exit().
 */
class ServiceLogsHandlerTestDouble extends ServiceLogsHandler
{
    /** @var mixed Last captured response data. */
    public mixed $lastData = null;

    /**
     * Override ok() to capture data and throw instead of echoing + exiting.
     *
     * @param mixed $data
     * @return void
     */
    protected function ok(mixed $data = null): void
    {
        $this->lastData = $data;
        throw new \RuntimeException('200 OK');
    }

    /**
     * Override error() to throw instead of echoing + exiting.
     *
     * @param string $message
     * @param int    $status
     * @return void
     */
    protected function error(string $message, int $status = 400): void
    {
        throw new \RuntimeException("$status $message");
    }
}
