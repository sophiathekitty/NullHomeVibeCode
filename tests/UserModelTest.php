<?php
/**
 * UserModelTest — integration tests for the User model.
 *
 * Verifies table creation, static factory methods, and instance methods.
 * Every test starts from a clean known state established in setUp().
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/models/User.php';
require_once APP_ROOT . '/models/UserSession.php';

class UserModelTest extends BaseTestCase
{
    /**
     * Truncate users (and sessions via FK) before every test, then re-insert
     * the localhost seed row so a baseline always exists.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
        DB::connection()->exec('TRUNCATE TABLE `sessions`');
        DB::connection()->exec('TRUNCATE TABLE `users`');
        DB::connection()->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Re-insert the localhost seed row with a known id.
        DB::query(
            'INSERT INTO `users` (`id`, `name`, `role`, `mac_address`, `show_admin_ui`) VALUES (1, ?, ?, NULL, 0)',
            ['localhost', 'device']
        );
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    /** The users table exists after schema setup. */
    public function testUsersTableExists(): void
    {
        $this->assertTableExists('users');
    }

    /** The sessions table exists after schema setup. */
    public function testSessionsTableExists(): void
    {
        $this->assertTableExists('sessions');
    }

    // ── findById ──────────────────────────────────────────────────────────────

    /** findById(1) returns a User with name='localhost' and role='device'. */
    public function testFindByIdReturnsLocalhostSeedRow(): void
    {
        $user = User::findById(1);

        $this->assertNotNull($user);
        $this->assertSame('localhost', $user->getName());
        $this->assertSame('device', $user->getRole());
    }

    /** findById(99999) returns null. */
    public function testFindByIdReturnsNullForUnknownId(): void
    {
        $this->assertNull(User::findById(99999));
    }

    // ── findHumans ────────────────────────────────────────────────────────────

    /** findHumans() excludes rows where role='device' and orders by name ASC. */
    public function testFindHumansExcludesDeviceAndOrdersByName(): void
    {
        $stub = new User();
        $stub->insert(['name' => 'Zara',   'role' => 'resident', 'show_admin_ui' => 0]);
        $stub->insert(['name' => 'Alex',   'role' => 'admin',    'show_admin_ui' => 1]);
        $stub->insert(['name' => 'Morgan', 'role' => 'guest',    'show_admin_ui' => 0]);

        $humans = User::findHumans();

        $names = array_map(fn(User $u) => $u->getName(), $humans);
        $this->assertNotContains('localhost', $names, 'Device user must be excluded');
        $this->assertSame(['Alex', 'Morgan', 'Zara'], $names, 'Humans must be ordered by name ASC');
    }

    /** findHumans() returns empty array when only the localhost device user exists. */
    public function testFindHumansReturnsEmptyWhenOnlyLocalhostExists(): void
    {
        $this->assertSame([], User::findHumans());
    }

    // ── findByMac ─────────────────────────────────────────────────────────────

    /** findByMac() returns the correct row when a user with that MAC exists. */
    public function testFindByMacReturnsCorrectRow(): void
    {
        $stub = new User();
        $id   = $stub->insert([
            'name'        => 'DeviceA',
            'role'        => 'device',
            'mac_address' => 'aa:bb:cc:dd:ee:ff',
            'show_admin_ui' => 0,
        ]);

        $user = User::findByMac('aa:bb:cc:dd:ee:ff');

        $this->assertNotNull($user);
        $this->assertSame($id, $user->getId());
        $this->assertSame('DeviceA', $user->getName());
    }

    /** findByMac() returns null for an unknown MAC. */
    public function testFindByMacReturnsNullForUnknownMac(): void
    {
        $this->assertNull(User::findByMac('00:11:22:33:44:55'));
    }

    // ── Round-trip ────────────────────────────────────────────────────────────

    /** Insert a resident user and verify that findById round-trips all fields. */
    public function testInsertAndFindByIdRoundTripsAllFields(): void
    {
        $stub = new User();
        $id   = $stub->insert([
            'name'          => 'Sophia',
            'role'          => 'admin',
            'color'         => '#d93849',
            'show_admin_ui' => 1,
        ]);

        $user = User::findById($id);

        $this->assertNotNull($user);
        $this->assertSame('Sophia',   $user->getName());
        $this->assertSame('admin',    $user->getRole());
        $this->assertSame('#d93849',  $user->getColor());
        $this->assertTrue($user->showAdminUi());
    }

    // ── delete ────────────────────────────────────────────────────────────────

    /** delete() removes the row and returns true for a normal user. */
    public function testDeleteRemovesRowAndReturnsTrue(): void
    {
        $stub = new User();
        $id   = $stub->insert(['name' => 'ToDelete', 'role' => 'resident', 'show_admin_ui' => 0]);

        $result = $stub->delete($id);

        $this->assertTrue($result);
        $this->assertNull(User::findById($id));
    }
}
