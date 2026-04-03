<?php
/**
 * RoomTest — integration tests for Room, RoomNeighbor, RoomLighting, and RoomController.
 *
 * Verifies data modelling, state reporting, and controller business logic.
 * Every test starts from a seeded state established in setUp() so tests are
 * fully independent of each other.
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/models/Model.php';
require_once APP_ROOT . '/models/Room.php';
require_once APP_ROOT . '/models/RoomNeighbor.php';
require_once APP_ROOT . '/models/Device.php';
require_once APP_ROOT . '/modules/RoomLighting.php';
require_once APP_ROOT . '/controllers/RoomController.php';

class RoomTest extends BaseTestCase
{
    // ── Seed helpers ──────────────────────────────────────────────────────────

    /** IDs assigned during setUp(). */
    private int $basementId;
    private int $stairsId;
    private int $hallwayId;

    /**
     * Truncate rooms, room_neighbors, and devices before every test and
     * re-seed a minimal known state.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Clean slate — foreign-key order: devices first, then neighbors, then rooms.
        DB::connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
        DB::connection()->exec('TRUNCATE TABLE `devices`');
        DB::connection()->exec('TRUNCATE TABLE `room_neighbors`');
        DB::connection()->exec('TRUNCATE TABLE `rooms`');
        DB::connection()->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Seed three rooms.
        $model = new Room();
        $this->basementId = $model->insert(['name' => 'basement', 'display_name' => 'Basement']);
        $this->stairsId   = $model->insert(['name' => 'stairs',   'display_name' => 'Stairs']);
        $this->hallwayId  = $model->insert(['name' => 'hallway',  'display_name' => 'Hallway']);
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    /** The rooms table exists after schema setup. */
    public function testRoomsTableExists(): void
    {
        $this->assertTableExists('rooms');
    }

    /** The room_neighbors table exists after schema setup. */
    public function testRoomNeighborsTableExists(): void
    {
        $this->assertTableExists('room_neighbors');
    }

    /** The devices table has the expected columns. */
    public function testDevicesTableHasCorrectColumns(): void
    {
        $cols  = DB::query('SHOW COLUMNS FROM `devices`')->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'Field');

        $this->assertContains('type',       $names, 'devices must have type column');
        $this->assertContains('subtype',    $names, 'devices must have subtype column');
        $this->assertContains('room_id',    $names, 'devices must have room_id column');
        $this->assertContains('color',      $names, 'devices must have color column');
        $this->assertContains('brightness', $names, 'devices must have brightness column');
        $this->assertContains('updated_at', $names, 'devices must have updated_at column');
    }

    // ── Room model ────────────────────────────────────────────────────────────

    /** findById() returns a hydrated Room instance with correct properties. */
    public function testRoomFindById(): void
    {
        $room = Room::findById($this->basementId);

        $this->assertInstanceOf(Room::class, $room);
        $this->assertSame($this->basementId, $room->id);
        $this->assertSame('basement', $room->name);
        $this->assertSame('Basement', $room->display_name);
    }

    /** findById() returns null for a non-existent id. */
    public function testRoomFindByIdReturnsNullForMissing(): void
    {
        $this->assertNull(Room::findById(99999));
    }

    /** allRooms() returns all seeded rooms as Room instances. */
    public function testRoomAllRooms(): void
    {
        $rooms = Room::allRooms();

        $this->assertCount(3, $rooms);
        foreach ($rooms as $r) {
            $this->assertInstanceOf(Room::class, $r);
        }
        $names = array_map(fn(Room $r) => $r->name, $rooms);
        $this->assertContains('basement', $names);
        $this->assertContains('stairs',   $names);
        $this->assertContains('hallway',  $names);
    }

    /** Room::insert() and find() round-trip correctly. */
    public function testRoomInsertAndFind(): void
    {
        $model = new Room();
        $id    = $model->insert(['name' => 'garage', 'display_name' => 'Garage']);

        $room = Room::findById($id);

        $this->assertNotNull($room);
        $this->assertSame('garage', $room->name);
        $this->assertSame('Garage', $room->display_name);
    }

    /** Room::update() via Model::update() persists changes. */
    public function testRoomUpdate(): void
    {
        (new Room())->update($this->basementId, ['display_name' => 'The Basement']);
        $room = Room::findById($this->basementId);

        $this->assertSame('The Basement', $room->display_name);
    }

    /** Room::delete() removes the row. */
    public function testRoomDelete(): void
    {
        (new Room())->delete($this->basementId);
        $this->assertNull(Room::findById($this->basementId));
    }

    // ── Room::devices() ───────────────────────────────────────────────────────

    /** Room::devices() returns only devices with type='light' for the given room. */
    public function testRoomDevicesFiltersCorrectly(): void
    {
        $deviceStub = new Device();

        // Two light-type devices in basement.
        $deviceStub->insert([
            'name'       => 'basement_lamp',
            'type'       => 'light',
            'subtype'    => 'lamp',
            'room_id'    => $this->basementId,
            'state'      => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $deviceStub->insert([
            'name'       => 'basement_ambient',
            'type'       => 'light',
            'subtype'    => 'ambient',
            'room_id'    => $this->basementId,
            'state'      => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        // One light device in stairs — should NOT appear in basement's devices.
        $deviceStub->insert([
            'name'       => 'stairs_light',
            'type'       => 'light',
            'room_id'    => $this->stairsId,
            'state'      => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        // Non-light device type in basement — should NOT appear.
        $deviceStub->insert([
            'name'       => 'basement_fan',
            'type'       => 'device',
            'room_id'    => $this->basementId,
            'state'      => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $room    = Room::findById($this->basementId);
        $devices = $room->devices();

        $this->assertCount(2, $devices);
        $deviceNames = array_column($devices, 'name');
        $this->assertContains('basement_lamp',    $deviceNames);
        $this->assertContains('basement_ambient', $deviceNames);
    }

    // ── Room::neighbors() ─────────────────────────────────────────────────────

    /** Room::neighbors() returns correct neighbor rooms. */
    public function testRoomNeighbors(): void
    {
        RoomNeighbor::link($this->basementId, $this->stairsId);
        RoomNeighbor::link($this->basementId, $this->hallwayId);

        $basement  = Room::findById($this->basementId);
        $neighbors = $basement->neighbors();

        $this->assertCount(2, $neighbors);
        foreach ($neighbors as $n) {
            $this->assertInstanceOf(Room::class, $n);
        }
        $ids = array_map(fn(Room $r) => $r->id, $neighbors);
        $this->assertContains($this->stairsId,  $ids);
        $this->assertContains($this->hallwayId, $ids);
        $this->assertNotContains($this->basementId, $ids);
    }

    /** Room::neighbors() returns an empty array when no neighbors are linked. */
    public function testRoomNeighborsEmpty(): void
    {
        $basement  = Room::findById($this->basementId);
        $this->assertSame([], $basement->neighbors());
    }

    // ── RoomNeighbor model ────────────────────────────────────────────────────

    /** link() stores the pair with lower id as room_id regardless of arg order. */
    public function testRoomNeighborLinkOrdersIds(): void
    {
        // Pass higher id first — should still store lower as room_id.
        $link = RoomNeighbor::link($this->stairsId, $this->basementId);

        $this->assertSame(min($this->stairsId, $this->basementId), $link->room_id);
        $this->assertSame(max($this->stairsId, $this->basementId), $link->neighbor_id);
    }

    /** link() with already-linked pair returns existing record without duplicate. */
    public function testRoomNeighborLinkIdempotent(): void
    {
        RoomNeighbor::link($this->basementId, $this->stairsId);
        RoomNeighbor::link($this->basementId, $this->stairsId); // should not throw

        $count = RoomNeighbor::query()
            ->where('room_id', min($this->basementId, $this->stairsId))
            ->where('neighbor_id', max($this->basementId, $this->stairsId))
            ->count();

        $this->assertSame(1, $count);
    }

    /** link() throws InvalidArgumentException when both ids are the same. */
    public function testRoomNeighborLinkSelfThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RoomNeighbor::link($this->basementId, $this->basementId);
    }

    /** areNeighbors() returns true after link(), regardless of arg order. */
    public function testRoomNeighborAreNeighbors(): void
    {
        $this->assertFalse(RoomNeighbor::areNeighbors($this->basementId, $this->stairsId));

        RoomNeighbor::link($this->basementId, $this->stairsId);

        $this->assertTrue(RoomNeighbor::areNeighbors($this->basementId, $this->stairsId));
        $this->assertTrue(RoomNeighbor::areNeighbors($this->stairsId,   $this->basementId));
    }

    /** unlink() removes the relationship and returns true; returns false when not linked. */
    public function testRoomNeighborUnlink(): void
    {
        RoomNeighbor::link($this->basementId, $this->stairsId);

        $removed = RoomNeighbor::unlink($this->basementId, $this->stairsId);
        $this->assertTrue($removed);
        $this->assertFalse(RoomNeighbor::areNeighbors($this->basementId, $this->stairsId));

        // Second unlink on an already-removed pair returns false.
        $this->assertFalse(RoomNeighbor::unlink($this->basementId, $this->stairsId));
    }

    // ── RoomLighting service ──────────────────────────────────────────────────

    /**
     * isBright() returns false when there are no lights in the room.
     */
    public function testIsBrightFalseWithNoLights(): void
    {
        $service = new RoomLighting();
        $room    = Room::findById($this->basementId);

        $this->assertFalse($service->isBright($room));
    }

    /** isBright() returns false when all devices are off. */
    public function testIsBrightFalseWhenAllOff(): void
    {
        (new Device())->insert([
            'name'       => 'basement_lamp',
            'type'       => 'light',
            'room_id'    => $this->basementId,
            'state'      => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $service = new RoomLighting();
        $room    = Room::findById($this->basementId);

        $this->assertFalse($service->isBright($room));
    }

    /** isBright() returns true when at least one device is on. */
    public function testIsBrightTrueWhenOneLightOn(): void
    {
        $d = new Device();
        $d->insert(['name' => 'b_lamp', 'type' => 'light', 'room_id' => $this->basementId, 'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $d->insert(['name' => 'b_amb',  'type' => 'light', 'room_id' => $this->basementId, 'state' => 1, 'updated_at' => date('Y-m-d H:i:s')]);

        $service = new RoomLighting();
        $room    = Room::findById($this->basementId);

        $this->assertTrue($service->isBright($room));
    }

    /** isBright() ignores non-light devices (type != 'light'). */
    public function testIsBrightIgnoresNonLightDevices(): void
    {
        (new Device())->insert([
            'name'       => 'fan',
            'type'       => 'device',
            'room_id'    => $this->basementId,
            'state'      => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $service = new RoomLighting();
        $room    = Room::findById($this->basementId);

        $this->assertFalse($service->isBright($room));
    }

    /** secondsSinceStateChange() returns 0 when no lights are associated. */
    public function testSecondsSinceStateChangeZeroNoLights(): void
    {
        $service = new RoomLighting();
        $room    = Room::findById($this->basementId);

        $this->assertSame(0, $service->secondsSinceStateChange($room));
    }

    /** secondsSinceStateChange() returns a positive integer after a state change. */
    public function testSecondsSinceStateChangePositive(): void
    {
        $ts = date('Y-m-d H:i:s', time() - 120); // 2 minutes ago

        (new Device())->insert([
            'name'       => 'b_lamp',
            'type'       => 'light',
            'room_id'    => $this->basementId,
            'state'      => 1,
            'updated_at' => $ts,
        ]);

        $service = new RoomLighting();
        $room    = Room::findById($this->basementId);
        $seconds = $service->secondsSinceStateChange($room);

        // Allow a small window for test execution time.
        $this->assertGreaterThanOrEqual(115, $seconds);
        $this->assertLessThanOrEqual(125, $seconds);
    }

    /** brightNeighborCount() returns 0 when no neighbors are linked. */
    public function testBrightNeighborCountZeroNoNeighbors(): void
    {
        $service = new RoomLighting();
        $room    = Room::findById($this->basementId);

        $this->assertSame(0, $service->brightNeighborCount($room));
    }

    /** brightNeighborCount() counts only bright neighbor rooms. */
    public function testBrightNeighborCount(): void
    {
        RoomNeighbor::link($this->basementId, $this->stairsId);
        RoomNeighbor::link($this->basementId, $this->hallwayId);

        $d = new Device();
        // Stairs has a device ON.
        $d->insert(['name' => 's_light', 'type' => 'light', 'room_id' => $this->stairsId,  'state' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        // Hallway devices are OFF.
        $d->insert(['name' => 'h_light', 'type' => 'light', 'room_id' => $this->hallwayId, 'state' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        $service = new RoomLighting();
        $room    = Room::findById($this->basementId);

        $this->assertSame(1, $service->brightNeighborCount($room));
    }

    /** secondsSinceNeighborStateChange() returns 0 when no neighbor lights exist. */
    public function testSecondsSinceNeighborStateChangeZero(): void
    {
        RoomNeighbor::link($this->basementId, $this->stairsId);

        $service = new RoomLighting();
        $room    = Room::findById($this->basementId);

        $this->assertSame(0, $service->secondsSinceNeighborStateChange($room));
    }

    /** secondsSinceNeighborStateChange() derives from MAX(updated_at) of neighbor devices. */
    public function testSecondsSinceNeighborStateChange(): void
    {
        RoomNeighbor::link($this->basementId, $this->stairsId);

        $ts = date('Y-m-d H:i:s', time() - 60); // 60 seconds ago
        (new Device())->insert([
            'name'       => 's_light',
            'type'       => 'light',
            'room_id'    => $this->stairsId,
            'state'      => 1,
            'updated_at' => $ts,
        ]);

        $service = new RoomLighting();
        $room    = Room::findById($this->basementId);
        $seconds = $service->secondsSinceNeighborStateChange($room);

        $this->assertGreaterThanOrEqual(55, $seconds);
        $this->assertLessThanOrEqual(65, $seconds);
    }

    /** getRoomState() returns the expected array shape. */
    public function testGetRoomStateShape(): void
    {
        $service = new RoomLighting();
        $room    = Room::findById($this->basementId);
        $state   = $service->getRoomState($room);

        $this->assertArrayHasKey('is_bright',                           $state);
        $this->assertArrayHasKey('seconds_since_state_change',          $state);
        $this->assertArrayHasKey('bright_neighbor_count',               $state);
        $this->assertArrayHasKey('seconds_since_neighbor_state_change', $state);
        $this->assertIsBool($state['is_bright']);
        $this->assertIsInt($state['seconds_since_state_change']);
        $this->assertIsInt($state['bright_neighbor_count']);
        $this->assertIsInt($state['seconds_since_neighbor_state_change']);
    }

    /** getNeighborStates() returns one pair per neighbor with 'room' and 'state' keys. */
    public function testGetNeighborStates(): void
    {
        RoomNeighbor::link($this->basementId, $this->stairsId);

        $service = new RoomLighting();
        $room    = Room::findById($this->basementId);
        $pairs   = $service->getNeighborStates($room);

        $this->assertCount(1, $pairs);
        $this->assertArrayHasKey('room',  $pairs[0]);
        $this->assertArrayHasKey('state', $pairs[0]);
        $this->assertInstanceOf(Room::class, $pairs[0]['room']);
        $this->assertSame($this->stairsId, $pairs[0]['room']->id);
        $this->assertArrayHasKey('is_bright', $pairs[0]['state']);
    }

    // ── RoomController ────────────────────────────────────────────────────────

    private function makeController(): RoomController
    {
        return new RoomController(new RoomLighting());
    }

    /** index() returns all rooms with state keys. */
    public function testControllerIndex(): void
    {
        $rooms = $this->makeController()->index();

        $this->assertCount(3, $rooms);
        foreach ($rooms as $r) {
            $this->assertArrayHasKey('id',           $r);
            $this->assertArrayHasKey('name',         $r);
            $this->assertArrayHasKey('is_bright',    $r);
            $this->assertArrayHasKey('bright_neighbor_count', $r);
        }
    }

    /** show() returns room data merged with state, null for missing id. */
    public function testControllerShow(): void
    {
        $result = $this->makeController()->show($this->basementId);

        $this->assertNotNull($result);
        $this->assertSame($this->basementId, $result['id']);
        $this->assertSame('basement',        $result['name']);
        $this->assertArrayHasKey('is_bright', $result);

        $this->assertNull($this->makeController()->show(99999));
    }

    /** store() creates a room and returns it with state. */
    public function testControllerStore(): void
    {
        $result = $this->makeController()->store('garage', 'Garage');

        $this->assertNotNull($result);
        $this->assertSame('garage', $result['name']);
        $this->assertSame('Garage', $result['display_name']);
        $this->assertArrayHasKey('is_bright', $result);
    }

    /** update() modifies the room and returns updated data, null for missing id. */
    public function testControllerUpdate(): void
    {
        $result = $this->makeController()->update($this->basementId, ['display_name' => 'The Basement']);

        $this->assertNotNull($result);
        $this->assertSame('The Basement', $result['display_name']);

        $this->assertNull($this->makeController()->update(99999, ['display_name' => 'X']));
    }

    /** destroy() deletes the room and returns true; false for missing id. */
    public function testControllerDestroy(): void
    {
        $this->assertTrue($this->makeController()->destroy($this->basementId));
        $this->assertNull(Room::findById($this->basementId));

        $this->assertFalse($this->makeController()->destroy(99999));
    }

    /** devices() returns formatted device rows with is_on; null for missing room. */
    public function testControllerDevices(): void
    {
        (new Device())->insert([
            'name'       => 'b_lamp',
            'type'       => 'light',
            'subtype'    => 'lamp',
            'room_id'    => $this->basementId,
            'state'      => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->makeController()->devices($this->basementId);

        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('is_on', $result[0]);
        $this->assertTrue($result[0]['is_on']);

        $this->assertNull($this->makeController()->devices(99999));
    }

    /** neighbors() returns neighbor rooms with their state; null for missing room. */
    public function testControllerNeighbors(): void
    {
        RoomNeighbor::link($this->basementId, $this->stairsId);

        $result = $this->makeController()->neighbors($this->basementId);

        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertSame($this->stairsId, $result[0]['id']);
        $this->assertArrayHasKey('is_bright',                 $result[0]);
        $this->assertArrayHasKey('seconds_since_state_change', $result[0]);

        $this->assertNull($this->makeController()->neighbors(99999));
    }

    /** linkNeighbor() creates the relationship and returns ids; null if room missing. */
    public function testControllerLinkNeighbor(): void
    {
        $result = $this->makeController()->linkNeighbor($this->basementId, $this->stairsId);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('room_id',     $result);
        $this->assertArrayHasKey('neighbor_id', $result);
        $this->assertTrue(RoomNeighbor::areNeighbors($this->basementId, $this->stairsId));

        // Missing room returns null.
        $this->assertNull($this->makeController()->linkNeighbor(99999, $this->stairsId));
    }

    /** unlinkNeighbor() removes the relationship and returns correct booleans. */
    public function testControllerUnlinkNeighbor(): void
    {
        RoomNeighbor::link($this->basementId, $this->stairsId);

        $this->assertTrue($this->makeController()->unlinkNeighbor($this->basementId, $this->stairsId));
        $this->assertFalse(RoomNeighbor::areNeighbors($this->basementId, $this->stairsId));

        // Second unlink returns false.
        $this->assertFalse($this->makeController()->unlinkNeighbor($this->basementId, $this->stairsId));
    }
}
