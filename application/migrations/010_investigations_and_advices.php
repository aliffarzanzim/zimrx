<?php
/**
 * Migration 010 — Investigations, advices, and their templates and settings
 */
class Migration010InvestigationsAndAdvices {

    public function up(PDO $pdo): void {
        // ---- zimrx_user_investigations ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_investigations (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                type TEXT DEFAULT 'investigation',
                name TEXT NOT NULL,
                price REAL,
                description TEXT,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_pinned " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_hidden " . DbSql::intType() . " NOT NULL DEFAULT 0,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_user_investigations_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_investigations_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                setting_key TEXT NOT NULL,
                setting_value TEXT,
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, setting_key)
            )"
        );

        // ---- zimrx_user_investigations_templates ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_investigations_templates (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                template_name TEXT NOT NULL,
                content_json TEXT,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_pinned " . DbSql::intType() . " NOT NULL DEFAULT 0,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_user_advices ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_advices (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                name TEXT NOT NULL,
                body TEXT,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_pinned " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_hidden " . DbSql::intType() . " NOT NULL DEFAULT 0,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                static_id " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_edited " . DbSql::intType() . " NOT NULL DEFAULT 0,
                advice_en TEXT,
                category_bn TEXT NOT NULL DEFAULT '',
                category_en TEXT NOT NULL DEFAULT '',
                category_search_alias TEXT,
                search_alias TEXT
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_user_advices_doctor_static ON zimrx_user_advices(doctor_id, static_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_user_advices_doctor_usage ON zimrx_user_advices(doctor_id, usage_count DESC, sort_order ASC, id ASC)");

        // ---- zimrx_user_advices_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_advices_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                setting_key TEXT NOT NULL,
                setting_value TEXT,
                updated_at " . DbSql::timestampColumn() . ",
                advice_id " . DbSql::intType() . " NOT NULL DEFAULT 0,
                UNIQUE(doctor_id, setting_key)
            )"
        );
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_zimrx_user_advices_settings_doctor_setting ON zimrx_user_advices_settings(doctor_id, advice_id, setting_key)");

        // ---- zimrx_user_advices_template ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_advices_template (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                template_name TEXT NOT NULL,
                content_json TEXT,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_pinned " . DbSql::intType() . " NOT NULL DEFAULT 0,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );
    }
}
