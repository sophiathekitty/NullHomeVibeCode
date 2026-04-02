<?php
/**
 * WemoModelTest — integration tests for the Wemo model.
 *
 * Verifies table creation, all CRUD methods, state update, and MAC-based lookup.
 * Every test starts from a clean wemos table.
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/models/Wemo.php';

class WemoModelTest extends BaseTestCase
{
    // ── Setup ─────────────────────────────────────────────────────────────────

    /** @var Wemo */
    private Wemo $model;

    /**
     * Truncate the wemos table before every test for a clean slate.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Wemo();
        DB::connection()->exec('TRUNCATE TABLE `wemos`');
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    /**
     * validate() creates the wemos table if it does not exist.
     *
     * Drops the table, calls validate(), and asserts it is recreated.
     *
     * @return void
     */
    public function testValidateCreatesTable(): void
    {
        DB::connection()->exec('DROP TABLE IF EXISTS `wemos`');

        $this->model->validate();

        $this->assertTableExists('wemos');
    }

    // ── findByMac ─────────────────────────────────────────────────────────────

    /**
     * findByMac() returns the correct row for a known MAC address.
     *
     * @return void
     */
    public function testFindByMacReturnsRow(): void
    {
        $id = $this->model->createWemo([
            'name'        => 'Desk Lamp',
            'mac_address' => 'aa:bb:cc:dd:ee:ff',
            'ip_address'  => '192.168.1.101',
            'port'        => 49153,
            'state'       => 0,
        ]);

        $row = $this->model->findByMac('aa:bb:cc:dd:ee:ff');

        $this->assertNotNull($row);
        $this->assertSame($id, (int) $row['id']);
        $this->assertSame('Desk Lamp', $row['name']);
        $this->assertSame('aa:bb:cc:dd:ee:ff', $row['mac_address']);
        $this->assertSame('192.168.1.101', $row['ip_address']);
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

    // ── createWemo ────────────────────────────────────────────────────────────

    /**
     * createWemo() inserts a row and returns a valid positive integer id.
     *
     * @return void
     */
    public function testCreateWemo(): void
    {
        $id = $this->model->createWemo([
            'name'        => 'Floor Lamp',
            'mac_address' => '11:22:33:44:55:66',
            'ip_address'  => '192.168.1.50',
            'port'        => 49153,
            'state'       => 0,
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $count = (int) DB::query('SELECT COUNT(*) FROM `wemos`')->fetchColumn();
        $this->assertSame(1, $count);
    }

    // ── updateState ───────────────────────────────────────────────────────────

    /**
     * updateState() sets the state column and updates last_checked to a
     * non-null datetime value.
     *
     * @return void
     */
    public function testUpdateState(): void
    {
        $id = $this->model->createWemo([
            'name'        => 'Kitchen Light',
            'mac_address' => 'aa:11:bb:22:cc:33',
            'ip_address'  => '10.0.0.5',
            'port'        => 49153,
            'state'       => 0,
        ]);

        $this->model->updateState($id, 1);

        $row = DB::query(
            'SELECT state, last_checked FROM `wemos` WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('1', (string) $row['state'], 'state must be 1 after updateState(id, 1)');
        $this->assertNotNull($row['last_checked'], 'last_checked must be set after a successful update');
    }

    // ── updateWemo ────────────────────────────────────────────────────────────

    /**
     * updateWemo() updates only the provided fields, leaving other columns intact.
     *
     * @return void
     */
    public function testUpdateWemo(): void
    {
        $id = $this->model->createWemo([
            'name'        => 'Old Name',
            'mac_address' => 'ff:ee:dd:cc:bb:aa',
            'ip_address'  => '10.0.0.1',
            'port'        => 49153,
            'state'       => 0,
        ]);

        $this->model->updateWemo($id, [
            'ip_address' => '10.0.0.99',
            'name'       => 'New Name',
        ]);

        $row = DB::query(
            'SELECT * FROM `wemos` WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('10.0.0.99', $row['ip_address'], 'ip_address must be updated');
        $this->assertSame('New Name', $row['name'],         'name must be updated');
        $this->assertSame('ff:ee:dd:cc:bb:aa', $row['mac_address'], 'mac_address must remain unchanged');
        $this->assertSame('0', (string) $row['state'],      'state must remain unchanged');
    }
}
