<?php
/**
 * Migration 006 — User drug instructions table
 */
class Migration006InstructionUsageStaticFirst {

    public function up(PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_drug_instructions (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL DEFAULT 1,
                instruction_bn TEXT,
                instruction_en TEXT,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_pinned " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_hidden " . DbSql::intType() . " NOT NULL DEFAULT 0,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                static_id " . DbSql::intType() . ",
                search_alias TEXT,
                default_dosage_form TEXT NOT NULL DEFAULT '[]',
                is_edited " . DbSql::intType() . " NOT NULL DEFAULT 0,
                default_instruction_in_another_row " . DbSql::intType() . " NOT NULL DEFAULT 0,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . "
            )"
        );

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_rx_instruction_doctor_static ON zimrx_user_drug_instructions(doctor_id, static_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_rx_instruction_doctor_usage ON zimrx_user_drug_instructions(doctor_id, usage_count DESC, sort_order ASC, id ASC)");
    }
}
