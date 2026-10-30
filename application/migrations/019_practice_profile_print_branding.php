<?php
declare(strict_types=1);

final class Migration019PracticeProfilePrintBranding
{
    public function up(PDO $pdo): void
    {
        if (!DbSchema::tableExists($pdo, 'zimrx_doctor_profiles')) {
            throw new RuntimeException('Practice profile schema must be installed before profile print branding.');
        }

        if (!DbSchema::columnExists($pdo, 'zimrx_doctor_profiles', 'prescription_footer_note')) {
            $pdo->exec("ALTER TABLE zimrx_doctor_profiles ADD COLUMN prescription_footer_note TEXT NOT NULL DEFAULT ''");
        }
    }
}
