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

    /**
     * Constructor — registers all model class files to be validated.
     */
    public function __construct() {
        $modelsDir = dirname(dirname(__DIR__)) . '/models/';
        // Add a new entry here whenever a new Model subclass is created.
        $this->modelFiles = [
            'LightsModel'    => $modelsDir . 'LightsModel.php',
            'SettingsModel'  => $modelsDir . 'SettingsModel.php',
            'Room'           => $modelsDir . 'Room.php',
            'RoomNeighbor'   => $modelsDir . 'RoomNeighbor.php',
        ];
    }

    /**
     * Load, sync, and validate every registered model.
     *
     * After syncing all models, performs safe migrations:
     *   - Drops the legacy `location` column from `lights` if present.
     *   - Adds a UNIQUE index on `rooms.name` if not present.
     *   - Adds a UNIQUE index on `room_neighbors(room_id, neighbor_id)` if not present.
     *
     * @return array{
     *   success: bool,
     *   results: list<array{model: string, table: string, status: string}>,
     *   error:   string|null
     * }
     */
    public function validate(): array {
        require_once dirname(dirname(__DIR__)) . '/models/Model.php';

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

        // ── Safe post-sync migrations ──────────────────────────────────────────

        // 1. Drop the legacy `location` column from `lights` if it still exists.
        try {
            $cols = DB::query('SHOW COLUMNS FROM `lights`')->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_column($cols, 'Field');
            if (in_array('location', $colNames, true)) {
                DB::connection()->exec('ALTER TABLE `lights` DROP COLUMN `location`');
            }
        } catch (Throwable $e) {
            $anyError = true;
            $results[] = [
                'model'  => 'LightsModel',
                'table'  => 'lights',
                'status' => 'migration error (drop location): ' . $e->getMessage(),
            ];
        }

        // 2. Unique index on rooms.name.
        try {
            $idx = DB::query(
                "SHOW INDEX FROM `rooms` WHERE Key_name = 'idx_rooms_name'"
            )->fetchAll();
            if (empty($idx)) {
                DB::connection()->exec(
                    'CREATE UNIQUE INDEX `idx_rooms_name` ON `rooms` (`name`)'
                );
            }
        } catch (Throwable $e) {
            $anyError = true;
            $results[] = [
                'model'  => 'Room',
                'table'  => 'rooms',
                'status' => 'migration error (unique idx name): ' . $e->getMessage(),
            ];
        }

        // 3. Unique index on room_neighbors(room_id, neighbor_id).
        try {
            $idx = DB::query(
                "SHOW INDEX FROM `room_neighbors` WHERE Key_name = 'idx_room_neighbors_pair'"
            )->fetchAll();
            if (empty($idx)) {
                DB::connection()->exec(
                    'CREATE UNIQUE INDEX `idx_room_neighbors_pair`'
                    . ' ON `room_neighbors` (`room_id`, `neighbor_id`)'
                );
            }
        } catch (Throwable $e) {
            $anyError = true;
            $results[] = [
                'model'  => 'RoomNeighbor',
                'table'  => 'room_neighbors',
                'status' => 'migration error (unique idx pair): ' . $e->getMessage(),
            ];
        }

        return [
            'success' => !$anyError,
            'results' => $results,
            'error'   => $anyError ? 'One or more tables could not be synced.' : null,
        ];
    }
}
