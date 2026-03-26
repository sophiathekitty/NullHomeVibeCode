<?php
/**
 * PHPUnit bootstrap — loaded before any test class.
 *
 * Requires config.test.php (never config.php) so tests always run against the
 * dedicated test database, then loads the DB layer used by the application.
 */
require_once __DIR__ . '/../config.test.php';
require_once APP_ROOT . '/db/DB.php';
require_once __DIR__ . '/BaseTestCase.php';
