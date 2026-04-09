<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../modules/auth/AuthModule.php';

/**
 * UserController — business logic for the user identity system.
 *
 * HTTP-unaware. Returns plain arrays or scalar values.
 * The API handler is responsible for cookie management.
 */
class UserController extends Controller
{
    /**
     * Constructor — bootstraps the parent controller.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Return all human users ordered by name, for the switcher UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listHumans(): array
    {
        return array_map(
            static fn(User $u) => $u->toArray(),
            User::findHumans()
        );
    }

    /**
     * Create a new human user and return their plain array.
     * Allowed roles: 'guest', 'resident', 'admin'.
     * Rejects 'device' — device users are created by the device identity system (future).
     *
     * @param string      $name        Display name.
     * @param string      $role        One of: 'guest', 'resident', 'admin'.
     * @param string|null $color       Optional hex color, e.g. '#d93849'. Null if not provided.
     * @param bool        $showAdminUi Whether to show admin tools.
     * @return array<string, mixed> The newly created user.
     * @throws \InvalidArgumentException If role is 'device' or an unrecognised value.
     */
    public function create(string $name, string $role, ?string $color, bool $showAdminUi): array
    {
        $allowed = ['guest', 'resident', 'admin'];
        if (!in_array($role, $allowed, true)) {
            throw new \InvalidArgumentException(
                'role must be one of: guest, resident, admin'
            );
        }

        $stub = new User();
        $id   = $stub->insert([
            'name'          => $name,
            'role'          => $role,
            'mac_address'   => null,
            'color'         => $color,
            'show_admin_ui' => $showAdminUi ? 1 : 0,
        ]);
        return User::findById($id)?->toArray() ?? [];
    }

    /**
     * Delete a user by id. Returns false if the user does not exist.
     * Refuses to delete id=1 (localhost system user) — returns false.
     *
     * @param int $id The user's primary key.
     * @return bool True if deleted, false if not found or protected.
     */
    public function delete(int $id): bool
    {
        if ($id === 1) {
            return false;
        }
        $user = User::findById($id);
        if ($user === null) {
            return false;
        }
        $stub = new User();
        return $stub->delete($id);
    }

    /**
     * Log in as the given user: create a session and return the token + user data.
     * The handler is responsible for setting the cookie.
     *
     * @param int    $userId    The user's primary key.
     * @param string $ipAddress Client IP (read from $_SERVER in the handler, passed in here).
     * @return array{user: array<string,mixed>, token: string}|null Null if user not found.
     */
    public function login(int $userId, string $ipAddress): ?array
    {
        $user = User::findById($userId);
        if ($user === null) {
            return null;
        }
        $token = AuthModule::createSession($userId, $ipAddress);
        return [
            'user'  => $user->toArray(),
            'token' => $token,
        ];
    }

    /**
     * Destroy the session identified by $token. No-op if already gone.
     *
     * @param string $token The session token from the cookie.
     * @return void
     */
    public function logout(string $token): void
    {
        AuthModule::destroySession($token);
    }

    /**
     * Resolve the current user from a token and IP. Delegates to AuthModule::resolve().
     *
     * @param string|null $token     Session token from cookie, or null.
     * @param string      $ipAddress Client IP.
     * @return array{user: array<string,mixed>, session: array<string,mixed>|null}
     */
    public function getCurrentUser(?string $token, string $ipAddress): array
    {
        return AuthModule::resolve($token, $ipAddress);
    }
}
