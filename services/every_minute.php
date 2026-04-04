<?php
/**
 * services/every_minute.php — runs every minute via cron.
 *
 * Crontab entry:
 *   * * * * * php /var/www/html/services/every_minute.php >> /var/log/nullhome.log 2>&1
 *
 * Add recurring one-minute tasks here.
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config.php';
define('NULLHOME_SERVICE', 'every_minute');

require_once APP_ROOT . '/modules/Debug.php';

Debug::startService();

// TODO: automation engine

Debug::completeService();

