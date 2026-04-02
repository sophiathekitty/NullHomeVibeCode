<?php
require_once __DIR__ . '/Model.php';

/**
 * LightsModel — represents smart lights / switches.
 *
 * Table: lights
 */
class LightsModel extends Model {
    protected static string $table = 'lights';

    public function getTable(): string {
        return 'lights';
    }

    public function getFields(): array {
        return [
            [
                'name'     => 'name',
                'type'     => 'VARCHAR',
                'length'   => 100,
                'nullable' => false,
                'default'  => null,
            ],
            [
                'name'     => 'type',
                'type'     => 'VARCHAR',
                'length'   => 50,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'subtype',
                'type'     => 'VARCHAR',
                'length'   => 50,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'room_id',
                'type'     => 'INT UNSIGNED',
                'length'   => null,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'state',
                'type'     => 'TINYINT',
                'length'   => 1,
                'nullable' => false,
                'default'  => '0',
            ],
            [
                'name'     => 'brightness',
                'type'     => 'TINYINT',
                'length'   => 3,
                'nullable' => false,
                'default'  => '100',
            ],
            [
                'name'     => 'last_state_change',
                'type'     => 'TIMESTAMP',
                'length'   => null,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'updated_at',
                'type'     => 'TIMESTAMP',
                'length'   => null,
                'nullable' => false,
                'default'  => 'CURRENT_TIMESTAMP',
            ],
        ];
    }

    /**
     * Insert a new light record and return the new auto-increment id.
     *
     * Accepts a raw associative data array, giving callers full control over
     * the fields set. This is used by WemoDriver when creating a linked light
     * on first device discovery.
     *
     * @param array<string, mixed> $data Associative array of column => value pairs.
     * @return int The new auto-increment id.
     * @throws \PDOException If the database query fails.
     */
    public function create(array $data): int
    {
        return $this->insert($data);
    }

    /**
     * Update the state for the given light id.
     *
     * Sets state, last_state_change, and updated_at simultaneously so all
     * timestamp columns stay consistent.
     *
     * @param int $id    Primary key of the record to update.
     * @param int $state New state value: 1 = on, 0 = off.
     * @return void
     * @throws \PDOException If the database query fails.
     */
    public function updateState(int $id, int $state): void
    {
        $now = date('Y-m-d H:i:s');
        $this->update($id, [
            'state'             => $state,
            'last_state_change' => $now,
            'updated_at'        => $now,
        ]);
    }
}
