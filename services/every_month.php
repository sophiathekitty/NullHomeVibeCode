<?php
/**
 * services/every_month.php — runs once per month via cron.
 *
 * Crontab entry:
 *   0 0 1 * * php /var/www/html/services/every_month.php >> /var/log/nullhome.log 2>&1
 *
 * Add tasks here that should run once per month.
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config.php';
define('NULLHOME_SERVICE', 'every_month');

require_once APP_ROOT . '/modules/Debug.php';

Debug::startService();

// TODO: Add monthly tasks here.

Debug::completeService();
