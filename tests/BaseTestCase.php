<?php
/**
 * BaseTestCase — abstract base for all test classes.
 *
 * Before the suite runs:
 *   1. Creates the homehub_test database (using a raw PDO, no dbname in DSN).
 *   2. Calls DatabaseValidationService to sync all model tables into that DB.
 *
 * After the suite finishes, drops the homehub_test database so the next run
 * always starts from a clean slate.
 *
 * Config is loaded exclusively from config.test.php (via bootstrap.php).
 * Application config loading (config.php) is never touched.
 */

use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase
{
    /** Create the test database and install the full schema. */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Step 1 — create the test DB using a connection without a selected dbname.
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '`');

        // Step 2 — run DatabaseValidationService to create / sync all model tables.
        require_once APP_ROOT . '/modules/db/DatabaseValidationService.php';
        $service = new DatabaseValidationService();
        $result  = $service->validate();

        if (!$result['success']) {
            throw new \RuntimeException(
                'Test DB schema setup failed: ' . ($result['error'] ?? 'unknown error')
            );
        }
    }

    /** Drop the test database after all tests in the suite have run. */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        // Use a fresh connection (no dbname) to drop the test database.
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');

        // Reset the DB singleton so subsequent test classes start with a fresh
        // connection (important when multiple test classes extend BaseTestCase).
        $reflection = new \ReflectionProperty(DB::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue(null, null);
    }

    /** Assert that a table exists in the current test database. */
    protected function assertTableExists(string $tableName): void
    {
        $count = (int) DB::query(
            'SELECT COUNT(*) FROM information_schema.tables'
            . ' WHERE table_schema = DATABASE() AND table_name = ?',
            [$tableName]
        )->fetchColumn();

        $this->assertSame(1, $count, "Table '{$tableName}' does not exist in the test database.");
    }
}
