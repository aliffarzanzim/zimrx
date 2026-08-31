<?php
/**
 * Migration 007 — Drug instruction settings tables
 */
class Migration007InstructionTemplateSettings {

    public function up(PDO $pdo): void {
        // ---- zimrx_user_drug_instructions_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_drug_instructions_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL DEFAULT 1,
                instruction_id " . DbSql::intType() . " NOT NULL DEFAULT 0,
                setting_key TEXT NOT NULL,
                setting_value TEXT,
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, instruction_id, setting_key)
            )"
        );

        // ---- zimrx_user_drug_instructionss_settings ----
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_drug_instructionss_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL DEFAULT 1,
                instruction_id " . DbSql::intType() . " NOT NULL DEFAULT 0,
                setting_key TEXT NOT NULL,
                setting_value TEXT,
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, instruction_id, setting_key)
            )"
        );
    }
}
