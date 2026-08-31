<?php
/**
 * ZimRx Central Configuration
 *
 * This file serves as the single source of truth for:
 * - Application paths
 * - Database engine and connection settings
 * - System-wide settings
 *
 * DATABASE MIGRATION NOTE:
 * To switch database engines (SQLite → MySQL/MariaDB):
 * 1. Update DB_DRIVER and connection configs below
 * 2. No code changes needed in any API files
 */

// =====================================================================
// 1. APPLICATION PATHS
// =====================================================================

define('ZIMRX_BASE_DIR', __DIR__);
define('ZIMRX_USERDATA_DIR', ZIMRX_BASE_DIR . '/userdata');
define('ZIMRX_DB_DIR', ZIMRX_USERDATA_DIR . '/database');
define('ZIMRX_UPLOADS_DIR', ZIMRX_USERDATA_DIR . '/uploads');

define('ZIMRX_ASSETS_DB_DIR', ZIMRX_BASE_DIR . '/assets/database');

// Legacy path constants (for backward compatibility)
define('ZIMRX_DB_USERDATA', ZIMRX_DB_DIR . '/zimrx_userdata.db');
define('ZIMRX_DB_SYSTEMDATA', ZIMRX_ASSETS_DB_DIR . '/zimrx_drugs.db');
define('ZIMRX_DB_STATIC', ZIMRX_ASSETS_DB_DIR . '/zimrx_static.db');
define('ZIMRX_DB_ICD11', ZIMRX_ASSETS_DB_DIR . '/zimrx_icd11_dx.db');
define('ZIMRX_DB_SNOMED', ZIMRX_ASSETS_DB_DIR . '/zimrx_snomed_pc.db');
define('ZIMRX_DB_ADDRESSES', ZIMRX_DB_STATIC);

// =====================================================================
// 2. DATABASE CONFIGURATION
// =====================================================================

/**
 * Database Driver: 'sqlite', 'mysql', 'mariadb', or 'pgsql'
 *
 * SQLITE (Current)
 * - Good for: Single-user, development, small deployments
 * - Connection: Uses file paths only
 *
 * MYSQL / MARIADB (Future)
 * - Good for: Multi-user, production, cloud deployments
 * - Connection: Requires host, port, user, password
 *
 * PGSQL
 * - Good for: Large-scale deployments, advanced features
 * - Connection: Similar to MySQL but PostgreSQL syntax
 */
define('DB_DRIVER', 'sqlite');

/**
 * Database Configuration
 *
 * For SQLite: Only 'path' is used
 * For MySQL/MariaDB/PostgreSQL: host, port, user, pass are used; 'path' is database name
 */
define('DB_CONFIG', [
    // Current: SQLite Configuration
    'driver'   => DB_DRIVER,

    'userdata' => [
        'path' => ZIMRX_DB_DIR . '/zimrx_userdata.db',
        'host' => 'localhost',      // For future MySQL/MariaDB migration
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

// =====================================================================
// MIGRATION EXAMPLES
// =====================================================================

/*
TO MIGRATE FROM SQLITE TO MYSQL:

1. Export current SQLite databases to SQL:
   sqlite3 zimrx_userdata.db .dump > zimrx_userdata.sql

2. Import into MySQL:
   mysql -u root -p zimrx_userdata < zimrx_userdata.sql

3. Update DB_CONFIG in config.php:

   define('DB_DRIVER', 'mysql');

   define('DB_CONFIG', [
       'driver'   => 'mysql',

       'userdata' => [
           'path' => 'zimrx_userdata',  // Database name (was file path)
           'host' => '192.168.1.100',   // MySQL server IP
           'port' => 3306,
           'user' => 'zimrx_user',
           'pass' => 'secure_password',
       ],

       'static' => [
           'path' => 'zimrx_static',
           'host' => '192.168.1.100',
           'port' => 3306,
           'user' => 'zimrx_user',
           'pass' => 'secure_password',
       ],

       'system' => [
           'path' => 'zimrx_system',
           'host' => '192.168.1.100',
           'port' => 3306,
           'user' => 'zimrx_user',
           'pass' => 'secure_password',
       ],
   ]);

4. No PHP code changes needed - everything works with new database!

*/

// =====================================================================
// 3. ENVIRONMENT (Optional)
// =====================================================================

// define('ZIMRX_ENV', 'development');
