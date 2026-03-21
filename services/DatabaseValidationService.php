<?php
/**
 * DatabaseValidationService — validates and syncs all model tables.
 *
 * Iterates over every registered Model subclass, confirms its table exists in
 * the database, and ensures every declared column is present with the correct
 * definition via DB::sync().
 *
 * Prerequisites: DB_HOST / DB_NAME / DB_USER / DB_PASS / DB_CHARSET constants
 * must be defined, and the DB class must be loaded, before calling validate().
 */
class DatabaseValidationService {
    /** @var array<string,string>  Map of model class name → absolute file path */
    private array $modelFiles;

    public function __construct() {
        $modelsDir = dirname(__DIR__) . '/models/';
        // Add a new entry here whenever a new Model subclass is created.
        $this->modelFiles = [
            'LightsModel'   => $modelsDir . 'LightsModel.php',
            'SettingsModel' => $modelsDir . 'SettingsModel.php',
        ];
    }

    /**
     * Load, sync, and validate every registered model.
     *
     * @return array{
     *   success: bool,
     *   results: list<array{model: string, table: string, status: string}>,
     *   error:   string|null
     * }
     */
    public function validate(): array {
        require_once dirname(__DIR__) . '/models/Model.php';

        $results  = [];
        $anyError = false;

        foreach ($this->modelFiles as $class => $file) {
            require_once $file;
            $model = new $class();
            try {
                DB::sync($model);
                $results[] = [
                    'model'  => $class,
                    'table'  => $model->getTable(),
                    'status' => 'ok',
                ];
            } catch (Throwable $e) {
                $anyError  = true;
                $results[] = [
                    'model'  => $class,
                    'table'  => $model->getTable(),
                    'status' => 'error: ' . $e->getMessage(),
                ];
            }
        }

        return [
            'success' => !$anyError,
            'results' => $results,
            'error'   => $anyError ? 'One or more tables could not be synced.' : null,
        ];
    }
}
