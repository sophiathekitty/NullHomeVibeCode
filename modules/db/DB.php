<?php
/**
 * DB — database connection and automatic schema sync.
 *
 * Models define their fields as a structured array:
 *   [ 'name' => 'column_name', 'type' => 'VARCHAR', 'length' => 255,
 *     'nullable' => false, 'default' => null ]
 *
 * DB::sync($model) compares the model definition against the live MySQL schema
 * and issues CREATE TABLE or ALTER TABLE … ADD/MODIFY COLUMN statements as needed.
 */
class DB {
    private static ?PDO $pdo = null;

    /**
     * Return (and lazily create) the shared PDO connection.
     *
     * @return PDO The shared database connection.
     * @throws PDOException If the connection cannot be established.
     */
    public static function connection(): PDO {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST, DB_NAME, DB_CHARSET
            );
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    /**
     * Prepare and execute a parameterised query.
     *
     * @param  string $sql    The SQL query string with optional placeholders.
     * @param  array  $params Positional parameter values to bind.
     * @return PDOStatement   The executed statement.
     * @throws PDOException   If the query fails.
     */
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Sync a model's field definitions against the live database schema.
     * Creates the table if it does not exist; otherwise adds or modifies columns.
     *
     * @param  object $model An instance of a Model subclass.
     * @return void
     * @throws PDOException  If a schema alteration query fails.
     */
    public static function sync(object $model): void {
        $table  = $model->getTable();
        $fields = $model->getFields();

        if (self::tableExists($table)) {
            self::syncColumns($table, $fields);
        } else {
            self::createTable($table, $fields);
        }
    }

    // ── private helpers ────────────────────────────────────────────────────

    /**
     * Check whether a table exists in the current database.
     *
     * @param  string $table The table name to check.
     * @return bool          True if the table exists, false otherwise.
     */
    private static function tableExists(string $table): bool {
        $stmt = self::query(
            'SELECT COUNT(*) FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Create a new table with an auto-increment primary key and the given fields.
     *
     * @param  string $table  The table name to create.
     * @param  array  $fields Ordered list of field definition arrays.
     * @return void
     * @throws PDOException   If the CREATE TABLE statement fails.
     */
    private static function createTable(string $table, array $fields): void {
        $columns = ['`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'];
        foreach ($fields as $field) {
            $columns[] = self::columnDef($field);
        }
        $sql = sprintf(
            'CREATE TABLE `%s` (%s) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $table,
            implode(', ', $columns)
        );
        self::connection()->exec($sql);
    }

    /**
     * Add or modify columns in an existing table to match the field definitions.
     *
     * @param  string $table  The table name to alter.
     * @param  array  $fields Ordered list of field definition arrays.
     * @return void
     * @throws PDOException   If an ALTER TABLE statement fails.
     */
    private static function syncColumns(string $table, array $fields): void {
        $existing = self::existingColumns($table);
        foreach ($fields as $field) {
            $name = $field['name'];
            if (!isset($existing[$name])) {
                $sql = sprintf(
                    'ALTER TABLE `%s` ADD COLUMN %s',
                    $table,
                    self::columnDef($field)
                );
                self::connection()->exec($sql);
            } else {
                // Modify the column if the definition has changed.
                $def = self::columnDef($field);
                $sql = sprintf('ALTER TABLE `%s` MODIFY COLUMN %s', $table, $def);
                self::connection()->exec($sql);
            }
        }
    }

    /**
     * Return an associative array of column_name => column_type for a table.
     *
     * @param  string               $table The table name to inspect.
     * @return array<string,string>        Map of column name to column type.
     */
    private static function existingColumns(string $table): array {
        $stmt = self::query(
            'SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
        $cols = [];
        foreach ($stmt->fetchAll() as $row) {
            $cols[$row['COLUMN_NAME']] = $row['COLUMN_TYPE'];
        }
        return $cols;
    }

    /**
     * Build a SQL column definition string from a field array.
     *
     * @param  array  $field A field definition array with keys: name, type, length, nullable, default.
     * @return string        The SQL fragment for use in CREATE/ALTER TABLE.
     */
    private static function columnDef(array $field): string {
        $type    = strtoupper($field['type']);
        $length  = isset($field['length']) && $field['length'] !== null
                   ? '(' . (int) $field['length'] . ')'
                   : '';
        $null    = ($field['nullable'] ?? true) ? 'NULL' : 'NOT NULL';
        $default = '';
        if (array_key_exists('default', $field) && $field['default'] !== null) {
            $rawDefault = (string) $field['default'];
            // SQL keyword defaults (e.g. CURRENT_TIMESTAMP) must not be quoted.
            $keywordDefaults = ['CURRENT_TIMESTAMP', 'NOW()', 'NULL', 'TRUE', 'FALSE'];
            if (in_array(strtoupper($rawDefault), $keywordDefaults, true)) {
                $default = ' DEFAULT ' . strtoupper($rawDefault);
            } else {
                // Use PDO::quote() for safe string literal escaping.
                $quoted  = self::connection()->quote($rawDefault);
                $default = ' DEFAULT ' . $quoted;
            }
        } elseif (($field['nullable'] ?? true)) {
            $default = ' DEFAULT NULL';
        }
        return sprintf('`%s` %s%s %s%s', $field['name'], $type, $length, $null, $default);
    }
}
