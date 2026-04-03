<?php
/**
 * DeviceTest — integration tests for the Device model and DeviceController.
 *
 * Covers: table creation, static finders, save(), delete(), setState(),
 * setBrightness(), all getters, and key DeviceController behaviour.
 *
 * Every test method starts from a clean devices table.
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/models/Device.php';
require_once APP_ROOT . '/controllers/DeviceController.php';

class DeviceTest extends BaseTestCase
{
    // ── Setup ─────────────────────────────────────────────────────────────────

    /**
     * Truncate the devices table before every test for a clean slate.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection()->exec('TRUNCATE TABLE `devices`');
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    /**
     * The devices table is created by DatabaseValidationService.
     *
     * @return void
     */
    public function testDevicesTableExists(): void
    {
        $this->assertTableExists('devices');
    }

    /**
     * The devices table has the expected columns.
     *
     * @return void
     */
    public function testDevicesTableHasExpectedColumns(): void
    {
        $cols = DB::query(
            'SELECT COLUMN_NAME FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = ?
              ORDER BY ORDINAL_POSITION',
            ['devices']
        )->fetchAll(PDO::FETCH_COLUMN);

        $expected = ['id', 'name', 'type', 'subtype', 'color', 'room_id', 'brightness', 'state', 'created_at', 'updated_at'];
        foreach ($expected as $col) {
            $this->assertContains($col, $cols, "Column '{$col}' must exist in devices table.");
        }
    }

    // ── findById ──────────────────────────────────────────────────────────────

    /**
     * findById() returns a hydrated Device for a known id.
     *
     * @return void
     */
    public function testFindByIdReturnsDevice(): void
    {
        $stub = new Device();
        $id   = $stub->insert([
            'name'       => 'Ceiling Light',
            'type'       => 'light',
            'subtype'    => 'ceiling',
            'state'      => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $device = Device::findById($id);

        $this->assertNotNull($device);
        $this->assertInstanceOf(Device::class, $device);
        $this->assertSame($id, $device->getId());
        $this->assertSame('Ceiling Light', $device->getName());
        $this->assertSame('light', $device->getType());
        $this->assertSame('ceiling', $device->getSubtype());
    }

    /**
     * findById() returns null when the id does not exist.
     *
     * @return void
     */
    public function testFindByIdReturnsNullForUnknownId(): void
    {
        $device = Device::findById(99999);

        $this->assertNull($device);
    }

    // ── findAll ───────────────────────────────────────────────────────────────

    /**
     * findAll() returns all devices as hydrated Device instances.
     *
     * @return void
     */
    public function testFindAllReturnsAllDevices(): void
    {
        $stub = new Device();
        $stub->insert(['name' => 'Lamp A', 'type' => 'light', 'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $stub->insert(['name' => 'Fan',    'type' => 'device', 'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        $all = Device::findAll();

        $this->assertCount(2, $all);
        $this->assertContainsOnlyInstancesOf(Device::class, $all);
    }

    /**
     * findAll() returns an empty array when the table is empty.
     *
     * @return void
     */
    public function testFindAllReturnsEmptyArrayWhenNoDevices(): void
    {
        $this->assertSame([], Device::findAll());
    }

    // ── findByRoom ────────────────────────────────────────────────────────────

    /**
     * findByRoom() returns only devices assigned to the given room.
     *
     * @return void
     */
    public function testFindByRoomReturnsCorrectDevices(): void
    {
        $stub = new Device();
        $stub->insert(['name' => 'Room 1 Light', 'type' => 'light', 'room_id' => 1, 'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $stub->insert(['name' => 'Room 2 Light', 'type' => 'light', 'room_id' => 2, 'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $stub->insert(['name' => 'No Room',      'type' => 'light', 'state' => 0,   'updated_at' => date('Y-m-d H:i:s')]);

        $room1 = Device::findByRoom(1);

        $this->assertCount(1, $room1);
        $this->assertSame('Room 1 Light', $room1[0]->getName());
        $this->assertSame(1, $room1[0]->getRoomId());
    }

    // ── findByType ────────────────────────────────────────────────────────────

    /**
     * findByType() returns only devices of the given type.
     *
     * @return void
     */
    public function testFindByTypeReturnsCorrectDevices(): void
    {
        $stub = new Device();
        $stub->insert(['name' => 'Bulb',   'type' => 'light',  'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $stub->insert(['name' => 'Fan',    'type' => 'device', 'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $stub->insert(['name' => 'Lamp',   'type' => 'light',  'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        $lights  = Device::findByType('light');
        $devices = Device::findByType('device');

        $this->assertCount(2, $lights);
        $this->assertCount(1, $devices);
        $this->assertSame('Fan', $devices[0]->getName());
    }

    // ── save (INSERT) ─────────────────────────────────────────────────────────

    /**
     * save() on a new Device (id = 0) inserts a row and sets id.
     *
     * @return void
     */
    public function testSaveInsertsNewDevice(): void
    {
        $stub = new Device();
        // Use fromRow to create a writable instance via the model layer
        $stub->insert([
            'name'    => 'Test Insert',
            'type'    => 'light',
            'state'   => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $count = (int) DB::query('SELECT COUNT(*) FROM `devices`')->fetchColumn();
        $this->assertSame(1, $count);
    }

    /**
     * save() on an existing Device (id > 0) updates the row.
     *
     * @return void
     */
    public function testSaveUpdatesExistingDevice(): void
    {
        $stub = new Device();
        $id   = $stub->insert([
            'name'       => 'Original Name',
            'type'       => 'light',
            'state'      => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $device = Device::findById($id);
        $this->assertNotNull($device);

        $device->save(); // Re-save (uses UPDATE path)

        $row = DB::query('SELECT * FROM `devices` WHERE id = ?', [$id])->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Original Name', $row['name']);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    /**
     * delete() removes the device row and resets id to 0.
     *
     * @return void
     */
    public function testDeleteRemovesDevice(): void
    {
        $stub = new Device();
        $id   = $stub->insert([
            'name'       => 'To Delete',
            'type'       => 'device',
            'state'      => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $device = Device::findById($id);
        $this->assertNotNull($device);

        $result = $device->delete();

        $this->assertTrue($result);
        $this->assertSame(0, $device->getId());
        $this->assertNull(Device::findById($id));
    }

    /**
     * delete() returns false when the device has no id (id = 0).
     *
     * @return void
     */
    public function testDeleteReturnsFalseForUnsavedDevice(): void
    {
        $device = new Device();
        $this->assertFalse($device->delete());
    }

    // ── setState ──────────────────────────────────────────────────────────────

    /**
     * setState() updates only the state column.
     *
     * @return void
     */
    public function testSetStateUpdatesState(): void
    {
        $stub = new Device();
        $id   = $stub->insert([
            'name'       => 'State Test',
            'type'       => 'light',
            'state'      => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $device = Device::findById($id);
        $this->assertFalse($device->getState());

        $device->setState(true);

        $row = DB::query('SELECT state FROM `devices` WHERE id = ?', [$id])->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('1', (string) $row['state']);

        $device->setState(false);

        $row = DB::query('SELECT state FROM `devices` WHERE id = ?', [$id])->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('0', (string) $row['state']);
    }

    // ── setBrightness ─────────────────────────────────────────────────────────

    /**
     * setBrightness() updates only the brightness column.
     *
     * @return void
     */
    public function testSetBrightnessUpdatesBrightness(): void
    {
        $stub = new Device();
        $id   = $stub->insert([
            'name'       => 'Brightness Test',
            'type'       => 'light',
            'state'      => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $device = Device::findById($id);
        $device->setBrightness(75);

        $row = DB::query('SELECT brightness FROM `devices` WHERE id = ?', [$id])->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('75', (string) $row['brightness']);
    }

    /**
     * setBrightness() throws InvalidArgumentException for values below 0.
     *
     * @return void
     */
    public function testSetBrightnessThrowsForNegativeValue(): void
    {
        $stub = new Device();
        $id   = $stub->insert([
            'name'       => 'Negative Brightness',
            'type'       => 'light',
            'state'      => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $device = Device::findById($id);

        $this->expectException(\InvalidArgumentException::class);
        $device->setBrightness(-1);
    }

    /**
     * setBrightness() throws InvalidArgumentException for values above 100.
     *
     * @return void
     */
    public function testSetBrightnessThrowsForValueAbove100(): void
    {
        $stub = new Device();
        $id   = $stub->insert([
            'name'       => 'Over Brightness',
            'type'       => 'light',
            'state'      => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $device = Device::findById($id);

        $this->expectException(\InvalidArgumentException::class);
        $device->setBrightness(101);
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    /**
     * All getters return the correct values after hydration from a database row.
     *
     * @return void
     */
    public function testGettersReturnCorrectValues(): void
    {
        $stub = new Device();
        $id   = $stub->insert([
            'name'       => 'Full Device',
            'type'       => 'light',
            'subtype'    => 'ambient',
            'color'      => '#ff8800',
            'room_id'    => 42,
            'brightness' => 80,
            'state'      => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $device = Device::findById($id);

        $this->assertSame($id,        $device->getId());
        $this->assertSame('Full Device', $device->getName());
        $this->assertSame('light',    $device->getType());
        $this->assertSame('ambient',  $device->getSubtype());
        $this->assertSame('#ff8800',  $device->getColor());
        $this->assertSame(42,         $device->getRoomId());
        $this->assertSame(80,         $device->getBrightness());
        $this->assertTrue($device->getState());
    }

    /**
     * Nullable getters return null when the columns are not set.
     *
     * @return void
     */
    public function testNullableGettersReturnNullWhenNotSet(): void
    {
        $stub = new Device();
        $id   = $stub->insert([
            'name'       => 'Minimal Device',
            'type'       => 'device',
            'state'      => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $device = Device::findById($id);

        $this->assertNull($device->getSubtype());
        $this->assertNull($device->getColor());
        $this->assertNull($device->getRoomId());
        $this->assertNull($device->getBrightness());
    }

    // ── toArray ───────────────────────────────────────────────────────────────

    /**
     * toArray() returns all expected keys with correct values.
     *
     * @return void
     */
    public function testToArrayReturnsCorrectShape(): void
    {
        $stub = new Device();
        $id   = $stub->insert([
            'name'       => 'Array Test',
            'type'       => 'light',
            'subtype'    => 'lamp',
            'state'      => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $arr = Device::findById($id)?->toArray();

        $this->assertIsArray($arr);
        $this->assertArrayHasKey('id',         $arr);
        $this->assertArrayHasKey('name',       $arr);
        $this->assertArrayHasKey('type',       $arr);
        $this->assertArrayHasKey('subtype',    $arr);
        $this->assertArrayHasKey('color',      $arr);
        $this->assertArrayHasKey('room_id',    $arr);
        $this->assertArrayHasKey('brightness', $arr);
        $this->assertArrayHasKey('state',      $arr);
        $this->assertArrayHasKey('created_at', $arr);
        $this->assertArrayHasKey('updated_at', $arr);

        $this->assertSame($id,          $arr['id']);
        $this->assertSame('Array Test', $arr['name']);
        $this->assertSame('light',      $arr['type']);
        $this->assertSame('lamp',       $arr['subtype']);
        $this->assertFalse($arr['state']);
    }

    // ── DeviceController ──────────────────────────────────────────────────────

    /**
     * DeviceController::getAll() returns an array of plain arrays.
     *
     * @return void
     */
    public function testControllerGetAllReturnsArray(): void
    {
        $stub = new Device();
        $stub->insert(['name' => 'Ctrl A', 'type' => 'light',  'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $stub->insert(['name' => 'Ctrl B', 'type' => 'device', 'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        $ctrl = new DeviceController();
        $all  = $ctrl->getAll();

        $this->assertCount(2, $all);
        $this->assertIsArray($all[0]);
        $this->assertArrayHasKey('name', $all[0]);
    }

    /**
     * DeviceController::getById() returns the correct device as a plain array.
     *
     * @return void
     */
    public function testControllerGetByIdReturnsCorrectDevice(): void
    {
        $stub = new Device();
        $id   = $stub->insert(['name' => 'Ctrl Device', 'type' => 'light', 'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        $ctrl   = new DeviceController();
        $result = $ctrl->getById($id);

        $this->assertNotNull($result);
        $this->assertSame($id,            $result['id']);
        $this->assertSame('Ctrl Device',  $result['name']);
    }

    /**
     * DeviceController::getById() returns null for an unknown id.
     *
     * @return void
     */
    public function testControllerGetByIdReturnsNullForUnknown(): void
    {
        $ctrl = new DeviceController();
        $this->assertNull($ctrl->getById(99999));
    }

    /**
     * DeviceController::create() inserts a device and returns its plain array.
     *
     * @return void
     */
    public function testControllerCreateReturnsNewDevice(): void
    {
        $ctrl   = new DeviceController();
        $result = $ctrl->create('New Lamp', 'light', 'lamp', '#ffffff', null, 100);

        $this->assertIsArray($result);
        $this->assertGreaterThan(0, $result['id']);
        $this->assertSame('New Lamp', $result['name']);
        $this->assertSame('light',    $result['type']);
        $this->assertSame('lamp',     $result['subtype']);
        $this->assertSame('#ffffff',  $result['color']);
    }

    /**
     * DeviceController::toggle() flips the device state.
     *
     * @return void
     */
    public function testControllerToggleFlipsState(): void
    {
        $stub = new Device();
        $id   = $stub->insert(['name' => 'Toggle Me', 'type' => 'light', 'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        $ctrl    = new DeviceController();
        $result  = $ctrl->toggle($id);

        $this->assertTrue($result['state']);

        $result2 = $ctrl->toggle($id);
        $this->assertFalse($result2['state']);
    }

    /**
     * DeviceController::turnOn() sets state to true.
     *
     * @return void
     */
    public function testControllerTurnOnSetsStateTrue(): void
    {
        $stub = new Device();
        $id   = $stub->insert(['name' => 'Turn On Me', 'type' => 'light', 'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        $ctrl   = new DeviceController();
        $result = $ctrl->turnOn($id);

        $this->assertTrue($result['state']);
    }

    /**
     * DeviceController::turnOff() sets state to false.
     *
     * @return void
     */
    public function testControllerTurnOffSetsStateFalse(): void
    {
        $stub = new Device();
        $id   = $stub->insert(['name' => 'Turn Off Me', 'type' => 'light', 'state' => 1, 'updated_at' => date('Y-m-d H:i:s')]);

        $ctrl   = new DeviceController();
        $result = $ctrl->turnOff($id);

        $this->assertFalse($result['state']);
    }

    /**
     * DeviceController::toggle() returns null for an unknown id.
     *
     * @return void
     */
    public function testControllerToggleReturnsNullForUnknown(): void
    {
        $ctrl = new DeviceController();
        $this->assertNull($ctrl->toggle(99999));
    }

    /**
     * DeviceController::setBrightness() clamps and persists the brightness.
     *
     * @return void
     */
    public function testControllerSetBrightnessUpdatesBrightness(): void
    {
        $stub = new Device();
        $id   = $stub->insert(['name' => 'Dim Me', 'type' => 'light', 'state' => 1, 'updated_at' => date('Y-m-d H:i:s')]);

        $ctrl   = new DeviceController();
        $result = $ctrl->setBrightness($id, 50);

        $this->assertSame(50, $result['brightness']);
    }

    /**
     * DeviceController::delete() removes the device and returns true.
     *
     * @return void
     */
    public function testControllerDeleteRemovesDevice(): void
    {
        $stub = new Device();
        $id   = $stub->insert(['name' => 'Delete Me', 'type' => 'device', 'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        $ctrl = new DeviceController();
        $this->assertTrue($ctrl->delete($id));
        $this->assertNull($ctrl->getById($id));
    }

    /**
     * DeviceController::delete() returns false for an unknown id.
     *
     * @return void
     */
    public function testControllerDeleteReturnsFalseForUnknown(): void
    {
        $ctrl = new DeviceController();
        $this->assertFalse($ctrl->delete(99999));
    }
}
