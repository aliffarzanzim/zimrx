<?php
declare(strict_types=1);

// ZimRx Public Gateway Initializer & Path Resolver

if (!defined('ZIMRX_BASE_DIR')) {
    $envBase = getenv('ZIMRX_BASE_DIR');
    if ($envBase !== false && $envBase !== '') {
        // Mode 1: Explicit environment variable (Docker / Enterprise)
        define('ZIMRX_BASE_DIR', rtrim($envBase, '/\\'));
    } elseif (file_exists(__DIR__ . '/../config/config.php')) {
        // Mode 2: Standard Desktop / Portable USB / FrankenPHP / Caddy / VPS
        // (public/ is located inside the application/ root)
        define('ZIMRX_BASE_DIR', dirname(__DIR__));
    } elseif (file_exists(dirname(__DIR__) . '/application/config/config.php')) {
        // Mode 3: Shared Hosting / Cheap cPanel
        // (public_html/ and application/ are sibling directories under /home/username/)
        define('ZIMRX_BASE_DIR', dirname(__DIR__) . '/application');
    } elseif (file_exists(dirname(__DIR__) . '/zimrx/config/config.php')) {
        // Mode 4: Shared Hosting / cPanel named 'zimrx'
        // (public_html/ and zimrx/ are sibling directories under /home/username/)
        define('ZIMRX_BASE_DIR', dirname(__DIR__) . '/zimrx');
    } else {
        http_response_code(500);
        die('Fatal Error: ZimRx core application files could not be located. Ensure application/ is deployed.');
    }
}

if (!defined('ZIMRX_ROOT_DIR')) {
    define('ZIMRX_ROOT_DIR', ZIMRX_BASE_DIR);
}

if (!defined('ZIMRX_PUBLIC_DIR')) {
    define('ZIMRX_PUBLIC_DIR', __DIR__);
}

// Load Core Configuration, Authentication & Database Engine
require_once ZIMRX_BASE_DIR . '/config/config.php';
require_once ZIMRX_BASE_DIR . '/lib/auth.php';
require_once ZIMRX_BASE_DIR . '/lib/db/db.php';
