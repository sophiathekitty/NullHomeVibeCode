<?php
/**
 * services/reboot.php — runs once on system boot / application start.
 *
 * Add this to /etc/rc.local or a systemd service, or call it from your
 * Pi startup script:
 *   php /var/www/html/services/reboot.php >> /var/log/nullhome.log 2>&1
 *
 * Responsibilities:
 *   - Ensure all database tables exist (DB::sync for each model)
 *   - Restore any hardware state (e.g. re-apply light states after a power cut)
 *   - Seed default settings if they are missing
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/db/DB.php';
require_once __DIR__ . '/../models/Model.php';
require_once __DIR__ . '/../models/LightsModel.php';
require_once __DIR__ . '/../models/SettingsModel.php';

$timestamp = date('Y-m-d H:i:s');
echo "[reboot] starting NullHome at $timestamp\n";

// Sync all model tables so the schema is up to date before the app starts.
$models = [
    new LightsModel(),
    new SettingsModel(),
];

foreach ($models as $model) {
    try {
        DB::sync($model);
        echo "[reboot] synced table: " . $model->getTable() . "\n";
    } catch (Exception $e) {
        echo "[reboot] ERROR syncing " . $model->getTable() . ": " . $e->getMessage() . "\n";
    }
}

// Seed default settings
$settings = new SettingsModel();
$defaults = [
    'site_name'  => 'NullHome',
    'timezone'   => 'UTC',
];
foreach ($defaults as $key => $value) {
    if ($settings->get($key) === null) {
        $settings->set($key, $value);
        echo "[reboot] seeded setting: $key=$value\n";
    }
}

echo "[reboot] startup complete\n";
