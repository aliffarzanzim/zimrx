<?php
/**
 * ZimRx Database Connection Manager
 * 
 * Provides a unified, abstracted database connection layer that supports
 * multiple database engines (SQLite, MySQL, MariaDB, PostgreSQL, etc.)
 * 
 * To migrate databases (e.g., SQLite to MySQL), only modify config.php
 * No changes needed in any API or application code.
 * 
 * Usage:
 *   DbConnections::userdata()   // Get PDO for userdata database
 *   DbConnections::staticDb()   // Get PDO for static database
 *   DbConnections::systemDb()   // Get PDO for system/drug database
 */

class DbConnections {
    /** @var array<string, PDO> Connection pool */
    private static array $connections = [];

    /** @var array Database configuration */
    private static array $config = [
        'driver'   => 'sqlite',  // sqlite, mysql, mariadb, pgsql
        'userdata' => [
            'path' => null,  // For SQLite: file path; For MySQL: database name
            'host' => 'localhost',
            'port' => 3306,
            'user' => 'root',
            'pass' => '',
        ],
        'static' => [
            'path' => null,
            'host' => 'localhost',
            'port' => 3306,
            'user' => 'root',
            'pass' => '',
        ],
        'system' => [
            'path' => null,
            'host' => 'localhost',
            'port' => 3306,
            'user' => 'root',
            'pass' => '',
        ],
    ];

    /**
     * Initialize database configuration
     * Call this once at application startup (before any database access)
     * 
     * Example for SQLite:
     *   DbConnections::configure([
     *       'driver' => 'sqlite',
     *       'userdata' => ['path' => '/path/to/zimrx_userdata.db'],
     *       'static' => ['path' => '/path/to/zimrx_static.db'],
     *   ]);
     * 
     * Example for MySQL:
     *   DbConnections::configure([
     *       'driver' => 'mysql',
     *       'userdata' => [
     *           'host' => 'db.example.com',
     *           'port' => 3306,
     *           'user' => 'zimrx_user',
     *           'pass' => 'password123',
     *           'path' => 'zimrx_userdata',  // Database name
     *       ],
     *       'static' => [
     *           'host' => 'db.example.com',
     *           'user' => 'zimrx_user',
     *           'pass' => 'password123',
     *           'path' => 'zimrx_static',
     *       ],
     *   ]);
     */
    public static function configure(array $config): void {
        self::$config = array_merge(self::$config, $config);
    }

    /**
     * Get current configuration
     */
    public static function getConfig(): array {
        return self::$config;
    }

    /**
     * Get PDO connection to userdata database
     * Contains: patients, zimrx_visits, appointments, zimrx_user_prescriptions, user-specific data
     */
    public static function userdata(): PDO {
        return self::getConnection('userdata');
    }

    /**
     * Get PDO connection to static database
     * Contains: global lookups (zimrx_static_doses, zimrx_static_durations, zimrx_static_instructions, zimrx_static_discount_causes, zimrx_static_pc)
     */
    public static function staticDb(): PDO {
        return self::getConnection('static');
    }

    /**
     * Get PDO connection to system database
     * Contains: drug master data, ICD-11 codes, Standard P/C (zimrx_static_pc), etc.
     */
    public static function systemDb(): PDO {
        return self::getConnection('system');
    }

    /**
     * Get a named connection (internal use)
     */
    private static function getConnection(string $name): PDO {
        if (isset(self::$connections[$name])) {
            return self::$connections[$name];
        }

        $pdo = self::createConnection($name);
        self::$connections[$name] = $pdo;
        return $pdo;
    }

    /**
     * Create a new PDO connection based on driver and configuration
     */
    private static function createConnection(string $name): PDO {
        $config = self::$config[$name] ?? [];
        $driver = self::$config['driver'] ?? 'sqlite';

        $pdo = match ($driver) {
            'sqlite'  => self::createSqliteConnection($config),
            'mysql'   => self::createMysqlConnection($config),
            'mariadb' => self::createMysqlConnection($config),  // MariaDB uses MySQL driver
            'pgsql'   => self::createPostgresConnection($config),
            default   => throw new RuntimeException("Unsupported database driver: $driver"),
        };

        // Apply universal PDO configuration
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    /**
     * Create SQLite connection
     */
    private static function createSqliteConnection(array $config): PDO {
        $path = $config['path'] ?? throw new RuntimeException('SQLite path not configured');
        
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $pdo = new PDO("sqlite:$path");
        
        // SQLite-specific optimizations
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA synchronous = NORMAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');

        return $pdo;
    }

    /**
     * Create MySQL or MariaDB connection
     */
    private static function createMysqlConnection(array $config): PDO {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3306;
        $user = $config['user'] ?? throw new RuntimeException('MySQL user not configured');
        $pass = $config['pass'] ?? '';
        $dbname = $config['path'] ?? throw new RuntimeException('MySQL database name (path) not configured');

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_PERSISTENT => false,
        ]);
    }

    /**
     * Create PostgreSQL connection
     */
    private static function createPostgresConnection(array $config): PDO {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 5432;
        $user = $config['user'] ?? throw new RuntimeException('PostgreSQL user not configured');
        $pass = $config['pass'] ?? '';
        $dbname = $config['path'] ?? throw new RuntimeException('PostgreSQL database name (path) not configured');

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$pass";
        
        return new PDO($dsn);
    }

    /**
     * Clear all cached connections (useful for testing)
     */
    public static function clearCache(): void {
        self::$connections = [];
    }

    /**
     * Get driver name (for diagnostics)
     */
    public static function driver(): string {
        return self::$config['driver'] ?? 'sqlite';
    }
}
