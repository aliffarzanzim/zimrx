<?php
declare(strict_types=1);

// Chief complaints (PC/CC) storage, frequency tracking, and suggestion priority order.
class Migration004PcSettings {

    private array $defaultPriorities = [
        ['source' => 'most_used', 'sort_order' => 1, 'is_enabled' => 1],
        ['source' => 'custom',    'sort_order' => 2, 'is_enabled' => 1],
        ['source' => 'static_pc', 'sort_order' => 3, 'is_enabled' => 1],
    ];

    public function up(PDO $pdo): void {
        // Doctor vocabulary for chief complaints with usage counters and pinned items
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_pc (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                category TEXT DEFAULT 'cc',
                term TEXT NOT NULL,
                usage_count " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_pinned " . DbSql::intType() . " NOT NULL DEFAULT 0,
                is_hidden " . DbSql::intType() . " NOT NULL DEFAULT 0,
                sort_order " . DbSql::intType() . " NOT NULL DEFAULT 0,
                source TEXT DEFAULT 'user',
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, category, term)
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_user_pc_lookup ON zimrx_user_pc(doctor_id, category, term)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_user_pc_doctor_category_usage ON zimrx_user_pc(doctor_id, category, usage_count)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_user_pc_doctor_category_recent ON zimrx_user_pc(doctor_id, category, updated_at)");

        // Autocomplete search source priorities and toggle settings
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_pc_settings (
                id " . DbSql::autoIncrement() . ",
                doctor_id " . DbSql::intType() . " NOT NULL,
                setting_key TEXT NOT NULL,
                setting_value TEXT,
                source TEXT NOT NULL DEFAULT '',
                term TEXT NOT NULL DEFAULT '',
                sort_order " . DbSql::intType() . " DEFAULT 0,
                is_enabled " . DbSql::intType() . " NOT NULL DEFAULT 1,
                created_at " . DbSql::timestampColumn() . ",
                updated_at " . DbSql::timestampColumn() . ",
                UNIQUE(doctor_id, setting_key, source, term)
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_zimrx_user_pc_settings_doctor_key ON zimrx_user_pc_settings(doctor_id, setting_key, source, term)");

        // Normalize legacy source names (snomed -> static_pc, drop retired icd entries)
        try {
            $pdo->exec("UPDATE zimrx_user_pc_settings SET source = 'static_pc' WHERE source = 'snomed'");
            $pdo->exec("DELETE FROM zimrx_user_pc_settings WHERE setting_key = 'source_priority' AND source = 'icd'");
        } catch (Throwable $_) {}

        // Seed initial complaint search priority order for doctor 1
        $stmt = $pdo->prepare(
            DbSql::insertIgnore(
                'zimrx_user_pc_settings',
                'doctor_id, setting_key, source, term, sort_order, is_enabled',
                ':doctor_id, :setting_key, :source, :term, :sort_order, :is_enabled'
            )
        );
        foreach ($this->defaultPriorities as $row) {
            $stmt->execute([
                'doctor_id'   => 1,
                'setting_key' => 'source_priority',
                'source'      => $row['source'],
                'term'        => '',
                'sort_order'  => $row['sort_order'],
                'is_enabled'  => $row['is_enabled'],
            ]);
        }
    }
}

