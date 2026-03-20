<?php
require_once __DIR__ . '/Model.php';

/**
 * LightsModel — represents smart lights / switches.
 *
 * Table: lights
 */
class LightsModel extends Model {
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
                'name'     => 'location',
                'type'     => 'VARCHAR',
                'length'   => 100,
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
                'name'     => 'updated_at',
                'type'     => 'TIMESTAMP',
                'length'   => null,
                'nullable' => false,
                'default'  => 'CURRENT_TIMESTAMP',
            ],
        ];
    }
}
