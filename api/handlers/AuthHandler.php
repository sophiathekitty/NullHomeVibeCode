<?php
require_once __DIR__ . '/UsersHandler.php';

/**
 * AuthHandler — thin subclass of UsersHandler that satisfies the
 * 'auth' => 'AuthHandler' class name convention in api/index.php.
 *
 * Routes: GET /api/auth/me, POST /api/auth/login, POST /api/auth/logout
 */
class AuthHandler extends UsersHandler {}
