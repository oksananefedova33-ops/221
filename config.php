<?php
declare(strict_types=1);

if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

if (!defined('ADMIN_DB_PATH')) {
    define('ADMIN_DB_PATH', __DIR__ . '/data/admin.sqlite');
}

if (!defined('ADMIN_SESSION_NAME')) {
    define('ADMIN_SESSION_NAME', 'site_admin');
}

if (!defined('SITE_ROOT')) {
    define('SITE_ROOT', realpath(__DIR__ . '/..'));
}

if (session_status() === PHP_SESSION_NONE) {
    session_name(ADMIN_SESSION_NAME);
    session_start();
}
