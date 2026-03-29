<?php
require_once __DIR__ . '/QueryBuilder.php';

/**
 * Model — base class for all data models.
 *
 * Each subclass must implement:
 *   - getTable()  : string — the MySQL table name
 *   - getFields() : array  — ordered list of field definition arrays
 *
 * Field definition array keys:
 *   name     (string)       column name
 *   type     (string)       MySQL type, e.g. 'VARCHAR', 'INT', 'TINYINT', 'TEXT'
 *   length   (int|null)     column length / precision, or null when not applicable
 *   nullable (bool)         whether the column allows NULL (default true)
 *   default  (mixed|null)   default value, or null for no default
 *
 * Subclasses that use QueryBuilder must also declare:
 *   protected static string $table = 'table_name';
 */
abstract class Model {
    /**
     * Table name for use with the QueryBuilder.
     * Subclasses should override this with their actual table name.
     */
    protected static string $table = '';

    /**
     * Return a fresh QueryBuilder scoped to this model's table.
     *
     * Uses late static binding so subclasses resolve the correct table name.
     *
     * @return QueryBuilder
     */
    public static function query(): QueryBuilder
    {
        return new QueryBuilder(DB::connection(), static::$table);
    }

    /** Return the table name for this model. */
    abstract public function getTable(): string;

    /** Return the ordered array of field definitions for this model. */
    abstract public function getFields(): array;

    /** Ensure the table is created/synced and return a list of all rows. */
    public function all(): array {
        DB::sync($this);
        return DB::query('SELECT * FROM `' . $this->getTable() . '`')->fetchAll();
    }

    /** Find a single row by its primary key. Returns null if not found. */
    public function find(int $id): ?array {
        DB::sync($this);
        $row = DB::query(
            'SELECT * FROM `' . $this->getTable() . '` WHERE id = ?',
            [$id]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Insert a new row and return the new auto-increment id.
     *
     * @param array $data  Associative array of column => value pairs.
     */
    public function insert(array $data): int {
        DB::sync($this);
        $cols   = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
        $places = implode(', ', array_fill(0, count($data), '?'));
        DB::query(
            "INSERT INTO `{$this->getTable()}` ($cols) VALUES ($places)",
            array_values($data)
        );
        return (int) DB::connection()->lastInsertId();
    }

    /**
     * Update a row identified by id.
     *
     * @param int   $id
     * @param array $data  Associative array of column => value pairs to update.
     */
    public function update(int $id, array $data): void {
        DB::sync($this);
        $set    = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        $values = array_values($data);
        $values[] = $id;
        DB::query("UPDATE `{$this->getTable()}` SET $set WHERE id = ?", $values);
    }

    /** Delete a row by id. */
    public function delete(int $id): void {
        DB::sync($this);
        DB::query("DELETE FROM `{$this->getTable()}` WHERE id = ?", [$id]);
    }
}
