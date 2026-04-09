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
        // Service must be registered before ServiceLog (FK dependency).
        $this->modelFiles = [
            'Device'         => $modelsDir . 'Device.php',
            'SettingsModel'  => $modelsDir . 'SettingsModel.php',
            'Room'           => $modelsDir . 'Room.php',
            'RoomNeighbor'   => $modelsDir . 'RoomNeighbor.php',
            'NmapScan'       => $modelsDir . 'NmapScan.php',
            'Wemo'           => $modelsDir . 'Wemo.php',
            'NullHub'        => $modelsDir . 'NullHub.php',
            'Service'        => $modelsDir . 'Service.php',
            'ServiceLog'     => $modelsDir . 'ServiceLog.php',
            'User'           => $modelsDir . 'User.php',
            'UserSession'    => $modelsDir . 'UserSession.php',
        ];
    }

    /**
     * Load, sync, and validate every registered model.
     *
     * After syncing all models, performs safe migrations:
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

        // 1. Unique index on rooms.name.
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

        // 2. Unique index on room_neighbors(room_id, neighbor_id).
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

        // 3. Unique index on nmap_scans.ip_address.
        try {
            $idx = DB::query(
                "SHOW INDEX FROM `nmap_scans` WHERE Key_name = 'idx_nmap_scans_ip'"
            )->fetchAll();
            if (empty($idx)) {
                DB::connection()->exec(
                    'CREATE UNIQUE INDEX `idx_nmap_scans_ip` ON `nmap_scans` (`ip_address`)'
                );
            }
        } catch (Throwable $e) {
            $anyError = true;
            $results[] = [
                'model'  => 'NmapScan',
                'table'  => 'nmap_scans',
                'status' => 'migration error (unique idx ip): ' . $e->getMessage(),
            ];
        }

        // 4. Unique index on wemos.mac_address.
        try {
            $idx = DB::query(
                "SHOW INDEX FROM `wemos` WHERE Key_name = 'idx_wemos_mac'"
            )->fetchAll();
            if (empty($idx)) {
                DB::connection()->exec(
                    'CREATE UNIQUE INDEX `idx_wemos_mac` ON `wemos` (`mac_address`)'
                );
            }
        } catch (Throwable $e) {
            $anyError = true;
            $results[] = [
                'model'  => 'Wemo',
                'table'  => 'wemos',
                'status' => 'migration error (unique idx mac): ' . $e->getMessage(),
            ];
        }

        // 5. Unique index on null_hubs.mac_address.
        try {
            $idx = DB::query(
                "SHOW INDEX FROM `null_hubs` WHERE Key_name = 'idx_null_hubs_mac'"
            )->fetchAll();
            if (empty($idx)) {
                DB::connection()->exec(
                    'CREATE UNIQUE INDEX `idx_null_hubs_mac` ON `null_hubs` (`mac_address`)'
                );
            }
        } catch (Throwable $e) {
            $anyError = true;
            $results[] = [
                'model'  => 'NullHub',
                'table'  => 'null_hubs',
                'status' => 'migration error (unique idx mac): ' . $e->getMessage(),
            ];
        }

        // 6. Unique index on services.name.
        try {
            $idx = DB::query(
                "SHOW INDEX FROM `services` WHERE Key_name = 'idx_services_name'"
            )->fetchAll();
            if (empty($idx)) {
                DB::connection()->exec(
                    'CREATE UNIQUE INDEX `idx_services_name` ON `services` (`name`)'
                );
            }
        } catch (Throwable $e) {
            $anyError = true;
            $results[] = [
                'model'  => 'Service',
                'table'  => 'services',
                'status' => 'migration error (unique idx name): ' . $e->getMessage(),
            ];
        }

        // 7. Seed services table with default rows if not already present.
        $seedServices = [
            ['name' => 'every_minute', 'retention_days' => 1],
            ['name' => 'every_hour',   'retention_days' => 7],
            ['name' => 'every_day',    'retention_days' => 30],
            ['name' => 'every_month',  'retention_days' => 365],
        ];
        foreach ($seedServices as $seed) {
            try {
                $exists = DB::query(
                    'SELECT COUNT(*) FROM `services` WHERE `name` = ?',
                    [$seed['name']]
                )->fetchColumn();
                if ((int) $exists === 0) {
                    DB::query(
                        'INSERT INTO `services` (`name`, `retention_days`) VALUES (?, ?)',
                        [$seed['name'], $seed['retention_days']]
                    );
                }
            } catch (Throwable $e) {
                $anyError = true;
                $results[] = [
                    'model'  => 'Service',
                    'table'  => 'services',
                    'status' => 'seed error (' . $seed['name'] . '): ' . $e->getMessage(),
                ];
            }
        }

        // 8. Foreign key on service_logs.service_id → services.id (if not present).
        try {
            $fk = DB::query(
                "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'service_logs'
                    AND CONSTRAINT_NAME = 'fk_service_logs_service_id'"
            )->fetchColumn();
            if ((int) $fk === 0) {
                DB::connection()->exec(
                    'ALTER TABLE `service_logs`'
                    . ' ADD CONSTRAINT `fk_service_logs_service_id`'
                    . ' FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE'
                );
            }
        } catch (Throwable $e) {
            $anyError = true;
            $results[] = [
                'model'  => 'ServiceLog',
                'table'  => 'service_logs',
                'status' => 'migration error (fk service_id): ' . $e->getMessage(),
            ];
        }

        // 9. Unique index on users.mac_address.
        // MySQL permits multiple NULLs in a unique index, so this only enforces
        // uniqueness among non-null values — correct behaviour for device users.
        try {
            $idx = DB::query(
                "SHOW INDEX FROM `users` WHERE Key_name = 'idx_users_mac'"
            )->fetchAll();
            if (empty($idx)) {
                DB::connection()->exec(
                    'CREATE UNIQUE INDEX `idx_users_mac` ON `users` (`mac_address`)'
                );
            }
        } catch (Throwable $e) {
            $anyError  = true;
            $results[] = ['model' => 'User', 'table' => 'users',
                          'status' => 'migration error (unique idx mac): ' . $e->getMessage()];
        }

        // 10. Unique index on sessions.token.
        try {
            $idx = DB::query(
                "SHOW INDEX FROM `sessions` WHERE Key_name = 'idx_sessions_token'"
            )->fetchAll();
            if (empty($idx)) {
                DB::connection()->exec(
                    'CREATE UNIQUE INDEX `idx_sessions_token` ON `sessions` (`token`)'
                );
            }
        } catch (Throwable $e) {
            $anyError  = true;
            $results[] = ['model' => 'UserSession', 'table' => 'sessions',
                          'status' => 'migration error (unique idx token): ' . $e->getMessage()];
        }

        // 11. FK: sessions.user_id → users.id ON DELETE CASCADE.
        try {
            $fk = DB::query(
                "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'sessions'
                    AND CONSTRAINT_NAME = 'fk_sessions_user_id'"
            )->fetchColumn();
            if ((int) $fk === 0) {
                DB::connection()->exec(
                    'ALTER TABLE `sessions`
                     ADD CONSTRAINT `fk_sessions_user_id`
                     FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE'
                );
            }
        } catch (Throwable $e) {
            $anyError  = true;
            $results[] = ['model' => 'UserSession', 'table' => 'sessions',
                          'status' => 'migration error (fk user_id): ' . $e->getMessage()];
        }

        // 12. Seed localhost system user (id=1) if not present.
        try {
            $exists = DB::query('SELECT COUNT(*) FROM `users` WHERE `id` = 1')->fetchColumn();
            if ((int) $exists === 0) {
                DB::query(
                    'INSERT INTO `users` (`id`, `name`, `role`, `mac_address`, `show_admin_ui`)
                     VALUES (1, ?, ?, NULL, 0)',
                    ['localhost', 'device']
                );
            }
        } catch (Throwable $e) {
            $anyError  = true;
            $results[] = ['model' => 'User', 'table' => 'users',
                          'status' => 'seed error (localhost): ' . $e->getMessage()];
        }

        return [
            'success' => !$anyError,
            'results' => $results,
            'error'   => $anyError ? 'One or more tables could not be synced.' : null,
        ];
    }
}
