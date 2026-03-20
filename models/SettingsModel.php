<?php
require_once __DIR__ . '/Model.php';

/**
 * SettingsModel — a generic key/value store for application settings.
 *
 * Table: settings
 */
class SettingsModel extends Model {
    public function getTable(): string {
        return 'settings';
    }

    public function getFields(): array {
        return [
            [
                'name'     => 'key',
                'type'     => 'VARCHAR',
                'length'   => 100,
                'nullable' => false,
                'default'  => null,
            ],
            [
                'name'     => 'value',
                'type'     => 'TEXT',
                'length'   => null,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'label',
                'type'     => 'VARCHAR',
                'length'   => 150,
                'nullable' => true,
                'default'  => null,
            ],
        ];
    }

    /** Retrieve a setting value by key. Returns null if not found. */
    public function get(string $key): ?string {
        DB::sync($this);
        $row = DB::query(
            'SELECT value FROM `settings` WHERE `key` = ? LIMIT 1',
            [$key]
        )->fetch();
        return $row ? $row['value'] : null;
    }

    /** Insert or update a setting by key. */
    public function set(string $key, string $value): void {
        DB::sync($this);
        $existing = DB::query(
            'SELECT id FROM `settings` WHERE `key` = ? LIMIT 1',
            [$key]
        )->fetch();
        if ($existing) {
            $this->update((int) $existing['id'], ['value' => $value]);
        } else {
            $this->insert(['key' => $key, 'value' => $value]);
        }
    }
}
