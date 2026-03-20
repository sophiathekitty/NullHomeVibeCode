<?php
/**
 * services/every_hour.php — runs every hour via cron.
 *
 * Crontab entry:
 *   0 * * * * php /var/www/html/services/every_hour.php >> /var/log/nullhome.log 2>&1
 *
 * Add tasks here that should run once per hour.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/DB.php';
require_once __DIR__ . '/../models/SettingsModel.php';

$timestamp = date('Y-m-d H:i:s');
echo "[every_hour] tick at $timestamp\n";

// TODO: Add hourly tasks here.
// Examples:
//   - Generate hourly energy usage summaries
//   - Rotate logs
//   - Send status notifications
