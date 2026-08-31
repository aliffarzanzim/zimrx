<?php
/**
 * DbSql — Driver-aware SQL dialect helper
 *
 * Encapsulates every SQL fragment or DDL keyword that differs between
 * SQLite, MySQL/MariaDB, and PostgreSQL.
 *
 * Usage:
 *   $sql = DbSql::insertIgnore('zimrx_doctors', 'id, doctor_code, display_name', ':id, :code, :name');
 *   $sql = DbSql::upsert('doctor_id, source', ['sort_order', 'is_enabled', 'updated_at']);
 *   $sql = DbSql::autoIncrement();                  // DDL primary key fragment
 *   $sql = DbSql::ilike('term', ':search');         // case-insensitive LIKE
 *   $sql = DbSql::groupConcat('col', ', ');         // GROUP_CONCAT or equivalent
 *   $sql = DbSql::currentTimestamp();               // CURRENT_TIMESTAMP (portable)
 *
 * Depends on DbConnections::driver() to select the correct variant.
 * Requires: application/DbConnections.php must be loaded before this file.
 */
class DbSql {

    // ----------------------------------------------------------------
    // INSERT OR IGNORE
    // ----------------------------------------------------------------

    /**
     * Build an INSERT-or-ignore statement for the given table/columns/values.
     *
     * SQLite:     INSERT OR IGNORE INTO "t" (cols) VALUES (vals)
     * MySQL:      INSERT IGNORE INTO `t` (cols) VALUES (vals)
     * PostgreSQL: INSERT INTO "t" (cols) VALUES (vals) ON CONFLICT DO NOTHING
     *
     * @param string $table  Table name (unquoted)
     * @param string $cols   Column list, already formatted e.g. "id, name, created_at"
     * @param string $vals   Placeholder list, already formatted e.g. ":id, :name, CURRENT_TIMESTAMP"
     */
    public static function insertIgnore(string $table, string $cols, string $vals): string {
        $driver = DbConnections::driver();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            return "INSERT IGNORE INTO `$table` ($cols) VALUES ($vals)";
        }
        if ($driver === 'pgsql') {
            return "INSERT INTO \"$table\" ($cols) VALUES ($vals) ON CONFLICT DO NOTHING";
        }
        // SQLite (default)
        return "INSERT OR IGNORE INTO \"$table\" ($cols) VALUES ($vals)";
    }

    /**
     * Build an INSERT-or-ignore statement where values come from a SELECT.
     *
     * SQLite:     INSERT OR IGNORE INTO "t" (cols) SELECT ...
     * MySQL:      INSERT IGNORE INTO `t` (cols) SELECT ...
     * PostgreSQL: INSERT INTO "t" (cols) SELECT ... ON CONFLICT DO NOTHING
     */
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

    // ----------------------------------------------------------------
    // UPSERT (INSERT ... ON CONFLICT DO UPDATE)
    // ----------------------------------------------------------------

    /**
     * Build the ON CONFLICT ... DO UPDATE / ON DUPLICATE KEY UPDATE clause
     * to append to an INSERT statement.
     *
     * SQLite / PostgreSQL:
     *   ON CONFLICT(conflictCols) DO UPDATE SET col = EXCLUDED.col, ...
     *
     * MySQL / MariaDB:
     *   ON DUPLICATE KEY UPDATE col = VALUES(col), ...
     *
     * @param string $conflictCols  Comma-separated conflict column(s), e.g. "doctor_id, source"
     * @param array  $updateCols    Columns to update on conflict, e.g. ['sort_order', 'updated_at']
     * @param array  $updateExprs   Optional map of col => raw expression override.
     *                              E.g. ['usage_count' => 'table.usage_count + 1']
     *                              For columns not in this map, EXCLUDED.col / VALUES(col) is used.
     * @param string $tableAlias    The table alias used for self-reference in SQLite expressions
     *                              (e.g. 'zimrx_user_pc' in 'zimrx_user_pc.usage_count + 1')
     */
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
                    // Replace SQLite/PG self-reference idiom with MySQL equivalent:
                    // "tablename.col + 1" → "`col` + 1"  and  "EXCLUDED.col" → "VALUES(`col`)"
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

        // SQLite + PostgreSQL share the ON CONFLICT syntax
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

    // ----------------------------------------------------------------
    // DDL helpers
    // ----------------------------------------------------------------

    /**
     * Return the DDL fragment for a single-column INTEGER auto-increment primary key.
     *
     * SQLite:     INTEGER PRIMARY KEY AUTOINCREMENT
     * MySQL:      INT NOT NULL AUTO_INCREMENT PRIMARY KEY
     * PostgreSQL: SERIAL PRIMARY KEY
     */
    public static function autoIncrement(): string {
        return match (DbConnections::driver()) {
            'mysql', 'mariadb' => 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'pgsql'            => 'SERIAL PRIMARY KEY',
            default            => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };
    }

    /**
     * Return the correct type for a timestamp column with a default.
     *
     * SQLite:     TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
     * MySQL:      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
     * PostgreSQL: TIMESTAMPTZ NOT NULL DEFAULT NOW()
     */
    public static function timestampColumn(bool $notNull = true): string {
        $nn = $notNull ? 'NOT NULL ' : '';
        return match (DbConnections::driver()) {
            'mysql', 'mariadb' => "DATETIME {$nn}DEFAULT CURRENT_TIMESTAMP",
            'pgsql'            => "TIMESTAMPTZ {$nn}DEFAULT NOW()",
            default            => "TEXT {$nn}DEFAULT CURRENT_TIMESTAMP",
        };
    }

    /**
     * Return the correct NOW() / CURRENT_TIMESTAMP expression for inline SQL.
     */
    public static function now(): string {
        return match (DbConnections::driver()) {
            'pgsql' => 'NOW()',
            default => 'CURRENT_TIMESTAMP',
        };
    }

    // ----------------------------------------------------------------
    // Query helpers
    // ----------------------------------------------------------------

    /**
     * Build a case-insensitive LIKE expression.
     *
     * SQLite:     col LIKE :ph      (SQLite LIKE is case-insensitive for ASCII by default)
     * MySQL:      col LIKE :ph      (depends on collation; utf8mb4_general_ci is CI)
     * PostgreSQL: col ILIKE :ph
     *
     * @param string $col  Column reference, e.g. "term" or "p.full_name"
     * @param string $ph   Placeholder, e.g. ":search" or "?"
     */
    public static function ilike(string $col, string $ph): string {
        return match (DbConnections::driver()) {
            'pgsql' => "$col ILIKE $ph",
            default => "$col LIKE $ph",
        };
    }

    /**
     * Build a GROUP_CONCAT / STRING_AGG expression.
     *
     * SQLite / MySQL: GROUP_CONCAT(col, sep)
     * PostgreSQL:     STRING_AGG(col, 'sep')
     *
     * @param string $col  Column or expression to aggregate
     * @param string $sep  Separator string
     */
    public static function groupConcat(string $col, string $sep = ', '): string {
        $escapedSep = str_replace("'", "''", $sep);
        return match (DbConnections::driver()) {
            'pgsql' => "STRING_AGG($col, '$escapedSep')",
            default => "GROUP_CONCAT($col, '$escapedSep')",
        };
    }

    /**
     * Return the integer column type keyword.
     *
     * SQLite:     INTEGER
     * MySQL:      INT
     * PostgreSQL: INTEGER
     */
    public static function intType(): string {
        return match (DbConnections::driver()) {
            'mysql', 'mariadb' => 'INT',
            default            => 'INTEGER',
        };
    }

    /**
     * Quote an identifier (table or column name) for the active driver.
     */
    public static function quoteIdentifier(string $name): string {
        return match (DbConnections::driver()) {
            'mysql', 'mariadb' => '`' . str_replace('`', '``', $name) . '`',
            default            => '"' . str_replace('"', '""', $name) . '"',
        };
    }
}
