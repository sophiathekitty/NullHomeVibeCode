<?php
/**
 * Debug — static module for in-memory debug logging and service run logging.
 *
 * Accumulates log entries in a static array during a request or service run.
 * Entries are optionally included in API response envelopes when debug mode is
 * enabled (e.g. via ?debug=1). When a ServiceLog is active, each entry is also
 * written to the database line-by-line via ServiceLog::appendLine().
 *
 * This class is fully static. Do not instantiate it.
 */
class Debug
{
    // ── Constants ─────────────────────────────────────────────────────────────

    /** @var string Log level constant for informational messages. */
    const LEVEL_LOG   = 'LOG';

    /** @var string Log level constant for warning messages. */
    const LEVEL_WARN  = 'WARN';

    /** @var string Log level constant for error messages. */
    const LEVEL_ERROR = 'ERROR';

    // ── Static state ──────────────────────────────────────────────────────────

    /** @var bool Whether debug output collection is enabled. */
    private static bool $enabled = false;

    /**
     * Accumulated log entries for the current request or service run.
     *
     * @var array<int, array{level: string, message: string, time: string}>
     */
    private static array $entries = [];

    /** @var ServiceLog|null The active ServiceLog instance, or null if none is set. */
    private static ?ServiceLog $serviceLog = null;

    // ── Enable / query ────────────────────────────────────────────────────────

    /**
     * Enables debug output collection. Called by api/index.php when ?debug=1 is present.
     *
     * @return void
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Returns true if debug output is enabled or if any warn/error entries exist.
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        if (self::$enabled) {
            return true;
        }
        foreach (self::$entries as $entry) {
            if ($entry['level'] === self::LEVEL_WARN || $entry['level'] === self::LEVEL_ERROR) {
                return true;
            }
        }
        return false;
    }

    // ── ServiceLog integration ────────────────────────────────────────────────

    /**
     * Sets the active ServiceLog instance. All subsequent log/warn/error calls
     * will also be written to this log until clearService() is called.
     *
     * @param ServiceLog $log The active service log row.
     * @return void
     */
    public static function setService(ServiceLog $log): void
    {
        self::$serviceLog = $log;
    }

    /**
     * Clears the active ServiceLog instance.
     *
     * @return void
     */
    public static function clearService(): void
    {
        self::$serviceLog = null;
    }

    /**
     * Returns the active ServiceLog instance, or null if no service is running.
     *
     * @return ServiceLog|null
     */
    public static function getServiceLog(): ?ServiceLog
    {
        return self::$serviceLog;
    }

    /**
     * Looks up the NULLHOME_SERVICE constant, finds or creates the matching Service row,
     * creates a new ServiceLog row via ServiceLog::start(), and registers it via
     * Debug::setService(). Calls Debug::log('[service_name] started').
     * No-op if NULLHOME_SERVICE is not defined.
     *
     * @return void
     */
    public static function startService(): void
    {
        if (!defined('NULLHOME_SERVICE')) {
            return;
        }

        require_once APP_ROOT . '/models/Service.php';
        require_once APP_ROOT . '/models/ServiceLog.php';

        $serviceName  = NULLHOME_SERVICE;
        $serviceModel = new Service();

        $serviceRow = $serviceModel->getByName($serviceName);
        if ($serviceRow === null) {
            $serviceModel->insert([
                'name'           => $serviceName,
                'retention_days' => 7,
            ]);
            $serviceRow = $serviceModel->getByName($serviceName);
        }

        $serviceLog = new ServiceLog();
        $serviceLog->start((int) $serviceRow['id']);

        self::setService($serviceLog);
        self::log($serviceName . ' started');
    }

    /**
     * Calls Debug::log('[service_name] done'), then ServiceLog::complete() on the active log.
     * Calls Debug::clearService(). No-op if no service is active.
     *
     * @return void
     */
    public static function completeService(): void
    {
        if (self::$serviceLog === null) {
            return;
        }

        $serviceName = defined('NULLHOME_SERVICE') ? NULLHOME_SERVICE : 'service';
        self::log($serviceName . ' done');
        self::$serviceLog->complete();
        self::clearService();
    }

    // ── Log entry methods ─────────────────────────────────────────────────────

    /**
     * Appends a LOG-level entry.
     *
     * @param string $message The message to log.
     * @return void
     */
    public static function log(string $message): void
    {
        self::append(self::LEVEL_LOG, $message);
    }

    /**
     * Appends a WARN-level entry. Forces debug output enabled.
     *
     * @param string $message The warning message.
     * @return void
     */
    public static function warn(string $message): void
    {
        self::append(self::LEVEL_WARN, $message);
    }

    /**
     * Appends an ERROR-level entry. Forces debug output enabled.
     *
     * @param string $message The error message.
     * @return void
     */
    public static function error(string $message): void
    {
        self::append(self::LEVEL_ERROR, $message);
    }

    // ── Output ────────────────────────────────────────────────────────────────

    /**
     * Returns all accumulated debug entries, or an empty array if debug is not enabled
     * and no warn/error entries exist.
     *
     * Each entry is an associative array with keys: 'level', 'message', 'time'.
     *
     * @return array<int, array{level: string, message: string, time: string}>
     */
    public static function getEntries(): array
    {
        if (!self::isEnabled()) {
            return [];
        }
        return self::$entries;
    }

    // ── Reset ─────────────────────────────────────────────────────────────────

    /**
     * Resets all static state. Used between tests and at the start of each service run.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$enabled    = false;
        self::$entries    = [];
        self::$serviceLog = null;
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    /**
     * Formats and appends an entry to the in-memory array and optionally to the active ServiceLog.
     *
     * Each entry stored in memory has keys: 'level', 'message', 'time'.
     * When a ServiceLog is active, the line is also written in the format:
     *   [HH:MM:SS] [LEVEL] message
     *
     * @param string $level   One of the LEVEL_* constants.
     * @param string $message The message text.
     * @return void
     */
    private static function append(string $level, string $message): void
    {
        $time = date('H:i:s');

        self::$entries[] = [
            'level'   => $level,
            'message' => $message,
            'time'    => $time,
        ];

        if (self::$serviceLog !== null) {
            self::$serviceLog->appendLine($level, $message);
        }
    }
}
