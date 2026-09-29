<?php
declare(strict_types=1);

// Custom patient address suggestions with frequency ranking per doctor.
class Migration005AddCustomUserAddress {

    public function up(PDO $pdo): void {
        // Saved addresses for quick autocomplete during patient registration
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_address (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                name TEXT NOT NULL,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_pinned " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_hidden " . DbSql::intType() . " NOT NULL DEFAULT 0,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, name)
            )"
        );
    }
}

