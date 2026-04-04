<?php
/**
 * services/every_hour.php — runs every hour via cron.
 *
 * Crontab entry:
 *   0 * * * * php /var/www/html/services/every_hour.php >> /var/log/nullhome.log 2>&1
 *
 * Add tasks here that should run once per hour.
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config.php';
define('NULLHOME_SERVICE', 'every_hour');

require_once APP_ROOT . '/modules/Debug.php';

Debug::startService();

// TODO: Add hourly tasks here.

Debug::completeService();

