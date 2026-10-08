<?php
declare(strict_types=1);

namespace ZimRx\Services;

use PDO;
use InvalidArgumentException;

// Append-only delta log for entity mutations (patients, vitals, visits) to synchronize with companion apps.
class SyncJournalService
{
    // Generate standard RFC 4122 v4 UUID
    public static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // Write a mutation event to the sync changes table
    public static function logChange(
        PDO $pdo,
        string $entityType,
        string $entitySyncId,
        string $operation,
        int $baseRevision,
        int $newRevision,
        array $payload,
        string $deviceId = 'desktop'
    ): void {
        if (!in_array($operation, ['insert', 'update', 'delete'], true)) {
            throw new InvalidArgumentException("Invalid sync operation: {$operation}");
        }

        $stmt = $pdo->prepare(
            "INSERT INTO zimrx_sync_changes (
                entity_type, entity_sync_id, operation,
                base_revision, new_revision, payload_json, device_id, created_at
            ) VALUES (
                :entity_type, :entity_sync_id, :operation,
                :base_revision, :new_revision, :payload_json, :device_id, CURRENT_TIMESTAMP
            )"
        );
        $stmt->execute([
            'entity_type'    => $entityType,
            'entity_sync_id' => $entitySyncId,
            'operation'      => $operation,
            'base_revision'  => $baseRevision,
            'new_revision'   => $newRevision,
            'payload_json'   => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'device_id'      => $deviceId,
        ]);
    }

    // Journal patient record changes and bump the optimistic revision counter
    public static function recordPatientChange(
        PDO $pdo,
        int $patientId,
        string $operation,
        array $payload,
        string $deviceId = 'desktop'
    ): void {
        if (!DbSchema::tableExists($pdo, 'zimrx_patients') || !DbSchema::tableExists($pdo, 'zimrx_sync_changes')) {
            return;
        }

        $stmt = $pdo->prepare("SELECT sync_id, revision FROM zimrx_patients WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }

        $syncId = (string)($row['sync_id'] ?? '');
        if ($syncId === '') {
            $syncId = self::generateUuid();
            $pdo->prepare("UPDATE zimrx_patients SET sync_id = :sync_id WHERE id = :id")->execute(['sync_id' => $syncId, 'id' => $patientId]);
        }

        $baseRevision = (int)($row['revision'] ?? 1);
        $newRevision = $operation === 'insert' ? max(1, $baseRevision) : ($baseRevision + 1);

        if ($operation !== 'insert') {
            $pdo->prepare("UPDATE zimrx_patients SET revision = :rev, updated_at = CURRENT_TIMESTAMP WHERE id = :id")
                ->execute(['rev' => $newRevision, 'id' => $patientId]);
        }

        self::logChange($pdo, 'patient', $syncId, $operation, $baseRevision, $newRevision, $payload, $deviceId);
    }

    // Journal vital sign reading mutations (inserts record base 0 -> 1; updates increment revision)
    public static function recordMetricReadingChange(
        PDO $pdo,
        int $readingId,
        int $patientId,
        string $operation,
        array $payload,
        string $deviceId = 'desktop'
    ): void {
        if (!DbSchema::tableExists($pdo, 'zimrx_sync_changes')) {
            throw new RuntimeException(
                'zimrx_sync_changes table is missing. ' .
                'Run database migrations before recording metric sync events.'
            );
        }

        // Migration 013 is mandatory; fail clearly if columns are missing.
        if (!DbSchema::columnExists($pdo, 'zimrx_patient_metric_readings', 'sync_id') ||
            !DbSchema::columnExists($pdo, 'zimrx_patient_metric_readings', 'revision')) {
            throw new RuntimeException(
                'zimrx_patient_metric_readings is missing sync_id/revision columns. ' .
                'Run database migrations before recording metric sync events.'
            );
        }

        // Read the row's current persistent sync_id and stored revision.
        $stmt = $pdo->prepare(
            'SELECT sync_id, revision FROM zimrx_patient_metric_readings WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $readingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Stable sync_id: assign once and persist so all journal entries share the same identity.
        $syncId = ($row && (string)($row['sync_id'] ?? '') !== '') ? (string)$row['sync_id'] : null;
        if ($syncId === null) {
            $syncId = self::generateUuid();
            $pdo->prepare('UPDATE zimrx_patient_metric_readings SET sync_id = :sid WHERE id = :id')
                ->execute(['sid' => $syncId, 'id' => $readingId]);
        }

        // Insert always journals 0 → 1; the stored DB default (1) is correct and untouched.
        // Update/delete read the live stored revision as their base.
        if ($operation === 'insert') {
            $baseRevision = 0;
            $newRevision  = 1;
        } else {
            $baseRevision = (int)($row['revision'] ?? 1);
            $newRevision  = $baseRevision + 1;
            // Advance the stored revision so future callers see the correct base.
            $pdo->prepare('UPDATE zimrx_patient_metric_readings SET revision = :rev WHERE id = :id')
                ->execute(['rev' => $newRevision, 'id' => $readingId]);
        }

        self::logChange(
            $pdo,
            'metric_reading',
            $syncId,
            $operation,
            $baseRevision,
            $newRevision,
            array_merge(['id' => $readingId, 'patient_id' => $patientId], $payload),
            $deviceId
        );
    }
}

if (!class_exists('SyncJournalService', false)) {
    class_alias(SyncJournalService::class, 'SyncJournalService');
}

