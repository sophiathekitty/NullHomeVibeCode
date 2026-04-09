<?php
/**
 * AuthModuleTest — integration tests for AuthModule.
 *
 * Verifies session resolution, creation, and deletion.
 * Every test starts from a clean known state established in setUp().
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/models/User.php';
require_once APP_ROOT . '/models/UserSession.php';
require_once APP_ROOT . '/modules/auth/AuthModule.php';

class AuthModuleTest extends BaseTestCase
{
    /** @var int ID of a known resident user inserted in setUp(). */
    private int $residentId;

    /**
     * Truncate users and sessions; re-insert localhost (id=1); insert a known resident.
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

        DB::query(
            'INSERT INTO `users` (`id`, `name`, `role`, `mac_address`, `show_admin_ui`) VALUES (1, ?, ?, NULL, 0)',
            ['localhost', 'device']
        );

        $stub = new User();
        $this->residentId = $stub->insert([
            'name'          => 'TestResident',
            'role'          => 'resident',
            'show_admin_ui' => 0,
        ]);
    }

    // ── resolve — guest fallback ───────────────────────────────────────────────

    /** resolve(null, $ip) returns the guest constant array. */
    public function testResolveWithNullTokenReturnsGuest(): void
    {
        $result = AuthModule::resolve(null, '127.0.0.1');

        $this->assertSame(0, $result['user']['id']);
        $this->assertSame('guest', $result['user']['role']);
        $this->assertNull($result['session']);
    }

    /** resolve('', $ip) returns the guest array. */
    public function testResolveWithEmptyTokenReturnsGuest(): void
    {
        $result = AuthModule::resolve('', '127.0.0.1');

        $this->assertSame(0, $result['user']['id']);
        $this->assertSame('guest', $result['user']['role']);
    }

    /** resolve($unknownToken, $ip) returns the guest array. */
    public function testResolveWithUnknownTokenReturnsGuest(): void
    {
        $result = AuthModule::resolve('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '127.0.0.1');

        $this->assertSame(0, $result['user']['id']);
        $this->assertSame('guest', $result['user']['role']);
    }

    /** resolve($expiredToken, $ip) deletes the session row and returns guest. */
    public function testResolveWithExpiredTokenDeletesSessionAndReturnsGuest(): void
    {
        $token = bin2hex(random_bytes(32));
        $stub  = new UserSession();
        $stub->insert([
            'user_id'      => $this->residentId,
            'token'        => $token,
            'ip_address'   => '127.0.0.1',
            'created_at'   => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'expires_at'   => date('Y-m-d H:i:s', time() - 3600),
        ]);

        $result = AuthModule::resolve($token, '127.0.0.1');

        $this->assertSame(0, $result['user']['id']);
        $this->assertNull(UserSession::findByToken($token), 'Expired session row must be deleted');
    }

    /** resolve($validToken, $ip) returns the correct user array and non-null session array. */
    public function testResolveWithValidTokenReturnsUserAndSession(): void
    {
        $token = AuthModule::createSession($this->residentId, '127.0.0.1');

        $result = AuthModule::resolve($token, '127.0.0.1');

        $this->assertSame($this->residentId, $result['user']['id']);
        $this->assertSame('resident', $result['user']['role']);
        $this->assertNotNull($result['session']);
        $this->assertSame($token, $result['session']['token']);
    }

    /** resolve($validToken, $ip) updates last_seen_at on the session row. */
    public function testResolveUpdatesLastSeenAt(): void
    {
        $token = AuthModule::createSession($this->residentId, '127.0.0.1');

        // Record the current last_seen_at.
        $before = UserSession::findByToken($token)?->toArray()['last_seen_at'];

        // Sleep 1 second so the timestamp can change.
        sleep(1);

        AuthModule::resolve($token, '127.0.0.1');

        $after = UserSession::findByToken($token)?->toArray()['last_seen_at'];

        $this->assertNotNull($after);
        $this->assertNotEquals($before, $after, 'last_seen_at must be updated after resolve()');
    }

    // ── createSession ─────────────────────────────────────────────────────────

    /** createSession() inserts a row with expires_at = null. */
    public function testCreateSessionInsertsRowWithNullExpiresAt(): void
    {
        $token = AuthModule::createSession($this->residentId, '127.0.0.1');

        $session = UserSession::findByToken($token);

        $this->assertNotNull($session);
        $this->assertNull($session->toArray()['expires_at']);
    }

    /** createSession() returns a string of exactly 64 hexadecimal characters. */
    public function testCreateSessionReturns64HexChars(): void
    {
        $token = AuthModule::createSession($this->residentId, '127.0.0.1');

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    /** createSession() called twice on the same user creates two distinct rows. */
    public function testCreateSessionCreatesMultipleSessionsForSameUser(): void
    {
        $token1 = AuthModule::createSession($this->residentId, '127.0.0.1');
        $token2 = AuthModule::createSession($this->residentId, '127.0.0.2');

        $this->assertNotSame($token1, $token2);

        $sessions = UserSession::findActiveByUserId($this->residentId);
        $this->assertCount(2, $sessions);
    }

    // ── destroySession ────────────────────────────────────────────────────────

    /** destroySession() deletes the session row; findByToken returns null afterward. */
    public function testDestroySessionDeletesRow(): void
    {
        $token = AuthModule::createSession($this->residentId, '127.0.0.1');

        AuthModule::destroySession($token);

        $this->assertNull(UserSession::findByToken($token));
    }

    /** destroySession() with an unknown token does not throw. */
    public function testDestroySessionWithUnknownTokenDoesNotThrow(): void
    {
        // Should complete without exception.
        AuthModule::destroySession('nonexistenttoken00000000000000000000000000000000000000000000000000');
        $this->assertTrue(true);
    }
}
