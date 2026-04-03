<?php
/**
 * services/every_minute.php — runs every minute via cron.
 *
 * Crontab entry:
 *   * * * * * php /var/www/html/services/every_minute.php >> /var/log/nullhome.log 2>&1
 *
 * Add recurring one-minute tasks here.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/db/DB.php';
require_once APP_ROOT . '/modules/devices/WemoDriver.php';

// Example: log a heartbeat timestamp so we know the service is running.
$timestamp = date('Y-m-d H:i:s');
echo "[every_minute] tick at $timestamp\n";

// Poll all known Wemo devices and sync their state to the database.
WemoDriver::observe();

