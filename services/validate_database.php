<?php
/**
 * services/validate_database.php — validate and sync all model tables.
 *
 * Can be called manually, from a cron job, or from a bash script:
 *   php /var/www/html/services/validate_database.php
 *   php /var/www/html/services/validate_database.php >> /var/log/nullhome.log 2>&1
 *
 * Exits with code 0 on success, 1 if any table could not be synced.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/DB.php';
require_once __DIR__ . '/DatabaseValidationService.php';

$timestamp = date('Y-m-d H:i:s');
echo "[validate_database] starting at $timestamp\n";

$svc        = new DatabaseValidationService();
$validation = $svc->validate();

foreach ($validation['results'] as $row) {
    $status = $row['status'] === 'ok' ? 'ok' : 'ERROR: ' . $row['status'];
    echo "[validate_database] table '{$row['table']}' ({$row['model']}): $status\n";
}

if ($validation['success']) {
    echo "[validate_database] all tables are valid\n";
    exit(0);
} else {
    echo "[validate_database] ERROR: " . ($validation['error'] ?? 'unknown error') . "\n";
    exit(1);
}
