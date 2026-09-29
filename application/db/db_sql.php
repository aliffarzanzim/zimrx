<?php
declare(strict_types=1);

// SQL dialect generator for SQLite, MySQL/MariaDB, and PostgreSQL cross-compatibility.
class DbSql {

    // Handles INSERT OR IGNORE differences across engines (PostgreSQL uses ON CONFLICT DO NOTHING)
    public static function insertIgnore(string $table, string $cols, string $vals): string {
        $driver = DbConnections::driver();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            return "INSERT IGNORE INTO `$table` ($cols) VALUES ($vals)";
        }
        if ($driver === 'pgsql') {
            return "INSERT INTO \"$table\" ($cols) VALUES ($vals) ON CONFLICT DO NOTHING";
        }
        return "INSERT OR IGNORE INTO \"$table\" ($cols) VALUES ($vals)";
    }

    public static function insertIgnoreSelect(string $table, string $cols, string $selectSql): string {
        $driver = DbConnections::driver();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            return "INSERT IGNORE INTO `$table` ($cols) $selectSql";
        }
        if ($driver === 'pgsql') {
            return "INSERT INTO \"$table\" ($cols) $selectSql ON CONFLICT DO NOTHING";
        }
        return "INSERT OR IGNORE INTO \"$table\" ($cols) $selectSql";
    }

    // Builds upsert clauses (ON CONFLICT DO UPDATE for SQLite/Postgres vs ON DUPLICATE KEY UPDATE for MySQL)
    public static function upsert(
        string $conflictCols,
        array  $updateCols,
        array  $updateExprs = [],
        string $tableAlias  = ''
    ): string {
        $driver = DbConnections::driver();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $setParts = [];
            foreach ($updateCols as $col) {
                if (isset($updateExprs[$col])) {
                    // Adapt table.col or EXCLUDED.col expressions to MySQL syntax
                    $expr = str_replace(
                        ["EXCLUDED.$col", "$tableAlias.$col", "excluded.$col"],
                        ["`$col`",        "`$col`",           "`$col`"],
                        $updateExprs[$col]
                    );
                    $setParts[] = "`$col` = $expr";
                } else {
                    $setParts[] = "`$col` = VALUES(`$col`)";
                }
            }
            return "ON DUPLICATE KEY UPDATE " . implode(",\n            ", $setParts);
        }

        $setParts = [];
        foreach ($updateCols as $col) {
            if (isset($updateExprs[$col])) {
                $setParts[] = "\"$col\" = " . $updateExprs[$col];
            } else {
                $setParts[] = "\"$col\" = EXCLUDED.\"$col\"";
            }
        }
        return "ON CONFLICT($conflictCols) DO UPDATE SET\n            " . implode(",\n            ", $setParts);
    }

    public static function autoIncrement(): string {
        return match (DbConnections::driver()) {
            'mysql', 'mariadb' => 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'pgsql'            => 'SERIAL PRIMARY KEY',
            default            => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };
    }

    public static function timestampColumn(bool $notNull = true): string {
        $nn = $notNull ? 'NOT NULL ' : '';
        return match (DbConnections::driver()) {
            'mysql', 'mariadb' => "DATETIME {$nn}DEFAULT CURRENT_TIMESTAMP",
            'pgsql'            => "TIMESTAMPTZ {$nn}DEFAULT NOW()",
            default            => "TEXT {$nn}DEFAULT CURRENT_TIMESTAMP",
        };
    }

    public static function now(): string {
        return match (DbConnections::driver()) {
            'pgsql' => 'NOW()',
            default => 'CURRENT_TIMESTAMP',
        };
    }

    // Case-insensitive LIKE expression (handles Postgres ILIKE)
    public static function ilike(string $col, string $ph): string {
        return match (DbConnections::driver()) {
            'pgsql' => "$col ILIKE $ph",
            default => "$col LIKE $ph",
        };
    }

    public static function groupConcat(string $col, string $sep = ', '): string {
        $escapedSep = str_replace("'", "''", $sep);
        return match (DbConnections::driver()) {
            'pgsql' => "STRING_AGG($col, '$escapedSep')",
            default => "GROUP_CONCAT($col, '$escapedSep')",
        };
    }

    public static function intType(): string {
        return match (DbConnections::driver()) {
            'mysql', 'mariadb' => 'INT',
            default            => 'INTEGER',
        };
    }

    public static function quoteIdentifier(string $name): string {
        return match (DbConnections::driver()) {
            'mysql', 'mariadb' => '`' . str_replace('`', '``', $name) . '`',
            default            => '"' . str_replace('"', '""', $name) . '"',
        };
    }
}

