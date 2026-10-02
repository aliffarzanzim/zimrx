<?php
declare(strict_types=1);

// Cross-driver schema inspector for SQLite, MySQL/MariaDB, and PostgreSQL.
class DbSchema {

    public static function isView(PDO $pdo, string $table): bool {
        return match (DbConnections::driver()) {
            'sqlite'           => self::sqliteObjectType($pdo, $table) === 'view',
            'mysql', 'mariadb' => self::mysqlObjectType($pdo, $table) === 'VIEW',
            'pgsql'            => self::pgsqlObjectType($pdo, $table) === 'VIEW',
            default            => self::sqliteObjectType($pdo, $table) === 'view',
        };
    }

    public static function tableExists(PDO $pdo, string $table): bool {
        return match (DbConnections::driver()) {
            'sqlite'          => self::sqliteTableExists($pdo, $table),
            'mysql', 'mariadb' => self::mysqlTableExists($pdo, $table),
            'pgsql'           => self::pgsqlTableExists($pdo, $table),
            default           => self::sqliteTableExists($pdo, $table),
        };
    }

    private static function sqliteTableExists(PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type IN ('table', 'view') AND name = ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    private static function sqliteObjectType(PDO $pdo, string $table): ?string {
        $stmt = $pdo->prepare("SELECT type FROM sqlite_master WHERE name = ? LIMIT 1");
        $stmt->execute([$table]);
        $type = $stmt->fetchColumn();
        return $type === false ? null : (string)$type;
    }

    private static function mysqlTableExists(PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    private static function mysqlObjectType(PDO $pdo, string $table): ?string {
        $stmt = $pdo->prepare(
            "SELECT TABLE_TYPE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             LIMIT 1"
        );
        $stmt->execute([$table]);
        $type = $stmt->fetchColumn();
        return $type === false ? null : (string)$type;
    }

    private static function pgsqlTableExists(PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM information_schema.tables
             WHERE table_schema = current_schema() AND table_name = ?"
        );
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    private static function pgsqlObjectType(PDO $pdo, string $table): ?string {
        $stmt = $pdo->prepare(
            "SELECT table_type FROM information_schema.tables
             WHERE table_schema = current_schema() AND table_name = ?
             LIMIT 1"
        );
        $stmt->execute([$table]);
        $type = $stmt->fetchColumn();
        return $type === false ? null : (string)$type;
    }

    public static function columnExists(PDO $pdo, string $table, string $column): bool {
        if (!self::tableExists($pdo, $table)) {
            return false;
        }
        return in_array($column, self::columns($pdo, $table), true);
    }

    // Flat list of column names for a table
    public static function columns(PDO $pdo, string $table): array {
        return array_column(self::columnInfo($pdo, $table), 'name');
    }

    // Normalizes column metadata (name, type, pk, notnull, default) across database engines
    public static function columnInfo(PDO $pdo, string $table): array {
        if (!self::tableExists($pdo, $table)) {
            return [];
        }
        return match (DbConnections::driver()) {
            'sqlite'           => self::sqliteColumnInfo($pdo, $table),
            'mysql', 'mariadb' => self::mysqlColumnInfo($pdo, $table),
            'pgsql'            => self::pgsqlColumnInfo($pdo, $table),
            default            => self::sqliteColumnInfo($pdo, $table),
        };
    }

    private static function sqliteColumnInfo(PDO $pdo, string $table): array {
        $quoted = str_replace('"', '""', $table);
        $stmt = $pdo->query("PRAGMA table_info(\"$quoted\")");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => [
            'name'       => (string)$r['name'],
            'type'       => (string)$r['type'],
            'pk'         => (int)$r['pk'],
            'notnull'    => (int)$r['notnull'],
            'dflt_value' => $r['dflt_value'],
        ], $rows);
    }

    private static function mysqlColumnInfo(PDO $pdo, string $table): array {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION"
        );
        $stmt->execute([$table]);
        return array_map(fn($r) => [
            'name'       => (string)$r['COLUMN_NAME'],
            'type'       => (string)$r['DATA_TYPE'],
            'pk'         => strtoupper((string)$r['COLUMN_KEY']) === 'PRI' ? 1 : 0,
            'notnull'    => strtoupper((string)$r['IS_NULLABLE']) === 'NO' ? 1 : 0,
            'dflt_value' => $r['COLUMN_DEFAULT'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function pgsqlColumnInfo(PDO $pdo, string $table): array {
        $stmt = $pdo->prepare(
            "SELECT c.column_name, c.data_type, c.is_nullable, c.column_default,
                    CASE WHEN pk.column_name IS NOT NULL THEN 1 ELSE 0 END AS is_pk
             FROM information_schema.columns c
             LEFT JOIN (
                 SELECT ku.column_name
                 FROM information_schema.table_constraints tc
                 JOIN information_schema.key_column_usage ku
                   ON tc.constraint_name = ku.constraint_name
                  AND tc.table_schema = ku.table_schema
                 WHERE tc.constraint_type = 'PRIMARY KEY'
                   AND tc.table_schema = current_schema()
                   AND tc.table_name = ?
             ) pk ON pk.column_name = c.column_name
             WHERE c.table_schema = current_schema()
               AND c.table_name = ?
             ORDER BY c.ordinal_position"
        );
        $stmt->execute([$table, $table]);
        return array_map(fn($r) => [
            'name'       => (string)$r['column_name'],
            'type'       => (string)$r['data_type'],
            'pk'         => (int)$r['is_pk'],
            'notnull'    => strtoupper((string)$r['is_nullable']) === 'NO' ? 1 : 0,
            'dflt_value' => $r['column_default'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Returns all table names in current schema, excluding internal engine tables
    public static function tables(PDO $pdo): array {
        return match (DbConnections::driver()) {
            'sqlite'           => self::sqliteTables($pdo),
            'mysql', 'mariadb' => self::mysqlTables($pdo),
            'pgsql'            => self::pgsqlTables($pdo),
            default            => self::sqliteTables($pdo),
        };
    }

    private static function sqliteTables(PDO $pdo): array {
        $stmt = $pdo->query(
            "SELECT name FROM sqlite_master
             WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
             ORDER BY name"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    private static function mysqlTables(PDO $pdo): array {
        $stmt = $pdo->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
             ORDER BY TABLE_NAME"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    private static function pgsqlTables(PDO $pdo): array {
        $stmt = $pdo->query(
            "SELECT table_name FROM information_schema.tables
             WHERE table_schema = current_schema() AND table_type = 'BASE TABLE'
             ORDER BY table_name"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }
}

