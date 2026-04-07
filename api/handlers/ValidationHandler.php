<?php
require_once __DIR__ . '/ApiHandler.php';
require_once __DIR__ . '/../../modules/db/DatabaseValidationService.php';

/**
 * ValidationHandler — handles /api/validation/… requests.
 *
 * Routes:
 *   POST /api/validation/run           → validate DB schema against all models
 *   POST /api/validation/delete-tables → drop confirmed orphan tables
 */
class ValidationHandler extends ApiHandler
{
    /**
     * Table names that belong to registered models and must never be deleted.
     *
     * @var list<string>
     */
    protected array $modelTables = [
        'devices',
        'settings',
        'rooms',
        'room_neighbors',
        'nmap_scans',
        'wemos',
        'null_hubs',
        'services',
        'service_logs',
    ];

    /**
     * Route the validation request.
     *
     * URL segments received in $params (everything after "validation"):
     *   ['run']           → POST /api/validation/run
     *   ['delete-tables'] → POST /api/validation/delete-tables
     *
     * @param array  $params URL path segments after the "validation" resource key.
     * @param string $method HTTP request method.
     * @param array  $body   Decoded JSON request body.
     * @return void
     */
    public function handle(array $params, string $method, array $body): void
    {
        if ($method !== 'POST') {
            $this->methodNotAllowed();
            return;
        }

        $action = $params[0] ?? null;

        if ($action === 'run') {
            $this->runValidation();
            return;
        }

        if ($action === 'delete-tables') {
            $this->deleteTables($body);
            return;
        }

        $this->notFound($action !== null ? "Unknown validation action: $action" : 'Validation action is required');
    }

    /**
     * Run the DatabaseValidationService and also detect orphan tables.
     *
     * An orphan table is any table that exists in the database but has no
     * corresponding registered model.
     *
     * @return void
     */
    private function runValidation(): void
    {
        $svc    = new DatabaseValidationService();
        $result = $svc->validate();

        $orphanTables = $this->detectOrphanTables();

        $this->ok([
            'results'       => $result['results'],
            'orphan_tables' => $orphanTables,
            'has_errors'    => !$result['success'],
        ]);
    }

    /**
     * Drop the requested orphan tables after safety checks.
     *
     * Only tables that are NOT in the model registry may be dropped.
     * The request body must include:
     *   - tables:  (array<string>)  list of table names to drop
     *   - confirm: (bool)           must be true or deletion is refused
     *
     * @param array $body Decoded JSON request body.
     * @return void
     */
    private function deleteTables(array $body): void
    {
        if (empty($body['confirm'])) {
            $this->error('Deletion requires confirm: true in the request body.', 400);
            return;
        }

        $tables = $body['tables'] ?? [];
        if (!is_array($tables) || empty($tables)) {
            $this->error('No tables specified for deletion.', 400);
            return;
        }

        $deleted  = [];
        $errors   = [];
        $skipped  = [];

        $orphans = $this->detectOrphanTables();

        foreach ($tables as $table) {
            $table = (string) $table;

            // Reject table names that contain anything other than word characters.
            // This guards against backtick injection in the DROP TABLE statement.
            if (!preg_match('/^\w+$/', $table)) {
                $errors[] = ['table' => $table, 'error' => 'Invalid table name.'];
                continue;
            }

            // Refuse to drop any model-registered table.
            if (in_array($table, $this->modelTables, true)) {
                $skipped[] = $table;
                continue;
            }

            // Only allow dropping tables that were identified as orphans.
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

        $this->ok([
            'deleted' => $deleted,
            'skipped' => $skipped,
            'errors'  => $errors,
        ]);
    }

    /**
     * Return a list of table names that exist in the DB but have no model.
     *
     * @return list<string>
     */
    private function detectOrphanTables(): array
    {
        $stmt = DB::query(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()'
        );
        $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $orphans = [];
        foreach ($allTables as $table) {
            if (!in_array($table, $this->modelTables, true)) {
                $orphans[] = $table;
            }
        }
        return $orphans;
    }
}
