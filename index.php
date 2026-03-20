<?php
// Root entry point.
// Show the install wizard when config.php is missing; otherwise serve the SPA.
if (!file_exists(__DIR__ . '/config.php')) {
    require __DIR__ . '/install/index.php';
    exit;
}

require __DIR__ . '/config.php';
require __DIR__ . '/app/index.php';
