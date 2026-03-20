<?php
// services/every_10_minutes.php — runs every 10 minutes via cron.
//
// Crontab entry:
//   */10 * * * * php /var/www/html/services/every_10_minutes.php >> /var/log/nullhome.log 2>&1
//
// Add tasks here that should run every 10 minutes.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/DB.php';
require_once __DIR__ . '/../models/SettingsModel.php';

$timestamp = date('Y-m-d H:i:s');
echo "[every_10_minutes] tick at $timestamp\n";

// TODO: Add 10-minute tasks here.
// Examples:
//   - Sync state with external smart-home bridges
//   - Refresh weather data
//   - Prune stale log entries
