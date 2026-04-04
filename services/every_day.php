<?php
/**
 * services/every_day.php — runs once per day via cron.
 *
 * Crontab entry:
 *   0 0 * * * php /var/www/html/services/every_day.php >> /var/log/nullhome.log 2>&1
 *
 * Add tasks here that should run once per day.
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config.php';
define('NULLHOME_SERVICE', 'every_day');

require_once APP_ROOT . '/modules/Debug.php';

Debug::startService();

// Prune expired service logs
$serviceLogModel = new ServiceLog();
$pruned = $serviceLogModel->pruneExpired();
Debug::log("Pruned $pruned expired service log rows");

// TODO: log archiving

Debug::completeService();
