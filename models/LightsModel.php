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
}
