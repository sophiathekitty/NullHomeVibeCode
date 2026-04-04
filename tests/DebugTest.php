<?php
/**
 * DebugTest — unit tests for the Debug static module.
 *
 * Uses PHPUnit mock objects for ServiceLog; does not hit the database.
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/modules/db/DB.php';
require_once APP_ROOT . '/models/ServiceLog.php';
require_once APP_ROOT . '/modules/Debug.php';

use PHPUnit\Framework\TestCase;

class DebugTest extends TestCase
{
    // ── Setup ─────────────────────────────────────────────────────────────────

    /**
     * Reset all Debug static state before each test to ensure isolation.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Debug::reset();
    }

    // ── log() ─────────────────────────────────────────────────────────────────

    /**
     * Debug::log() adds an entry with level LOG.
     *
     * @return void
     */
    public function testLogAddsLogLevelEntry(): void
    {
        Debug::enable();
        Debug::log('hello world');

        $entries = Debug::getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(Debug::LEVEL_LOG, $entries[0]['level']);
        $this->assertSame('hello world', $entries[0]['message']);
        $this->assertNotEmpty($entries[0]['time']);
    }

    // ── warn() ────────────────────────────────────────────────────────────────

    /**
     * Debug::warn() adds an entry with level WARN and forces isEnabled() to true.
     *
     * @return void
     */
    public function testWarnAddsWarnLevelEntryAndForcesEnabled(): void
    {
        // Do NOT call enable() — warn should force it on.
        $this->assertFalse(Debug::isEnabled());

        Debug::warn('something is wrong');

        $this->assertTrue(Debug::isEnabled());

        $entries = Debug::getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(Debug::LEVEL_WARN, $entries[0]['level']);
        $this->assertSame('something is wrong', $entries[0]['message']);
    }

    // ── error() ───────────────────────────────────────────────────────────────

    /**
     * Debug::error() adds an entry with level ERROR and forces isEnabled() to true.
     *
     * @return void
     */
    public function testErrorAddsErrorLevelEntryAndForcesEnabled(): void
    {
        $this->assertFalse(Debug::isEnabled());

        Debug::error('fatal failure');

        $this->assertTrue(Debug::isEnabled());

        $entries = Debug::getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(Debug::LEVEL_ERROR, $entries[0]['level']);
        $this->assertSame('fatal failure', $entries[0]['message']);
    }

    // ── getEntries() ──────────────────────────────────────────────────────────

    /**
     * getEntries() returns an empty array when debug is not enabled and there are no warn/error entries.
     *
     * @return void
     */
    public function testGetEntriesReturnsEmptyWhenNotEnabled(): void
    {
        Debug::log('this should not appear');

        $this->assertFalse(Debug::isEnabled());
        $this->assertSame([], Debug::getEntries());
    }

    /**
     * getEntries() returns all entries when debug is enabled.
     *
     * @return void
     */
    public function testGetEntriesReturnsAllEntriesWhenEnabled(): void
    {
        Debug::enable();
        Debug::log('first');
        Debug::warn('second');
        Debug::error('third');

        $entries = Debug::getEntries();
        $this->assertCount(3, $entries);
        $this->assertSame(Debug::LEVEL_LOG,   $entries[0]['level']);
        $this->assertSame(Debug::LEVEL_WARN,  $entries[1]['level']);
        $this->assertSame(Debug::LEVEL_ERROR, $entries[2]['level']);
    }

    // ── reset() ───────────────────────────────────────────────────────────────

    /**
     * Debug::reset() clears all entries and resets the enabled state.
     *
     * @return void
     */
    public function testResetClearsEntriesAndDisablesDebug(): void
    {
        Debug::enable();
        Debug::log('entry one');
        Debug::warn('entry two');

        $this->assertTrue(Debug::isEnabled());
        $this->assertCount(2, Debug::getEntries());

        Debug::reset();

        $this->assertFalse(Debug::isEnabled());
        $this->assertSame([], Debug::getEntries());
    }

    // ── ServiceLog mock integration ───────────────────────────────────────────

    /**
     * When a ServiceLog mock is set via setService(), calling log() invokes appendLine() on the mock.
     *
     * @return void
     */
    public function testLogCallsAppendLineOnServiceLog(): void
    {
        $mock = $this->createMock(ServiceLog::class);
        $mock->expects($this->once())
             ->method('appendLine')
             ->with(Debug::LEVEL_LOG, 'test message');

        Debug::setService($mock);
        Debug::log('test message');
    }

    /**
     * warn() entries are passed to appendLine() with the WARN level string.
     *
     * @return void
     */
    public function testWarnCallsAppendLineWithWarnLevel(): void
    {
        $mock = $this->createMock(ServiceLog::class);
        $mock->expects($this->once())
             ->method('appendLine')
             ->with(Debug::LEVEL_WARN, 'warn message');

        Debug::setService($mock);
        Debug::warn('warn message');
    }

    /**
     * error() entries are passed to appendLine() with the ERROR level string.
     *
     * @return void
     */
    public function testErrorCallsAppendLineWithErrorLevel(): void
    {
        $mock = $this->createMock(ServiceLog::class);
        $mock->expects($this->once())
             ->method('appendLine')
             ->with(Debug::LEVEL_ERROR, 'error message');

        Debug::setService($mock);
        Debug::error('error message');
    }
}
