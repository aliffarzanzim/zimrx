<?php
declare(strict_types=1);

// Doctor medication instruction preferences and template visibility toggles.
class Migration007InstructionTemplateSettings {

    public function up(PDO $pdo): void {
        // Per-instruction configuration (timing defaults, language preferences, meal relation)
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

        // Retained fallback table to support legacy queries referencing the alternate spelling
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

