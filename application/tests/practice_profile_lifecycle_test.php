<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db/db_connections.php';
require_once __DIR__ . '/../lib/db/db_schema.php';
require_once __DIR__ . '/../lib/db/db_sql.php';

define('ZIMRX_USERDATA_DIR', __DIR__ . '/scratch/profile_userdata');

function current_user_role(): string { return 'doctor'; }
function current_user_id(): int { return 7; }
function current_user_doctor_id(): int { return 1; }
function current_user_name(): string { return 'Test Doctor'; }
function zimrx_doctor_options_for_user(PDO $pdo, int $userId, string $role, int $doctorId): array
{
    return [['id' => 1, 'display_name' => 'Test Doctor']];
}

require_once __DIR__ . '/../lib/Services/PracticeProfileService.php';
require_once __DIR__ . '/../lib/Services/PracticeProfileLifecycleService.php';

function profile_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function profile_test_db(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE zimrx_doctors (id INTEGER PRIMARY KEY, display_name TEXT, is_active INTEGER)');
    $pdo->exec("INSERT INTO zimrx_doctors VALUES (1, 'Test Doctor', 1)");
    $pdo->exec("CREATE TABLE zimrx_doctor_profiles (
        id INTEGER PRIMARY KEY, profile_uuid TEXT, doctor_id INTEGER, profile_name TEXT, address TEXT, phone TEXT,
        prescription_footer_note TEXT, is_active INTEGER, is_default INTEGER, share_patient_particulars INTEGER,
        share_emr INTEGER, share_prescriptions INTEGER, settings_json TEXT, created_at TEXT, updated_at TEXT,
        archived_at TEXT, is_deleted INTEGER DEFAULT 0, deleted_at TEXT
    )");
    $pdo->exec("INSERT INTO zimrx_doctor_profiles
        (id, profile_uuid, doctor_id, profile_name, address, phone, prescription_footer_note, is_active, is_default,
         share_patient_particulars, share_emr, share_prescriptions, settings_json, is_deleted)
        VALUES (1, 'p1', 1, 'Practice One', '', '', '', 1, 1, 0, 0, 0, '{}', 0),
               (2, 'p2', 1, 'Practice Two', '', '', '', 1, 0, 0, 0, 0, '{}', 0)");
    $pdo->exec('CREATE TABLE zimrx_patients (id INTEGER PRIMARY KEY, profile_id INTEGER, sync_id TEXT, revision INTEGER DEFAULT 1)');
    $pdo->exec('CREATE TABLE zimrx_visits (id INTEGER PRIMARY KEY, patient_id INTEGER, profile_id INTEGER, sync_id TEXT, revision INTEGER DEFAULT 1, clinical_snapshot_json TEXT, prescription_html TEXT, rich_text_json TEXT)');
    $pdo->exec('CREATE TABLE zimrx_patient_metric_readings (id INTEGER PRIMARY KEY, patient_id INTEGER, profile_id INTEGER, sync_id TEXT, revision INTEGER DEFAULT 1)');
    $pdo->exec('CREATE TABLE zimrx_appointments (id INTEGER PRIMARY KEY, patient_id INTEGER, profile_id INTEGER)');
    $pdo->exec('CREATE TABLE zimrx_payments (id INTEGER PRIMARY KEY, patient_id INTEGER, profile_id INTEGER)');
    $pdo->exec('CREATE TABLE zimrx_visit_vitals (id INTEGER PRIMARY KEY, patient_id INTEGER, profile_id INTEGER)');
    $pdo->exec('CREATE TABLE zimrx_visit_revisions (id INTEGER PRIMARY KEY, patient_id INTEGER, visit_record_id INTEGER, profile_id INTEGER)');
    $pdo->exec('CREATE TABLE zimrx_patients_change_log (id INTEGER PRIMARY KEY, patient_id INTEGER, profile_id INTEGER)');
    $pdo->exec('CREATE TABLE zimrx_patient_particulars_audit (id INTEGER PRIMARY KEY, patient_id INTEGER, profile_id INTEGER)');
    $pdo->exec('CREATE TABLE zimrx_patient_sequences (id INTEGER PRIMARY KEY, profile_id INTEGER)');
    $pdo->exec('CREATE TABLE zimrx_prescription_drugs (id INTEGER PRIMARY KEY, patient_id INTEGER, visit_id INTEGER)');
    $pdo->exec('CREATE TABLE zimrx_prescription_clinical_findings (id INTEGER PRIMARY KEY, visit_id INTEGER)');
    $pdo->exec('CREATE TABLE zimrx_ai_analyzer_history (id INTEGER PRIMARY KEY, patient_id INTEGER, visit_id INTEGER)');
    $pdo->exec('CREATE TABLE zimrx_sms_history (id INTEGER PRIMARY KEY, patient_id INTEGER)');
    $pdo->exec('CREATE TABLE zimrx_user_patient_referrals (id INTEGER PRIMARY KEY, doctor_id INTEGER, visit_record_id INTEGER)');
    $pdo->exec('CREATE TABLE zimrx_patient_doctor_access (id INTEGER PRIMARY KEY, patient_id INTEGER, doctor_id INTEGER)');
    $pdo->exec('CREATE TABLE zimrx_mobile_upload_queue (id TEXT PRIMARY KEY, patient_id INTEGER, visit_record_id INTEGER, file_path TEXT)');
    $pdo->exec('CREATE TABLE zimrx_mobile_active_context (doctor_id INTEGER PRIMARY KEY, patient_id INTEGER, visit_record_id INTEGER)');
    $pdo->exec("CREATE TABLE zimrx_sync_changes (
        sequence INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_sync_id TEXT, operation TEXT,
        base_revision INTEGER, new_revision INTEGER, payload_json TEXT, device_id TEXT, created_at TEXT
    )");
    return $pdo;
}

$pdo = profile_test_db();
$pdo->exec("INSERT INTO zimrx_patients VALUES (10, 1, 'patient-sync', 2), (20, 2, 'other-sync', 1)");
$pdo->exec("INSERT INTO zimrx_visits VALUES (100, 10, 1, 'visit-sync', 4, '{}', '', '{}'), (200, 20, 2, 'other-visit', 1, '{}', '', '{}')");
$pdo->exec('INSERT INTO zimrx_prescription_drugs (id, patient_id, visit_id) VALUES (1, 10, 100)');
$pdo->exec('INSERT INTO zimrx_prescription_clinical_findings (id, visit_id) VALUES (1, 100)');
$pdo->exec('INSERT INTO zimrx_ai_analyzer_history (id, patient_id, visit_id) VALUES (1, 10, 100)');
$pdo->exec("INSERT INTO zimrx_sync_changes (entity_type, entity_sync_id, operation, base_revision, new_revision, payload_json, device_id, created_at)
    VALUES ('patient', 'patient-sync', 'update', 1, 2, '{\\\"full_name\\\":\\\"Test\\\"}', 'desktop', CURRENT_TIMESTAMP)");

$lifecycle = new \ZimRx\Services\PracticeProfileLifecycleService($pdo);
$lifecycle->deleteProfileAndPatientData(1, 1, 'DELETE Practice One');
profile_test_assert((int)$pdo->query('SELECT COUNT(*) FROM zimrx_doctor_profiles WHERE id = 1')->fetchColumn() === 0, 'purge removes profile row');
profile_test_assert((int)$pdo->query('SELECT COUNT(*) FROM zimrx_patients WHERE id = 10')->fetchColumn() === 0, 'purge removes owned patient');
profile_test_assert((int)$pdo->query('SELECT COUNT(*) FROM zimrx_patients WHERE id = 20')->fetchColumn() === 1, 'purge leaves other profile patient intact');
profile_test_assert((int)$pdo->query('SELECT COUNT(*) FROM zimrx_prescription_drugs')->fetchColumn() === 0, 'purge removes patient prescription rows');
profile_test_assert((int)$pdo->query("SELECT COUNT(*) FROM zimrx_sync_changes WHERE operation = 'delete' AND entity_sync_id = 'patient-sync' AND payload_json = '[]'")->fetchColumn() === 1, 'purge replaces PHI sync history with minimal tombstone');

$pdo = profile_test_db();
$confirmationRejected = false;
try {
    (new \ZimRx\Services\PracticeProfileLifecycleService($pdo))->deleteProfileAndPatientData(1, 1, 'DELETE Wrong Name');
} catch (RuntimeException $error) {
    $confirmationRejected = str_contains($error->getMessage(), 'confirmation text does not match');
}
profile_test_assert($confirmationRejected, 'purge requires server-verified profile-name confirmation');
profile_test_assert((int)$pdo->query('SELECT COUNT(*) FROM zimrx_doctor_profiles')->fetchColumn() === 2, 'invalid confirmation leaves profiles unchanged');

$pdo = profile_test_db();
$pdo->exec('INSERT INTO zimrx_patients (id, profile_id) VALUES (10, 1)');
$pdo->exec('INSERT INTO zimrx_appointments (id, patient_id, profile_id) VALUES (1, 10, 2)');
try {
    (new \ZimRx\Services\PracticeProfileLifecycleService($pdo))->deleteProfileAndPatientData(1, 1, 'DELETE Practice One');
    throw new RuntimeException('cross-profile reference should have blocked purge');
} catch (RuntimeException $error) {
    profile_test_assert(str_contains($error->getMessage(), 'referenced by another profile'), 'cross-profile reference blocks purge');
}
profile_test_assert((int)$pdo->query('SELECT COUNT(*) FROM zimrx_patients WHERE id = 10')->fetchColumn() === 1, 'blocked purge rolls back without deleting patient');

$pdo = profile_test_db();
$pdo->exec('INSERT INTO zimrx_patients (id, profile_id) VALUES (10, 1)');
(new \ZimRx\Services\PracticeProfileLifecycleService($pdo))->removeProfile(1, 1);
profile_test_assert((int)$pdo->query('SELECT COUNT(*) FROM zimrx_patients WHERE id = 10')->fetchColumn() === 1, 'remove profile keeps patient records');
profile_test_assert((int)$pdo->query('SELECT is_deleted FROM zimrx_doctor_profiles WHERE id = 1')->fetchColumn() === 1, 'remove profile hides profile');
profile_test_assert((int)$pdo->query('SELECT is_default FROM zimrx_doctor_profiles WHERE id = 2')->fetchColumn() === 1, 'removing the default promotes another profile');

$pdo = profile_test_db();
$profileService = new \ZimRx\Services\PracticeProfileService($pdo);
$profileService->archive(1, 1);
profile_test_assert((int)$pdo->query('SELECT is_active FROM zimrx_doctor_profiles WHERE id = 1')->fetchColumn() === 0, 'deactivation archives profile without deleting it');
profile_test_assert((int)$pdo->query('SELECT COUNT(*) FROM zimrx_doctor_profiles WHERE id = 1')->fetchColumn() === 1, 'deactivation preserves profile record');
$profileService->reactivate(1, 1);
profile_test_assert((int)$pdo->query('SELECT is_active FROM zimrx_doctor_profiles WHERE id = 1')->fetchColumn() === 1, 'archived profile can be reactivated');
profile_test_assert((int)$pdo->query('SELECT is_deleted FROM zimrx_doctor_profiles WHERE id = 1')->fetchColumn() === 0, 'reactivation does not alter deletion state');

$pdo = profile_test_db();
$profileService = new \ZimRx\Services\PracticeProfileService($pdo);
$_SESSION['active_practice_profile_id'] = 2;
$_SESSION['active_profile_doctor_id'] = 1;
$activeProfile = $profileService->activeForCurrentUser();
profile_test_assert((int)($activeProfile['id'] ?? 0) === 2, 'current practice selection resolves to the selected profile');
$activatedProfile = $profileService->activate(1);
profile_test_assert((int)$activatedProfile['id'] === 1 && (int)$_SESSION['active_practice_profile_id'] === 1, 'practice switching updates the session to an accessible profile');
unset($_SESSION['active_practice_profile_id'], $_SESSION['active_profile_doctor_id']);

echo "Practice profile lifecycle checks passed.";
