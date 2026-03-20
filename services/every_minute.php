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
require_once __DIR__ . '/../db/DB.php';
require_once __DIR__ . '/../models/LightsModel.php';
require_once __DIR__ . '/../controllers/LightsController.php';

// Example: log a heartbeat timestamp so we know the service is running.
$timestamp = date('Y-m-d H:i:s');
echo "[every_minute] tick at $timestamp\n";

// TODO: Add minute-level automation rules here.
// Examples:
//   - Check schedules and toggle lights on/off
//   - Poll sensor data
//   - Update state from external integrations
