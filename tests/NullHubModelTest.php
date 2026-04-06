<?php
/**
 * NullHubModelTest — integration tests for the NullHub model.
 *
 * Verifies table creation, all CRUD methods, last_ping update, and all
 * lookup methods. Every test starts from a clean null_hubs table.
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/models/NullHub.php';

class NullHubModelTest extends BaseTestCase
{
    // ── Setup ─────────────────────────────────────────────────────────────────

    /** @var NullHub */
    private NullHub $model;

    /**
     * Truncate the null_hubs table before every test for a clean slate.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new NullHub();
        DB::connection()->exec('TRUNCATE TABLE `null_hubs`');
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    /**
     * validate() creates the null_hubs table if it does not exist.
     *
     * @return void
     */
    public function testValidateCreatesTable(): void
    {
        DB::connection()->exec('DROP TABLE IF EXISTS `null_hubs`');

        $this->model->validate();

        $this->assertTableExists('null_hubs');
    }

    // ── createNullHub ─────────────────────────────────────────────────────────

    /**
     * createNullHub() inserts a row and returns a valid positive integer id.
     *
     * @return void
     */
    public function testCreateNullHub(): void
    {
        $id = $this->model->createNullHub([
            'mac_address' => 'aa:bb:cc:dd:ee:ff',
            'name'        => 'null pi',
            'url'         => '192.168.86.90',
            'type'        => 'old_hub',
            'server'      => 'pi4b',
            'main'        => 1,
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $count = (int) DB::query('SELECT COUNT(*) FROM `null_hubs`')->fetchColumn();
        $this->assertSame(1, $count);
    }

    // ── findByMac ─────────────────────────────────────────────────────────────

    /**
     * findByMac() returns the correct row for a known MAC address.
     *
     * @return void
     */
    public function testFindByMacReturnsRow(): void
    {
        $id = $this->model->createNullHub([
            'mac_address' => 'aa:bb:cc:dd:ee:ff',
            'name'        => 'null pi',
            'url'         => '192.168.86.90',
            'type'        => 'old_hub',
            'server'      => 'pi4b',
            'main'        => 1,
        ]);

        $row = $this->model->findByMac('aa:bb:cc:dd:ee:ff');

        $this->assertNotNull($row);
        $this->assertSame($id, (int) $row['id']);
        $this->assertSame('null pi', $row['name']);
        $this->assertSame('192.168.86.90', $row['url']);
        $this->assertSame('aa:bb:cc:dd:ee:ff', $row['mac_address']);
    }

    /**
     * findByMac() returns null when the MAC address is not in the database.
     *
     * @return void
     */
    public function testFindByMacReturnsNullForUnknown(): void
    {
        $row = $this->model->findByMac('00:11:22:33:44:55');

        $this->assertNull($row);
    }

    // ── findByUrl ─────────────────────────────────────────────────────────────

    /**
     * findByUrl() returns the correct row for a known URL/IP.
     *
     * @return void
     */
    public function testFindByUrlReturnsRow(): void
    {
        $this->model->createNullHub([
            'mac_address' => 'bb:cc:dd:ee:ff:00',
            'name'        => 'dev',
            'url'         => '192.168.86.202',
            'hub'         => '192.168.86.90',
            'type'        => 'hub',
            'server'      => 'pi3ap',
            'main'        => 0,
        ]);

        $row = $this->model->findByUrl('192.168.86.202');

        $this->assertNotNull($row);
        $this->assertSame('dev', $row['name']);
        $this->assertSame('192.168.86.90', $row['hub']);
    }

    /**
     * findByUrl() returns null when the URL is not in the database.
     *
     * @return void
     */
    public function testFindByUrlReturnsNullForUnknown(): void
    {
        $row = $this->model->findByUrl('10.0.0.1');

        $this->assertNull($row);
    }

    // ── findByHub ─────────────────────────────────────────────────────────────

    /**
     * findByHub() returns all devices associated with a given parent hub IP.
     *
     * @return void
     */
    public function testFindByHubReturnsDevices(): void
    {
        $this->model->createNullHub([
            'mac_address' => 'bb:cc:dd:ee:ff:01',
            'name'        => 'child one',
            'url'         => '192.168.86.201',
            'hub'         => '192.168.86.90',
            'type'        => 'hub',
            'server'      => 'pi3a',
            'main'        => 0,
        ]);
        $this->model->createNullHub([
            'mac_address' => 'bb:cc:dd:ee:ff:02',
            'name'        => 'child two',
            'url'         => '192.168.86.202',
            'hub'         => '192.168.86.90',
            'type'        => 'hub',
            'server'      => 'pi3ap',
            'main'        => 0,
        ]);
        // Another hub not belonging to this parent.
        $this->model->createNullHub([
            'mac_address' => 'cc:dd:ee:ff:00:01',
            'name'        => 'other',
            'url'         => '192.168.86.100',
            'hub'         => '192.168.86.50',
            'type'        => 'hub',
            'server'      => 'pi0',
            'main'        => 0,
        ]);

        $rows = $this->model->findByHub('192.168.86.90');

        $this->assertCount(2, $rows);
    }

    // ── allNullHubs ───────────────────────────────────────────────────────────

    /**
     * allNullHubs() returns all rows in the table.
     *
     * @return void
     */
    public function testAllNullHubsReturnsAllRows(): void
    {
        $this->model->createNullHub([
            'mac_address' => 'aa:bb:cc:dd:ee:01',
            'name'        => 'hub one',
            'url'         => '192.168.1.1',
            'main'        => 1,
        ]);
        $this->model->createNullHub([
            'mac_address' => 'aa:bb:cc:dd:ee:02',
            'name'        => 'hub two',
            'url'         => '192.168.1.2',
            'main'        => 0,
        ]);

        $rows = $this->model->allNullHubs();

        $this->assertCount(2, $rows);
    }

    // ── updateNullHub ─────────────────────────────────────────────────────────

    /**
     * updateNullHub() updates only the provided fields, leaving others intact.
     *
     * @return void
     */
    public function testUpdateNullHub(): void
    {
        $id = $this->model->createNullHub([
            'mac_address' => 'ff:ee:dd:cc:bb:aa',
            'name'        => 'Old Name',
            'url'         => '10.0.0.1',
            'main'        => 0,
        ]);

        $this->model->updateNullHub($id, [
            'url'  => '10.0.0.99',
            'name' => 'New Name',
        ]);

        $row = DB::query(
            'SELECT * FROM `null_hubs` WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('10.0.0.99', $row['url'],         'url must be updated');
        $this->assertSame('New Name', $row['name'],          'name must be updated');
        $this->assertSame('ff:ee:dd:cc:bb:aa', $row['mac_address'], 'mac_address must remain unchanged');
        $this->assertSame('0', (string) $row['main'],        'main must remain unchanged');
    }

    // ── updateLastPing ────────────────────────────────────────────────────────

    /**
     * updateLastPing() sets last_ping to a non-null datetime and sets online = 1.
     *
     * @return void
     */
    public function testUpdateLastPing(): void
    {
        $id = $this->model->createNullHub([
            'mac_address' => 'aa:11:bb:22:cc:33',
            'name'        => 'ping test',
            'url'         => '10.0.0.5',
            'main'        => 0,
            'online'      => 0,
        ]);

        $this->model->updateLastPing($id);

        $row = DB::query(
            'SELECT last_ping, online FROM `null_hubs` WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($row['last_ping'], 'last_ping must be set after updateLastPing');
        $this->assertSame('1', (string) $row['online'], 'online must be 1 after updateLastPing');
    }

    // ── Optional fields ───────────────────────────────────────────────────────

    /**
     * createNullHub() stores all optional fields from the /api/info payload.
     *
     * @return void
     */
    public function testOptionalFieldsAreStored(): void
    {
        $id = $this->model->createNullHub([
            'mac_address' => 'b8:27:eb:b5:b6:7c',
            'name'        => 'dev',
            'url'         => '192.168.86.202',
            'hub'         => '192.168.86.90',
            'type'        => 'hub',
            'server'      => 'pi3ap',
            'main'        => 0,
            'enabled'     => 1,
            'room'        => 0,
            'dev'         => 'dev',
            'hash'        => '3af17efdc976cee4105b97cbc9947908d53420ed',
            'modified'    => '2024-04-17 15:49:25',
            'git'         => 'https://github.com/sophiathekitty/NullHub',
            'setup'       => 'complete',
        ]);

        $row = DB::query(
            'SELECT * FROM `null_hubs` WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('dev', $row['dev']);
        $this->assertSame('3af17efdc976cee4105b97cbc9947908d53420ed', $row['hash']);
        $this->assertSame('2024-04-17 15:49:25', $row['modified']);
        $this->assertSame('https://github.com/sophiathekitty/NullHub', $row['git']);
        $this->assertSame('complete', $row['setup']);
    }
}
