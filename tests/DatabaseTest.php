<?php
/**
 * DatabaseTest — smoke tests for database connectivity and schema integrity.
 *
 * Verifies that:
 *   - A PDO connection to the test database can be obtained.
 *   - The core application tables exist after the schema setup performed by
 *     BaseTestCase::setUpBeforeClass().
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */
class DatabaseTest extends BaseTestCase
{
    /** DB connection returns a valid PDO instance. */
    public function testDatabaseConnectionSucceeds(): void
    {
        $this->assertInstanceOf(PDO::class, DB::connection());
    }

    /** The lights table was created by the validation service. */
    public function testLightsTableExists(): void
    {
        $this->assertTableExists('lights');
    }

    /** The devices table was created by the validation service. */
    public function testDevicesTableExists(): void
    {
        $this->assertTableExists('devices');
    }

    /** The settings table was created by the validation service. */
    public function testSettingsTableExists(): void
    {
        $this->assertTableExists('settings');
    }
}
