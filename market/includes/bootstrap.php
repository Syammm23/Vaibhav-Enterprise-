<?php
/** Loaded first by every page. Session, database, helpers. */

require_once __DIR__ . '/../config/config.php';

/**
 * Web path to the application root, with a trailing slash — "/" when the app is
 * the document root, "/market/" when it sits in a subfolder. Redirects issued
 * from /admin need this: a bare "login.php" would resolve to /admin/login.php.
 */
if (!defined('BASE_URL')) {
    $appRoot = str_replace('\\', '/', dirname(__DIR__));
    $docRoot = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $webPath = ($docRoot !== '' && str_starts_with($appRoot, $docRoot))
        ? substr($appRoot, strlen($docRoot))
        : '';
    define('BASE_URL', rtrim($webPath, '/') . '/');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_name('MARKETSESSID');
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/catalog.php';
