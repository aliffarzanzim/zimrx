<?php
declare(strict_types=1);

use ZimRx\Services\SyncJournalService;

require_once __DIR__ . '/../lib/db/db_connections.php';
require_once __DIR__ . '/../lib/db/db_schema.php';
require_once __DIR__ . '/../lib/db/db_sql.php';
require_once __DIR__ . '/../lib/db/db_migrator.php';
require_once __DIR__ . '/../lib/Services/SyncJournalService.php';

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON');
(new DbMigrator())->run($pdo);

$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO zimrx_patients (full_name, doctor_id) VALUES (:name, :doctor)')
        ->execute(['name' => 'Journal Test Patient', 'doctor' => 1]);
    $patientId = (int)$pdo->lastInsertId();
    SyncJournalService::recordPatientChange($pdo, $patientId, 'insert', ['full_name' => 'Journal Test Patient']);
    SyncJournalService::recordPatientChange($pdo, $patientId, 'update', ['full_name' => 'Journal Test Patient']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
$patient = $pdo->query('SELECT sync_id, revision FROM zimrx_patients')->fetch();
$patientEvents = $pdo->query("SELECT * FROM zimrx_sync_changes WHERE entity_type = 'patient' ORDER BY sequence")->fetchAll();
$check(count($patientEvents) === 2, 'Patient insert and update must both be journaled.');
$check($patient['sync_id'] !== '' && $patientEvents[0]['entity_sync_id'] === $patient['sync_id']
    && $patientEvents[1]['entity_sync_id'] === $patient['sync_id'], 'Patient events must retain the stored sync identity.');
$check((int)$patient['revision'] === 2 && (int)$patientEvents[1]['base_revision'] === 1
    && (int)$patientEvents[1]['new_revision'] === 2, 'Patient update must advance its stored and journal revisions.');

$saveMetric = static function (string $metric, string $value) use ($pdo, $patientId): int {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO zimrx_patient_metric_readings
            (patient_id, metric_type, reading_value, reading_date)
            VALUES (:patient, :metric, :value, :date)');
        $stmt->execute(['patient' => $patientId, 'metric' => $metric, 'value' => $value, 'date' => '2026-10-31']);
        $readingId = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare('UPDATE zimrx_patients SET tracked_metrics_json = :tracked WHERE id = :patient');
        $stmt->execute(['tracked' => json_encode(['weight', $metric]), 'patient' => $patientId]);
        SyncJournalService::recordMetricReadingChange($pdo, $readingId, $patientId, 'insert', [
            'metric_type' => $metric, 'reading_value' => $value,
        ]);
        $pdo->commit();
        return $readingId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
};
$readingId = $saveMetric('pulse', '72');
$reading = $pdo->query('SELECT * FROM zimrx_patient_metric_readings')->fetch();
$event = $pdo->query("SELECT * FROM zimrx_sync_changes WHERE entity_type = 'metric_reading'")->fetch();
$check($reading['reading_value'] === '72' && $reading['sync_id'] !== ''
    && $event['entity_sync_id'] === $reading['sync_id'], 'Metric reading and matching journal identity must persist together.');
$check((int)$event['base_revision'] === 0 && (int)$event['new_revision'] === 1,
    'Metric insert must journal revision 0 to 1.');
$payload = json_decode($event['payload_json'], true, 512, JSON_THROW_ON_ERROR);
$check($payload['id'] === $readingId && $payload['patient_id'] === $patientId
    && $payload['reading_value'] === '72', 'Metric journal must preserve the reading identity and payload.');

$beforePatient = $pdo->query('SELECT * FROM zimrx_patients')->fetchAll();
$beforeReadings = $pdo->query('SELECT * FROM zimrx_patient_metric_readings ORDER BY id')->fetchAll();
$beforeJournal = $pdo->query('SELECT * FROM zimrx_sync_changes ORDER BY sequence')->fetchAll();
$pdo->exec("CREATE TRIGGER reject_metric_journal BEFORE INSERT ON zimrx_sync_changes
    WHEN NEW.entity_type = 'metric_reading' BEGIN SELECT RAISE(ABORT, 'journal rejected'); END");
$rejected = false;
try {
    $saveMetric('height', '170');
} catch (PDOException $e) {
    $rejected = str_contains($e->getMessage(), 'journal rejected');
}
$check($rejected && !$pdo->inTransaction(), 'A rejected journal insert must end the failed save transaction.');
$check($pdo->query('SELECT * FROM zimrx_patients')->fetchAll() === $beforePatient
    && $pdo->query('SELECT * FROM zimrx_patient_metric_readings ORDER BY id')->fetchAll() === $beforeReadings
    && $pdo->query('SELECT * FROM zimrx_sync_changes ORDER BY sequence')->fetchAll() === $beforeJournal,
    'Journal failure must roll back the reading and tracked-metric changes without disturbing saved records.');

$missingSchema = new PDO('sqlite::memory:');
$schemaRejected = false;
try {
    SyncJournalService::recordMetricReadingChange($missingSchema, 1, 1, 'insert', []);
} catch (RuntimeException $e) {
    $schemaRejected = str_contains($e->getMessage(), 'zimrx_sync_changes table is missing');
}
$check($schemaRejected, 'Missing journal schema must raise the intended RuntimeException.');

echo "Sync journal record checks passed: {$checks}.\n";
