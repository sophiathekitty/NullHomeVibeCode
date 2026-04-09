<?php
require_once __DIR__ . '/ApiHandler.php';
require_once __DIR__ . '/../../controllers/UserController.php';

/**
 * UsersHandler — handles /api/users/… and /api/auth/… requests.
 *
 * Routes:
 *   GET    /api/auth/me          → return current user from session cookie
 *   POST   /api/auth/login       → create session, set cookie, return user
 *   POST   /api/auth/logout      → delete session, clear cookie
 *   GET    /api/users            → list all human users
 *   POST   /api/users            → create a new user
 *   DELETE /api/users/{id}       → delete a user
 */
class UsersHandler extends ApiHandler
{
    /** @var string Resource key: 'users' or 'auth'. Set by api/index.php. */
    protected string $resource = 'users';

    /**
     * Set the resource name so the handler can distinguish /api/users from /api/auth.
     *
     * @param string $resource
     * @return void
     */
    public function setResource(string $resource): void
    {
        $this->resource = $resource;
    }

    /**
     * Route the incoming request to the appropriate action.
     *
     * @param array  $params URL path segments after the resource key.
     * @param string $method HTTP request method.
     * @param array  $body   Decoded JSON request body.
     * @return void
     */
    public function handle(array $params, string $method, array $body): void
    {
        $controller = new UserController();

        if ($this->resource === 'auth') {
            $this->handleAuth($params, $method, $body, $controller);
            return;
        }

        $this->handleUsers($params, $method, $body, $controller);
    }

    /**
     * Handle /api/auth/… routes.
     *
     * @param array           $params
     * @param string          $method
     * @param array           $body
     * @param UserController  $controller
     * @return void
     */
    private function handleAuth(
        array $params,
        string $method,
        array $body,
        UserController $controller
    ): void {
        $action = $params[0] ?? null;

        if ($action === 'me' && $method === 'GET') {
            $token  = $_COOKIE['nullhome_session'] ?? null;
            $ip     = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $result = $controller->getCurrentUser($token, $ip);
            $this->ok($result['user']);
            return;
        }

        if ($action === 'login' && $method === 'POST') {
            $userId = isset($body['user_id']) ? (int) $body['user_id'] : null;
            if ($userId === null) {
                $this->error('user_id is required');
                return;
            }
            $ip     = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $result = $controller->login($userId, $ip);
            if ($result === null) {
                $this->notFound('User not found');
                return;
            }
            setcookie('nullhome_session', $result['token'], [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $this->ok($result['user']);
            return;
        }

        if ($action === 'logout' && $method === 'POST') {
            $token = $_COOKIE['nullhome_session'] ?? null;
            if ($token !== null) {
                $controller->logout($token);
            }
            setcookie('nullhome_session', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $this->ok(null);
            return;
        }

        $this->notFound($action !== null ? "Unknown auth action: $action" : 'Auth action is required');
    }

    /**
     * Handle /api/users/… routes.
     *
     * @param array           $params
     * @param string          $method
     * @param array           $body
     * @param UserController  $controller
     * @return void
     */
    private function handleUsers(
        array $params,
        string $method,
        array $body,
        UserController $controller
    ): void {
        // GET /api/users — list all human users
        if ($method === 'GET' && empty($params)) {
            $this->ok($controller->listHumans());
            return;
        }

        // POST /api/users — create a new user
        if ($method === 'POST' && empty($params)) {
            $name       = trim((string) ($body['name'] ?? ''));
            $role       = trim((string) ($body['role'] ?? 'resident'));
            $color      = isset($body['color']) ? (string) $body['color'] : null;
            $showAdmin  = !empty($body['show_admin_ui']);

            if ($name === '') {
                $this->error('name is required');
                return;
            }

            try {
                $user = $controller->create($name, $role, $color, $showAdmin);
                $this->ok($user);
            } catch (\InvalidArgumentException $e) {
                $this->error($e->getMessage());
            }
            return;
        }

        // DELETE /api/users/{id}
        if ($method === 'DELETE' && isset($params[0])) {
            $id = (int) $params[0];
            if ($id === 1) {
                $this->error('Cannot delete the localhost system user');
                return;
            }
            $deleted = $controller->delete($id);
            if (!$deleted) {
                $this->notFound('User not found');
                return;
            }
            $this->ok(null);
            return;
        }

        $this->methodNotAllowed();
    }
}
