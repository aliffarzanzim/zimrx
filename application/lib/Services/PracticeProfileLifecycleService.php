<?php
declare(strict_types=1);

namespace ZimRx\Services;

use PDO;
use RuntimeException;
use Throwable;

final class PracticeProfileLifecycleService
{
    private const PROFILE_TABLES = [
        'zimrx_patients',
        'zimrx_patients_change_log',
        'zimrx_patient_particulars_audit',
        'zimrx_patient_metric_readings',
        'zimrx_appointments',
        'zimrx_payments',
        'zimrx_visit_vitals',
        'zimrx_visits',
        'zimrx_visit_revisions',
        'zimrx_patient_sequences',
    ];

    public function __construct(private readonly PDO $pdo) {}

    public function removeProfile(int $profileId, int $doctorId): void
    {
        $this->changeProfileState($profileId, $doctorId, true);
        $this->clearActiveSession($profileId);
    }

    public function deleteProfileAndPatientData(int $profileId, int $doctorId, string $confirmation): void
    {
        $files = [];
        $this->pdo->beginTransaction();
        try {
            $profile = $this->ownedProfile($profileId, $doctorId);
            if (!hash_equals('DELETE ' . (string)$profile['profile_name'], $confirmation)) {
                throw new RuntimeException('The deletion confirmation text does not match the practice profile name.');
            }
            $this->assertAnotherActiveProfile($profileId, $doctorId);
            $this->assertNoExternalPatientReferences($profileId, $doctorId);

            $patientIds = $this->collectIds('zimrx_patients', 'id', 'profile_id = :profile_id', ['profile_id' => $profileId]);
            $visitIds = $this->collectIds('zimrx_visits', 'id', 'profile_id = :profile_id', ['profile_id' => $profileId]);
            $files = $this->collectUploadFiles($profileId);
            $this->writeDeletionTombstones($patientIds, $visitIds, $profileId);
            $this->deletePatientChildren($profileId, $doctorId);

            foreach (self::PROFILE_TABLES as $table) {
                if (\DbSchema::tableExists($this->pdo, $table) && \DbSchema::columnExists($this->pdo, $table, 'profile_id')) {
                    $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE profile_id = :profile_id");
                    $stmt->execute(['profile_id' => $profileId]);
                }
            }

            $remaining = $this->pdo->prepare('SELECT COUNT(*) FROM zimrx_doctor_profiles WHERE doctor_id = :doctor_id AND is_active = 1 AND is_deleted = 0 AND id <> :id');
            $remaining->execute(['doctor_id' => $doctorId, 'id' => $profileId]);
            if ((int)$remaining->fetchColumn() < 1) {
                throw new RuntimeException('At least one active practice profile must remain.');
            }

            $delete = $this->pdo->prepare('DELETE FROM zimrx_doctor_profiles WHERE id = :id AND doctor_id = :doctor_id AND is_deleted = 0');
            $delete->execute(['id' => $profileId, 'doctor_id' => $doctorId]);
            if ($delete->rowCount() !== 1) {
                throw new RuntimeException('Practice profile could not be deleted.');
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }

        $this->clearActiveSession($profileId);
        $this->removeStoredFiles($files, $profileId);
    }

    private function changeProfileState(int $profileId, int $doctorId, bool $deleted): void
    {
        $this->pdo->beginTransaction();
        try {
            $profile = $this->ownedProfile($profileId, $doctorId);
            $this->assertAnotherActiveProfile($profileId, $doctorId);
            if ((int)$profile['is_default'] === 1) {
                $promote = $this->pdo->prepare(
                    'UPDATE zimrx_doctor_profiles SET is_default = 1, updated_at = ' . \DbSql::now() . '
                     WHERE id = (
                         SELECT id FROM zimrx_doctor_profiles
                         WHERE doctor_id = :doctor_id AND is_active = 1 AND is_deleted = 0 AND id <> :id
                         ORDER BY id ASC LIMIT 1
                     )'
                );
                $promote->execute(['doctor_id' => $doctorId, 'id' => $profileId]);
                if ($promote->rowCount() !== 1) {
                    throw new RuntimeException('No replacement default profile is available.');
                }
            }
            $sql = $deleted
                ? 'UPDATE zimrx_doctor_profiles SET is_active = 0, is_default = 0, is_deleted = 1, deleted_at = ' . \DbSql::now() . ', archived_at = ' . \DbSql::now() . ', updated_at = ' . \DbSql::now() . ' WHERE id = :id AND doctor_id = :doctor_id AND is_deleted = 0'
                : 'UPDATE zimrx_doctor_profiles SET is_active = 0, is_default = 0, archived_at = ' . \DbSql::now() . ', updated_at = ' . \DbSql::now() . ' WHERE id = :id AND doctor_id = :doctor_id AND is_deleted = 0';
            $update = $this->pdo->prepare($sql);
            $update->execute(['id' => $profileId, 'doctor_id' => $doctorId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Practice profile could not be removed.');
            }
            $this->pdo->commit();
            return;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function ownedProfile(int $profileId, int $doctorId): array
    {
        if ($profileId <= 0 || $doctorId <= 0) {
            throw new RuntimeException('A valid practice profile is required.');
        }
        $access = (new PracticeProfileService($this->pdo))->managedByCurrentUser();
        foreach ($access as $row) {
            if ((int)$row['id'] === $profileId && (int)$row['doctor_id'] === $doctorId) {
                $stmt = $this->pdo->prepare(
                    'SELECT id, doctor_id, profile_name, is_active, is_default FROM zimrx_doctor_profiles
                     WHERE id = :id AND doctor_id = :doctor_id AND is_deleted = 0 LIMIT 1'
                );
                $stmt->execute(['id' => $profileId, 'doctor_id' => $doctorId]);
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($profile)) {
                    return $profile;
                }
            }
        }
        throw new RuntimeException('You do not have access to that practice profile.');
    }

    private function assertAnotherActiveProfile(int $profileId, int $doctorId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM zimrx_doctor_profiles
             WHERE doctor_id = :doctor_id AND is_active = 1 AND is_deleted = 0 AND id <> :id'
        );
        $stmt->execute(['doctor_id' => $doctorId, 'id' => $profileId]);
        if ((int)$stmt->fetchColumn() < 1) {
            throw new RuntimeException('Create another active practice profile before removing this one.');
        }
    }

    private function assertNoExternalPatientReferences(int $profileId, int $doctorId): void
    {
        foreach ([
            'zimrx_appointments',
            'zimrx_visits',
            'zimrx_payments',
            'zimrx_visit_vitals',
            'zimrx_patient_metric_readings',
            'zimrx_visit_revisions',
            'zimrx_patients_change_log',
            'zimrx_patient_particulars_audit',
        ] as $table) {
            if (!\DbSchema::tableExists($this->pdo, $table) || !\DbSchema::columnExists($this->pdo, $table, 'profile_id')) {
                continue;
            }
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM {$table} other_row
                WHERE (other_row.profile_id IS NULL OR other_row.profile_id <> :row_profile_id)
                   AND other_row.patient_id IN (SELECT id FROM zimrx_patients WHERE profile_id = :patient_profile_id)"
            );
            $stmt->execute(['row_profile_id' => $profileId, 'patient_profile_id' => $profileId]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('Patient data is referenced by another profile. Disable sharing and resolve those records before deleting this profile.');
            }
        }
        if (\DbSchema::tableExists($this->pdo, 'zimrx_patient_doctor_access')) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM zimrx_patient_doctor_access access_row
                 WHERE access_row.doctor_id <> :doctor_id
                   AND access_row.patient_id IN (SELECT id FROM zimrx_patients WHERE profile_id = :profile_id)'
            );
            $stmt->execute(['doctor_id' => $doctorId, 'profile_id' => $profileId]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('One or more patients are shared with another doctor. Revoke that access before deleting their data.');
            }
        }
        foreach ([
            ['zimrx_prescription_drugs', 'patient_id', 'visit_id'],
            ['zimrx_prescription_clinical_findings', null, 'visit_id'],
            ['zimrx_ai_analyzer_history', 'patient_id', 'visit_id'],
            ['zimrx_sms_history', 'patient_id', null],
            ['zimrx_user_patient_referrals', null, 'visit_record_id'],
        ] as [$table, $patientColumn, $visitColumn]) {
            if (!\DbSchema::tableExists($this->pdo, $table) || !\DbSchema::columnExists($this->pdo, $table, 'doctor_id')) {
                continue;
            }
            $relations = [];
            if ($patientColumn !== null && \DbSchema::columnExists($this->pdo, $table, $patientColumn)) {
                $relations[] = "{$patientColumn} IN (SELECT id FROM zimrx_patients WHERE profile_id = :patient_profile)";
            }
            if ($visitColumn !== null && \DbSchema::columnExists($this->pdo, $table, $visitColumn)) {
                $relations[] = "{$visitColumn} IN (SELECT id FROM zimrx_visits WHERE profile_id = :visit_profile)";
            }
            if ($relations === []) {
                continue;
            }
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE doctor_id <> :doctor_id AND (" . implode(' OR ', $relations) . ')'
            );
            $params = ['doctor_id' => $doctorId];
            if ($patientColumn !== null && \DbSchema::columnExists($this->pdo, $table, $patientColumn)) {
                $params['patient_profile'] = $profileId;
            }
            if ($visitColumn !== null && \DbSchema::columnExists($this->pdo, $table, $visitColumn)) {
                $params['visit_profile'] = $profileId;
            }
            $stmt->execute($params);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('Clinical data for these patients is owned by another doctor and cannot be removed with this profile.');
            }
        }
    }

    private function collectIds(string $table, string $column, string $where, array $params): array
    {
        $stmt = $this->pdo->prepare("SELECT {$column} FROM {$table} WHERE {$where}");
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function collectUploadFiles(int $profileId): array
    {
        $paths = [];
        if (\DbSchema::tableExists($this->pdo, 'zimrx_mobile_upload_queue')) {
            $stmt = $this->pdo->prepare(
                'SELECT file_path FROM zimrx_mobile_upload_queue
                 WHERE patient_id IN (SELECT id FROM zimrx_patients WHERE profile_id = :patient_profile_id)
                    OR visit_record_id IN (SELECT id FROM zimrx_visits WHERE profile_id = :visit_profile_id)'
            );
            $stmt->execute(['patient_profile_id' => $profileId, 'visit_profile_id' => $profileId]);
            $paths = array_filter($stmt->fetchAll(PDO::FETCH_COLUMN), 'is_string');
        }
        $profile = $this->pdo->prepare('SELECT settings_json FROM zimrx_doctor_profiles WHERE id = :profile_id LIMIT 1');
        $profile->execute(['profile_id' => $profileId]);
        $settings = json_decode((string)$profile->fetchColumn(), true);
        $print = is_array($settings['prescription_print'] ?? null) ? $settings['prescription_print'] : [];
        $header = is_array($print['header_settings'] ?? null) ? $print['header_settings'] : [];
        foreach (['logo_path', 'full_body_header_path', 'bg_image_path'] as $key) {
            if (is_string($header[$key] ?? null) && $header[$key] !== '') {
                $paths[] = $header[$key];
            }
        }
        $layout = is_array($print['layout_settings'] ?? null) ? $print['layout_settings'] : [];
        $advanced = json_decode((string)($layout['print_settings_json'] ?? '{}'), true);
        if (is_array($advanced) && is_string($advanced['stamp_path'] ?? null) && $advanced['stamp_path'] !== '') {
            $paths[] = $advanced['stamp_path'];
        }
        $visits = $this->pdo->prepare('SELECT clinical_snapshot_json, prescription_html, rich_text_json FROM zimrx_visits WHERE profile_id = :profile_id');
        $visits->execute(['profile_id' => $profileId]);
        foreach ($visits->fetchAll(PDO::FETCH_ASSOC) as $visit) {
            foreach ($visit as $value) {
                if (is_string($value) && preg_match_all("~api/view_report\\.php\\?file=([^\"&\\s]+)~", $value, $matches)) {
                    foreach ($matches[1] as $file) {
                        $paths[] = 'api/view_report.php?file=' . rawurldecode($file);
                    }
                }
            }
        }
        return array_values(array_unique($paths));
    }

    private function writeDeletionTombstones(array $patientIds, array $visitIds, int $profileId): void
    {
        if (!\DbSchema::tableExists($this->pdo, 'zimrx_sync_changes')) {
            return;
        }
        require_once __DIR__ . '/SyncJournalService.php';
        $entities = [
            ['zimrx_patients', $patientIds, 'patient'],
            ['zimrx_visits', $visitIds, 'visit'],
            ['zimrx_patient_metric_readings', $this->collectIds('zimrx_patient_metric_readings', 'id', 'profile_id = :profile_id', ['profile_id' => $profileId]), 'metric_reading'],
        ];
        $deleteOld = $this->pdo->prepare('DELETE FROM zimrx_sync_changes WHERE entity_type = :type AND entity_sync_id = :sync_id');
        foreach ($entities as [$table, $ids, $type]) {
            if (!\DbSchema::tableExists($this->pdo, $table) || !\DbSchema::columnExists($this->pdo, $table, 'sync_id')) {
                continue;
            }
            $select = $this->pdo->prepare("SELECT sync_id, revision FROM {$table} WHERE id = :id");
            foreach ($ids as $id) {
                $select->execute(['id' => $id]);
                $row = $select->fetch(PDO::FETCH_ASSOC);
                if (!is_array($row) || trim((string)($row['sync_id'] ?? '')) === '') {
                    continue;
                }
                $syncId = (string)$row['sync_id'];
                $revision = max(1, (int)($row['revision'] ?? 1));
                $deleteOld->execute(['type' => $type, 'sync_id' => $syncId]);
                SyncJournalService::logChange($this->pdo, $type, $syncId, 'delete', $revision, $revision + 1, []);
            }
        }
    }

    private function deletePatientChildren(int $profileId, int $doctorId): void
    {
        $patientTables = [
            'zimrx_prescription_drugs',
            'zimrx_prescription_clinical_findings',
            'zimrx_ai_analyzer_history',
            'zimrx_sms_history',
            'zimrx_patients_change_log',
            'zimrx_patient_particulars_audit',
            'zimrx_patient_metric_readings',
            'zimrx_user_patient_referrals',
            'zimrx_visit_vitals',
            'zimrx_payments',
            'zimrx_appointments',
            'zimrx_visits',
            'zimrx_visit_revisions',
        ];
        foreach ($patientTables as $table) {
            if (!\DbSchema::tableExists($this->pdo, $table)) {
                continue;
            }
            $clauses = [];
            if (\DbSchema::columnExists($this->pdo, $table, 'patient_id')) {
                $clauses[] = "patient_id IN (SELECT id FROM zimrx_patients WHERE profile_id = :patient_profile_{$table})";
            }
            if (in_array($table, ['zimrx_prescription_drugs', 'zimrx_prescription_clinical_findings', 'zimrx_ai_analyzer_history'], true) && \DbSchema::columnExists($this->pdo, $table, 'visit_id')) {
                $clauses[] = "visit_id IN (SELECT id FROM zimrx_visits WHERE profile_id = :visit_profile_{$table})";
            }
            if (\DbSchema::columnExists($this->pdo, $table, 'visit_record_id')) {
                $clauses[] = "visit_record_id IN (SELECT id FROM zimrx_visits WHERE profile_id = :record_profile_{$table})";
            }
            if (\DbSchema::columnExists($this->pdo, $table, 'profile_id')) {
                $clauses[] = "profile_id = :row_profile_{$table}";
            }
            if ($table === 'zimrx_user_patient_referrals') {
                $clauses[] = "doctor_id = :doctor_id AND visit_record_id IN (SELECT id FROM zimrx_visits WHERE profile_id = :referral_profile)";
            }
            if ($clauses === []) {
                continue;
            }
            $params = [];
            foreach ($clauses as $clause) {
                if (preg_match('/:(patient_profile_|visit_profile_|record_profile_|row_profile_)([a-z0-9_]+)/', $clause, $m)) {
                    $params[$m[1] . $m[2]] = $profileId;
                }
            }
            if (str_contains(implode(' ', $clauses), ':doctor_id')) {
                $params['doctor_id'] = $doctorId;
                $params['referral_profile'] = $profileId;
            }
            $stmt = $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE (' . implode(' OR ', $clauses) . ')');
            $stmt->execute($params);
        }
        if (\DbSchema::tableExists($this->pdo, 'zimrx_patient_doctor_access')) {
            $this->pdo->prepare('DELETE FROM zimrx_patient_doctor_access WHERE patient_id IN (SELECT id FROM zimrx_patients WHERE profile_id = :profile_id)')
                ->execute(['profile_id' => $profileId]);
        }
        if (\DbSchema::tableExists($this->pdo, 'zimrx_mobile_active_context')) {
            $this->pdo->prepare('DELETE FROM zimrx_mobile_active_context WHERE patient_id IN (SELECT id FROM zimrx_patients WHERE profile_id = :patient_profile_id) OR visit_record_id IN (SELECT id FROM zimrx_visits WHERE profile_id = :visit_profile_id)')
                ->execute(['patient_profile_id' => $profileId, 'visit_profile_id' => $profileId]);
        }
        if (\DbSchema::tableExists($this->pdo, 'zimrx_mobile_upload_queue')) {
            $this->pdo->prepare('DELETE FROM zimrx_mobile_upload_queue WHERE patient_id IN (SELECT id FROM zimrx_patients WHERE profile_id = :patient_profile_id) OR visit_record_id IN (SELECT id FROM zimrx_visits WHERE profile_id = :visit_profile_id)')
                ->execute(['patient_profile_id' => $profileId, 'visit_profile_id' => $profileId]);
        }
    }

    private function removeStoredFiles(array $paths, int $deletedProfileId): void
    {
        $uploadsRoot = realpath(\ZIMRX_USERDATA_DIR . '/uploads');
        if ($uploadsRoot === false) {
            return;
        }
        $userdataRoot = realpath(\ZIMRX_USERDATA_DIR);
        if ($userdataRoot === false) {
            return;
        }
        foreach ($paths as $path) {
            $candidate = null;
            if (str_starts_with($path, 'api/view_report.php?file=')) {
                parse_str((string)(parse_url($path, PHP_URL_QUERY) ?? ''), $query);
                $filename = basename((string)($query['file'] ?? ''));
                if (preg_match('/^report-[0-9]+-[0-9]+-[a-f0-9]+\\.(jpg|jpeg|png|gif|webp|pdf)$/i', $filename)) {
                    $candidate = \ZIMRX_USERDATA_DIR . '/uploads/reports/' . $filename;
                }
            } elseif (preg_match('#^/?uploads/[a-zA-Z0-9_./-]+$#', $path)
                && preg_match('#(?:^|/)\.{1,2}(?:/|$)#', ltrim($path, '/')) !== 1) {
                $candidate = \ZIMRX_USERDATA_DIR . '/' . ltrim($path, '/');
            }
            if ($candidate === null) {
                continue;
            }
            $real = realpath($candidate);
            if ($real === false || !str_starts_with($real, $uploadsRoot . DIRECTORY_SEPARATOR) || !is_file($real)) {
                continue;
            }
            $url = 'api/view_report.php?file=' . rawurlencode(basename($real));
            $relativePath = substr($real, strlen($userdataRoot) + 1);
            $publicPath = '/' . str_replace('\\', '/', $relativePath);
            $profileReference = $this->pdo->prepare(
                'SELECT COUNT(*) FROM zimrx_doctor_profiles
                 WHERE id <> :profile_id AND is_deleted = 0 AND settings_json LIKE :path'
            );
            $profileReference->execute(['profile_id' => $deletedProfileId, 'path' => '%' . $publicPath . '%']);
            if ((int)$profileReference->fetchColumn() > 0) {
                continue;
            }
            $referenced = $this->pdo->prepare(
                'SELECT COUNT(*) FROM zimrx_visits
                 WHERE profile_id <> :profile_id
                   AND (clinical_snapshot_json LIKE :snapshot_needle OR prescription_html LIKE :prescription_needle OR rich_text_json LIKE :rich_text_needle)'
            );
            $needle = '%' . $url . '%';
            $referenced->execute([
                'profile_id' => $deletedProfileId,
                'snapshot_needle' => $needle,
                'prescription_needle' => $needle,
                'rich_text_needle' => $needle,
            ]);
            if ((int)$referenced->fetchColumn() > 0) {
                continue;
            }
            if (\DbSchema::tableExists($this->pdo, 'zimrx_mobile_upload_queue')) {
                $queued = $this->pdo->prepare('SELECT COUNT(*) FROM zimrx_mobile_upload_queue WHERE file_path = :path');
                $queued->execute(['path' => $url]);
                if ((int)$queued->fetchColumn() > 0) {
                    continue;
                }
            }
            if (!@unlink($real)) {
                error_log('[ZimRx] Could not remove purged profile upload: ' . $real);
            }
        }
    }

    private function clearActiveSession(int $profileId): void
    {
        if ((int)($_SESSION['active_practice_profile_id'] ?? 0) === $profileId) {
            unset($_SESSION['active_practice_profile_id'], $_SESSION['active_profile_doctor_id']);
        }
    }
}
