<?php
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/UserSession.php';

/**
 * AuthModule — stateless authentication helpers.
 *
 * HTTP-unaware: no $_COOKIE, $_SERVER, or $_SESSION references.
 * All inputs are passed as arguments.
 */
class AuthModule
{
    /**
     * Synthetic guest user returned when no valid session exists.
     *
     * id=0 is never stored in the database.
     */
    private const GUEST = [
        'id'            => 0,
        'name'          => 'Guest',
        'role'          => 'guest',
        'color'         => null,
        'show_admin_ui' => false,
        'mac_address'   => null,
    ];

    /**
     * Resolve the current actor from a session token and the request IP.
     *
     * Steps:
     *   1. If $token is null or empty, return the guest array.
     *   2. Look up the session by token. If not found, return guest.
     *   3. If session->isExpired(), delete the session row and return guest.
     *   4. Update last_seen_at on the session to NOW().
     *   5. Fetch the user. If not found, delete the session and return guest.
     *   6. Return ['user' => $user->toArray(), 'session' => $session->toArray()].
     *
     * Note: IP-change validation for device sessions is deferred to a follow-up issue.
     *
     * @param string|null $token     The session token from the cookie, or null if absent.
     * @param string      $ipAddress The client IP address (passed in; not read from $_SERVER).
     * @return array{user: array<string,mixed>, session: array<string,mixed>|null}
     */
    public static function resolve(?string $token, string $ipAddress): array
    {
        if ($token === null || $token === '') {
            return ['user' => self::GUEST, 'session' => null];
        }

        $session = UserSession::findByToken($token);
        if ($session === null) {
            return ['user' => self::GUEST, 'session' => null];
        }

        if ($session->isExpired()) {
            $stub = new UserSession();
            $stub->delete($session->toArray()['id']);
            return ['user' => self::GUEST, 'session' => null];
        }

        // Update last_seen_at
        DB::query(
            "UPDATE `sessions` SET `last_seen_at` = NOW() WHERE `token` = ?",
            [$token]
        );

        $user = User::findById($session->getUserId());
        if ($user === null) {
            $stub = new UserSession();
            $stub->delete($session->toArray()['id']);
            return ['user' => self::GUEST, 'session' => null];
        }

        // Reload session to get updated last_seen_at
        $session = UserSession::findByToken($token);

        return [
            'user'    => $user->toArray(),
            'session' => $session?->toArray(),
        ];
    }

    /**
     * Create a new session row for the given user id and return the token.
     *
     * Token is generated via bin2hex(random_bytes(32)) — 64 hex characters.
     * expires_at is always NULL for human users (the only callers in this issue).
     *
     * @param int    $userId    The user's primary key.
     * @param string $ipAddress Client IP.
     * @return string The new session token.
     */
    public static function createSession(int $userId, string $ipAddress): string
    {
        $token = bin2hex(random_bytes(32));
        $stub  = new UserSession();
        $stub->insert([
            'user_id'      => $userId,
            'token'        => $token,
            'ip_address'   => $ipAddress,
            'created_at'   => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'expires_at'   => null,
        ]);
        return $token;
    }

    /**
     * Delete a session row by token. No-op if the token does not exist.
     *
     * @param string $token The session token to delete.
     * @return void
     */
    public static function destroySession(string $token): void
    {
        DB::query("DELETE FROM `sessions` WHERE `token` = ?", [$token]);
    }

    // TODO: pruneDeviceSessions() — deferred to device identity follow-up issue
    // TODO: validateDeviceSessions() — deferred to device identity follow-up issue
}
