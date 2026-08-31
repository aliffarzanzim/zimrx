<?php
/**
 * Migration 011 — AI analyzer, SMS settings and templates, and internal chat system
 */
class Migration011AiAnalyzerSmsAndChat {

    public function up(PDO $pdo): void {
        // ---- zimrx_ai_analyzer_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_ai_analyzer_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL UNIQUE,
                provider TEXT,
                api_key_ciphertext TEXT,
                model TEXT,
                settings_json TEXT,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_ai_analyzer_history ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_ai_analyzer_history (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                patient_id " . DbSql::intType() . ",
                visit_id " . DbSql::intType() . ",
                title TEXT,
                prompt TEXT,
                response TEXT,
                metadata_json TEXT,
                created_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_sms_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_sms_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL UNIQUE,
                provider TEXT,
                sender_id TEXT,
                api_settings_json TEXT,
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_sms_templates ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_sms_templates (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                template_name TEXT NOT NULL,
                body TEXT NOT NULL,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_active " . DbSql::intType() . " NOT NULL DEFAULT 1,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_sms_history ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_sms_history (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                patient_id " . DbSql::intType() . ",
                mobile TEXT,
                message TEXT NOT NULL,
                status TEXT,
                provider_response TEXT,
                sent_at TEXT,
                created_at " . DbSql::timestampColumn() . "
            )"
        );

        // ---- zimrx_chat_conversations ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_chat_conversations (
                id " . DbSql::autoIncrement() . ",
                type TEXT NOT NULL DEFAULT 'direct',
                title TEXT,
                description TEXT,
                created_by " . DbSql::intType() . " NOT NULL,
                last_message_id " . DbSql::intType() . " DEFAULT 0,
                last_message_preview TEXT,
                last_message_sender_name TEXT,
                last_message_at TEXT,
                is_archived " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_conv_last_msg ON zimrx_chat_conversations(last_message_at DESC)");

        // ---- zimrx_chat_participants ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_chat_participants (
                conversation_id " . DbSql::intType() . " NOT NULL,
                user_id " . DbSql::intType() . " NOT NULL,
                role TEXT NOT NULL DEFAULT 'member',
                last_read_message_id " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_pinned " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_muted " . DbSql::intType() . " NOT NULL DEFAULT 0,
                joined_at " . DbSql::timestampColumn() . ",
                last_delivered_message_id " . DbSql::intType() . " DEFAULT 0,
                last_active_at TEXT,
                PRIMARY KEY (conversation_id, user_id)
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_participants_user ON zimrx_chat_participants(user_id, last_read_message_id)");

        // ---- zimrx_chat_messages ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_chat_messages (
                id " . DbSql::autoIncrement() . ",
                conversation_id " . DbSql::intType() . " NOT NULL,
                sender_id " . DbSql::intType() . " NOT NULL,
                sender_name TEXT NOT NULL,
                sender_role TEXT NOT NULL,
                message_type TEXT NOT NULL DEFAULT 'text',
                message TEXT NOT NULL,
                metadata_json TEXT,
                is_deleted " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                file_path TEXT,
                file_name TEXT,
                file_type TEXT,
                file_size " . DbSql::intType() . " DEFAULT 0,
                is_hidden " . DbSql::intType() . " DEFAULT 0,
                deleted_by " . DbSql::intType() . " DEFAULT 0,
                deleted_at TEXT
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_messages_conv_id ON zimrx_chat_messages(conversation_id, id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_messages_created ON zimrx_chat_messages(created_at)");

        // ---- zimrx_chat_quick_messages ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_chat_quick_messages (
                id " . DbSql::autoIncrement() . ",
                user_id " . DbSql::intType() . " NOT NULL DEFAULT 0,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                message_type TEXT NOT NULL DEFAULT 'text',
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_active " . DbSql::intType() . " NOT NULL DEFAULT 1,
                is_deleted " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_quick_user ON zimrx_chat_quick_messages(user_id, is_active, is_deleted)");
    }
}
