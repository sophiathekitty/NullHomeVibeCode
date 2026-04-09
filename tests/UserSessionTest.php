<?php
/**
 * UserSessionTest — integration tests for the UserSession model.
 *
 * Uses the localhost user (id=1) as the anchor user_id.
 * Every test starts from a clean sessions table established in setUp().
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/models/User.php';
require_once APP_ROOT . '/models/UserSession.php';

class UserSessionTest extends BaseTestCase
{
    /**
     * Truncate sessions before every test for a clean slate.
     * The localhost seed user (id=1) is guaranteed by BaseTestCase / setUp of UserModelTest
     * but here we also need to make sure it exists since sessions FK to users.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
        DB::connection()->exec('TRUNCATE TABLE `sessions`');
        DB::connection()->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Ensure localhost user exists so FK is satisfied.
        $exists = (int) DB::query('SELECT COUNT(*) FROM `users` WHERE `id` = 1')->fetchColumn();
        if ($exists === 0) {
            DB::query(
                'INSERT INTO `users` (`id`, `name`, `role`, `mac_address`, `show_admin_ui`) VALUES (1, ?, ?, NULL, 0)',
                ['localhost', 'device']
            );
        }
    }

    // ── findByToken ───────────────────────────────────────────────────────────

    /** findByToken() returns the correct instance after a direct DB insert. */
    public function testFindByTokenReturnsCorrectInstance(): void
    {
        $token = bin2hex(random_bytes(32));
        $stub  = new UserSession();
        $stub->insert([
            'user_id'      => 1,
            'token'        => $token,
            'ip_address'   => '127.0.0.1',
            'created_at'   => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'expires_at'   => null,
        ]);

        $session = UserSession::findByToken($token);

        $this->assertNotNull($session);
        $this->assertSame($token, $session->getToken());
        $this->assertSame(1, $session->getUserId());
    }

    /** findByToken() returns null for an unknown token. */
    public function testFindByTokenReturnsNullForUnknownToken(): void
    {
        $this->assertNull(UserSession::findByToken('unknowntoken12345678901234567890'));
    }

    // ── isExpired ─────────────────────────────────────────────────────────────

    /** isExpired() returns false when expires_at is null. */
    public function testIsExpiredReturnsFalseWhenExpiresAtIsNull(): void
    {
        $token = bin2hex(random_bytes(32));
        $stub  = new UserSession();
        $stub->insert([
            'user_id'      => 1,
            'token'        => $token,
            'ip_address'   => '127.0.0.1',
            'created_at'   => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'expires_at'   => null,
        ]);

        $session = UserSession::findByToken($token);

        $this->assertFalse($session->isExpired());
    }

    /** isExpired() returns true when expires_at is a past datetime. */
    public function testIsExpiredReturnsTrueForPastExpiresAt(): void
    {
        $token = bin2hex(random_bytes(32));
        $stub  = new UserSession();
        $stub->insert([
            'user_id'      => 1,
            'token'        => $token,
            'ip_address'   => '127.0.0.1',
            'created_at'   => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'expires_at'   => date('Y-m-d H:i:s', time() - 3600),
        ]);

        $session = UserSession::findByToken($token);

        $this->assertTrue($session->isExpired());
    }

    /** isExpired() returns false when expires_at is a future datetime. */
    public function testIsExpiredReturnsFalseForFutureExpiresAt(): void
    {
        $token = bin2hex(random_bytes(32));
        $stub  = new UserSession();
        $stub->insert([
            'user_id'      => 1,
            'token'        => $token,
            'ip_address'   => '127.0.0.1',
            'created_at'   => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'expires_at'   => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $session = UserSession::findByToken($token);

        $this->assertFalse($session->isExpired());
    }

    // ── findActiveByUserId ────────────────────────────────────────────────────

    /** findActiveByUserId() returns all sessions when none are expired. */
    public function testFindActiveByUserIdReturnsAllWhenNoneExpired(): void
    {
        $stub = new UserSession();
        $stub->insert([
            'user_id'      => 1,
            'token'        => bin2hex(random_bytes(32)),
            'ip_address'   => '127.0.0.1',
            'created_at'   => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'expires_at'   => null,
        ]);
        $stub->insert([
            'user_id'      => 1,
            'token'        => bin2hex(random_bytes(32)),
            'ip_address'   => '127.0.0.2',
            'created_at'   => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'expires_at'   => null,
        ]);

        $sessions = UserSession::findActiveByUserId(1);

        $this->assertCount(2, $sessions);
    }

    /** findActiveByUserId() excludes rows where expires_at is in the past. */
    public function testFindActiveByUserIdExcludesExpiredSessions(): void
    {
        $stub = new UserSession();
        $stub->insert([
            'user_id'      => 1,
            'token'        => bin2hex(random_bytes(32)),
            'ip_address'   => '127.0.0.1',
            'created_at'   => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'expires_at'   => null,
        ]);
        // Expired session
        $stub->insert([
            'user_id'      => 1,
            'token'        => bin2hex(random_bytes(32)),
            'ip_address'   => '127.0.0.2',
            'created_at'   => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'expires_at'   => date('Y-m-d H:i:s', time() - 3600),
        ]);

        $sessions = UserSession::findActiveByUserId(1);

        $this->assertCount(1, $sessions);
    }
}
