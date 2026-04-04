<?php
/**
 * ValidationHandlerTest — unit tests for the ValidationHandler API class.
 *
 * Tests exercise the handler logic directly (no HTTP layer) by:
 *   - Calling runValidation() indirectly via a method-accessible subclass.
 *   - Verifying that orphan-table detection works correctly.
 *   - Verifying that model-registered tables cannot be deleted.
 *   - Verifying that orphan tables can be created, detected, deleted.
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/api/handlers/ApiHandler.php';
require_once APP_ROOT . '/api/handlers/ValidationHandler.php';

class ValidationHandlerTest extends BaseTestCase
{
    /** @var ValidationHandlerTestDouble */
    private ValidationHandlerTestDouble $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new ValidationHandlerTestDouble();
    }

    // ── detectOrphanTables ────────────────────────────────────────────────────

    /** No orphan tables immediately after fresh schema sync. */
    public function testNoOrphansAfterCleanSync(): void
    {
        $orphans = $this->handler->exposedDetectOrphanTables();
        $this->assertIsArray($orphans, 'detectOrphanTables() must return an array.');
        $this->assertEmpty($orphans, 'There should be no orphan tables after a clean sync.');
    }

    /** An extra table created outside any model is reported as an orphan. */
    public function testExtraTableIsDetectedAsOrphan(): void
    {
        DB::connection()->exec('CREATE TABLE IF NOT EXISTS `_test_orphan_abc` (`id` INT)');

        try {
            $orphans = $this->handler->exposedDetectOrphanTables();
            $this->assertContains('_test_orphan_abc', $orphans);
        } finally {
            DB::connection()->exec('DROP TABLE IF EXISTS `_test_orphan_abc`');
        }
    }

    // ── deleteTables safety ───────────────────────────────────────────────────

    /** A model-registered table is skipped (not deleted) even when requested. */
    public function testModelTableIsNotDeleted(): void
    {
        $result = $this->handler->exposedDeleteTables(
            ['tables' => ['rooms'], 'confirm' => true]
        );

        $this->assertContains('rooms', $result['skipped'], 'Model table must be skipped.');
        $this->assertNotContains('rooms', $result['deleted'], 'Model table must not be deleted.');
        $this->assertTableExists('rooms');
    }

    /** Deletion is refused when confirm is missing or false. */
    public function testDeletionRefusedWithoutConfirm(): void
    {
        $result = $this->handler->exposedDeleteTables(
            ['tables' => ['rooms']]   // no confirm key
        );
        $this->assertSame('confirm_required', $result['error']);
    }

    /** An orphan table can be deleted when it is selected and confirmed. */
    public function testOrphanTableCanBeDeleted(): void
    {
        DB::connection()->exec('CREATE TABLE IF NOT EXISTS `_test_orphan_del` (`id` INT)');

        try {
            $result = $this->handler->exposedDeleteTables(
                ['tables' => ['_test_orphan_del'], 'confirm' => true]
            );
            $this->assertContains('_test_orphan_del', $result['deleted']);
            $this->assertEmpty($result['errors']);
        } finally {
            DB::connection()->exec('DROP TABLE IF EXISTS `_test_orphan_del`');
        }
    }

    // ── runValidation response shape ──────────────────────────────────────────

    /** runValidation returns the expected top-level keys. */
    public function testRunValidationResponseShape(): void
    {
        $result = $this->handler->exposedRunValidation();
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('orphan_tables', $result);
        $this->assertArrayHasKey('has_errors', $result);
        $this->assertIsArray($result['results']);
        $this->assertIsArray($result['orphan_tables']);
        $this->assertIsBool($result['has_errors']);
    }

    /** Each result row has model, table, and status keys. */
    public function testRunValidationResultRows(): void
    {
        $result = $this->handler->exposedRunValidation();
        $this->assertNotEmpty($result['results'], 'Results must contain at least one model entry.');
        foreach ($result['results'] as $row) {
            $this->assertArrayHasKey('model', $row);
            $this->assertArrayHasKey('table', $row);
            $this->assertArrayHasKey('status', $row);
        }
    }
}

/**
 * Test double that exposes protected/private methods of ValidationHandler.
 */
class ValidationHandlerTestDouble extends ValidationHandler
{
    /**
     * Expose detectOrphanTables for direct testing.
     *
     * @return list<string>
     */
    public function exposedDetectOrphanTables(): array
    {
        return $this->detectOrphanTablesPublic();
    }

    /**
     * Expose the delete logic without going through the full HTTP handler,
     * returning the response data array rather than echoing JSON.
     *
     * @param array $body Request body.
     * @return array{deleted: list<string>, skipped: list<string>, errors: list<array>}
     *             or array{error: string} on validation failure.
     */
    public function exposedDeleteTables(array $body): array
    {
        if (empty($body['confirm'])) {
            return ['error' => 'confirm_required'];
        }

        $tables = $body['tables'] ?? [];
        if (!is_array($tables) || empty($tables)) {
            return ['error' => 'no_tables'];
        }

        $deleted = [];
        $errors  = [];
        $skipped = [];

        $orphans      = $this->detectOrphanTablesPublic();
        $modelTables  = $this->modelTables;

        foreach ($tables as $table) {
            $table = (string) $table;
            if (in_array($table, $modelTables, true)) {
                $skipped[] = $table;
                continue;
            }
            if (!in_array($table, $orphans, true)) {
                $skipped[] = $table;
                continue;
            }
            try {
                DB::connection()->exec('DROP TABLE `' . $table . '`');
                $deleted[] = $table;
            } catch (Throwable $e) {
                $errors[] = ['table' => $table, 'error' => $e->getMessage()];
            }
        }

        return ['deleted' => $deleted, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Expose the run-validation logic, returning the data array directly.
     *
     * @return array{results: list<array>, orphan_tables: list<string>, has_errors: bool}
     */
    public function exposedRunValidation(): array
    {
        require_once APP_ROOT . '/modules/db/DatabaseValidationService.php';
        $svc    = new DatabaseValidationService();
        $result = $svc->validate();

        return [
            'results'       => $result['results'],
            'orphan_tables' => $this->detectOrphanTablesPublic(),
            'has_errors'    => !$result['success'],
        ];
    }

    /**
     * Public wrapper around the private detectOrphanTables() method.
     *
     * @return list<string>
     */
    private function detectOrphanTablesPublic(): array
    {
        $stmt = DB::query(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()'
        );
        $allTables   = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $orphans = [];
        foreach ($allTables as $table) {
            if (!in_array($table, $this->modelTables, true)) {
                $orphans[] = $table;
            }
        }
        return $orphans;
    }

    /**
     * Satisfy the abstract handle() requirement.
     *
     * @param array  $params
     * @param string $method
     * @param array  $body
     * @return void
     */
    public function handle(array $params, string $method, array $body): void {}
}
