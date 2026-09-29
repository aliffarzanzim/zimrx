<?php
declare(strict_types=1);

// Central PDO connection manager for ZimRx.
// Keeps reusable singleton connections for userdata (patients, visits) and static/drug databases.
class DbConnections {

    private static array $connections = [];

    private static array $config = [
        'driver'   => 'sqlite', // sqlite, mysql, mariadb, pgsql
        'userdata' => [
            'path' => null, // file path for sqlite, database name for mysql/pgsql
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

    // Merge database settings on application startup
    public static function configure(array $config): void {
        self::$config = array_merge(self::$config, $config);
    }

    public static function getConfig(): array {
        return self::$config;
    }

    // Main clinic database: patients, visits, appointments, settings
    public static function userdata(): PDO {
        return self::getConnection('userdata');
    }

    // Static lookup database: standard doses, durations, instructions
    public static function staticDb(): PDO {
        return self::getConnection('static');
    }

    // Master drug database and clinical reference data
    public static function systemDb(): PDO {
        return self::getConnection('system');
    }

    private static function getConnection(string $name): PDO {
        if (isset(self::$connections[$name])) {
            return self::$connections[$name];
        }

        $pdo = self::createConnection($name);
        self::$connections[$name] = $pdo;
        return $pdo;
    }

    private static function createConnection(string $name): PDO {
        $config = self::$config[$name] ?? [];
        $driver = self::$config['driver'] ?? 'sqlite';

        $pdo = match ($driver) {
            'sqlite'  => self::createSqliteConnection($config),
            'mysql'   => self::createMysqlConnection($config),
            'mariadb' => self::createMysqlConnection($config),
            'pgsql'   => self::createPostgresConnection($config),
            default   => throw new RuntimeException("Unsupported database driver: $driver"),
        };

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    private static function createSqliteConnection(array $config): PDO {
        $path = $config['path'] ?? throw new RuntimeException('SQLite path not configured');
        
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create the database directory: {$directory}");
        }

        $pdo = new PDO("sqlite:$path");
        
        // WAL mode and busy timeout help avoid database locked errors under concurrency
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA synchronous = NORMAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA busy_timeout = 5000;');

        return $pdo;
    }

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

    private static function createPostgresConnection(array $config): PDO {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 5432;
        $user = $config['user'] ?? throw new RuntimeException('PostgreSQL user not configured');
        $pass = $config['pass'] ?? '';
        $dbname = $config['path'] ?? throw new RuntimeException('PostgreSQL database name (path) not configured');

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$pass";
        
        return new PDO($dsn);
    }

    // Reset connections (mainly for unit tests)
    public static function clearCache(): void {
        self::$connections = [];
    }

    public static function driver(): string {
        return self::$config['driver'] ?? 'sqlite';
    }
}

