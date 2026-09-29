<?php
declare(strict_types=1);

// Core configuration: file paths, database connection parameters, and network origins.

// Application directory paths
define('ZIMRX_BASE_DIR', __DIR__);
define('ZIMRX_USERDATA_DIR', ZIMRX_BASE_DIR . '/userdata');
define('ZIMRX_DB_DIR', ZIMRX_USERDATA_DIR . '/database');
define('ZIMRX_UPLOADS_DIR', ZIMRX_USERDATA_DIR . '/uploads');
define('ZIMRX_ASSETS_DB_DIR', ZIMRX_BASE_DIR . '/assets/database');

// Database storage paths
define('ZIMRX_DB_USERDATA', ZIMRX_DB_DIR . '/zimrx_userdata.db');
define('ZIMRX_DB_SYSTEMDATA', ZIMRX_ASSETS_DB_DIR . '/zimrx_drugs.db');
define('ZIMRX_DB_STATIC', ZIMRX_ASSETS_DB_DIR . '/zimrx_static.db');
define('ZIMRX_DB_ICD11', ZIMRX_ASSETS_DB_DIR . '/zimrx_icd11_dx.db');
define('ZIMRX_DB_ADDRESSES', ZIMRX_DB_STATIC);

// Active database engine: 'sqlite', 'mysql', 'mariadb', or 'pgsql'
define('DB_DRIVER', 'sqlite');

// Database credentials and endpoints. For SQLite, only 'path' is used.
// For network databases (MySQL, MariaDB, Postgres), fill in host, port, user, and password.
define('DB_CONFIG', [
    'driver'   => DB_DRIVER,

    'userdata' => [
        'path' => ZIMRX_DB_DIR . '/zimrx_userdata.db',
        'host' => 'localhost',
        'port' => 3306,
        'user' => 'zimrx_user',
        'pass' => '',
    ],

    'static' => [
        'path' => ZIMRX_ASSETS_DB_DIR . '/zimrx_static.db',
        'host' => 'localhost',
        'port' => 3306,
        'user' => 'zimrx_user',
        'pass' => '',
    ],

    'system' => [
        'path' => ZIMRX_DB_SYSTEMDATA,
        'host' => 'localhost',
        'port' => 3306,
        'user' => 'zimrx_user',
        'pass' => '',
    ],
]);

// Public origin override for mobile camera upload QR codes (e.g. 'https://clinic.local:8080').
// When null, the system automatically detects and broadcasts the server's local LAN IP.
if (!defined('ZIMRX_PUBLIC_ORIGIN')) {
    $envOrigin = getenv('ZIMRX_PUBLIC_ORIGIN');
    define('ZIMRX_PUBLIC_ORIGIN', ($envOrigin !== false && $envOrigin !== '') ? $envOrigin : null);
}
