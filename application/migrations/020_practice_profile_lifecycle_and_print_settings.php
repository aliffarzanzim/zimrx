<?php
declare(strict_types=1);

final class Migration020PracticeProfileLifecycleAndPrintSettings
{
    public function up(PDO $pdo): void
    {
        if (!DbSchema::tableExists($pdo, 'zimrx_doctor_profiles')) {
            throw new RuntimeException('Practice profile schema must be installed first.');
        }

        if (!DbSchema::columnExists($pdo, 'zimrx_doctor_profiles', 'is_deleted')) {
            $pdo->exec('ALTER TABLE zimrx_doctor_profiles ADD COLUMN is_deleted ' . DbSql::intType() . ' NOT NULL DEFAULT 0');
        }
        if (!DbSchema::columnExists($pdo, 'zimrx_doctor_profiles', 'deleted_at')) {
            $pdo->exec('ALTER TABLE zimrx_doctor_profiles ADD COLUMN deleted_at TEXT NULL');
        }

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_doctor_profiles_deleted ON zimrx_doctor_profiles(doctor_id, is_deleted, is_active)');
    }
}
