<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensure_patient_particulars_audit_schema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_patient_particulars_audit (
            id " . DbSql::autoIncrement() . ",
            patient_id INTEGER NOT NULL,
            patient_reg_no TEXT,
            action_source TEXT NOT NULL,
            changed_by_user_id INTEGER,
            changed_by_role TEXT,
            changed_by_name TEXT,
            changes_json TEXT NOT NULL,
            summary_text TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_patient ON zimrx_patient_particulars_audit(patient_id, created_at)");
}

function log_patient_particulars_audit(
    PDO $pdo,
    int $patientId,
    string $regNo,
    ?array $oldData,
    array $newData,
    string $source = 'appointment'
): void {
    if ($patientId <= 0) return;
    ensure_patient_particulars_audit_schema($pdo);

    $fieldsToCheck = [
        'full_name' => 'Name',
        'mobile' => 'Mobile',
        'age' => 'Age',
        'age_unit' => 'Age Unit',
        'dob' => 'Date of Birth',
        'gender' => 'Sex',
        'blood_group' => 'Blood Group',
        'occupation' => 'Occupation',
        'address' => 'Address',
        'weight' => 'Weight',
        'weight_unit' => 'Weight Unit',
        'height' => 'Height',
        'height_unit' => 'Height Unit',
        'referral_category' => 'Referred By Category',
        'referral_name' => 'Referred By Name'
    ];

    $changes = [];
    $summaryParts = [];

    if (empty($oldData)) {
        $changes['initial'] = [
            'label' => 'Initial Registration',
            'old' => null,
            'new' => 'Patient registered'
        ];
        $summaryParts[] = 'Initial Registration';
    } else {
        foreach ($fieldsToCheck as $key => $label) {
            if (!array_key_exists($key, $newData)) continue;

            $oldVal = trim((string)($oldData[$key] ?? ''));
            $newVal = trim((string)($newData[$key] ?? ''));

            if ($oldVal === '' && $newVal === '') continue;

            if ($oldVal !== $newVal) {
                $changes[$key] = [
                    'label' => $label,
                    'old' => $oldVal !== '' ? $oldVal : '--',
                    'new' => $newVal !== '' ? $newVal : '--'
                ];
                $summaryParts[] = "$label changed";
            }
        }
    }

    if (empty($changes)) {
        return; // No differences detected
    }

    $userId = function_exists('current_user_id') ? current_user_id() : (int)($_SESSION['user_id'] ?? 1);
    $userRole = function_exists('current_user_role') ? current_user_role() : (string)($_SESSION['user_role'] ?? 'staff');

    $userName = 'User #' . $userId;
    if (!empty($_SESSION['user_name'])) {
        $userName = (string)$_SESSION['user_name'];
    } elseif (!empty($_SESSION['doctor_name'])) {
        $userName = (string)$_SESSION['doctor_name'];
    } else {
        try {
            $uStmt = $pdo->prepare("SELECT name, username FROM zimrx_users WHERE id = :id LIMIT 1");
            $uStmt->execute(['id' => $userId]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow) $userName = $uRow['name'] ?: $uRow['username'] ?: $userName;
        } catch (Throwable $e) {}
    }

    $summaryText = implode(', ', $summaryParts);
    $stmt = $pdo->prepare(
        "INSERT INTO zimrx_patient_particulars_audit (
            patient_id, patient_reg_no, action_source, changed_by_user_id, changed_by_role,
            changed_by_name, changes_json, summary_text, created_at
        ) VALUES (
            :patient_id, :patient_reg_no, :action_source, :changed_by_user_id, :changed_by_role,
            :changed_by_name, :changes_json, :summary_text, CURRENT_TIMESTAMP
        )"
    );
    $stmt->execute([
        'patient_id' => $patientId,
        'patient_reg_no' => $regNo,
        'action_source' => $source,
        'changed_by_user_id' => $userId,
        'changed_by_role' => $userRole,
        'changed_by_name' => $userName,
        'changes_json' => json_encode($changes, JSON_UNESCAPED_UNICODE),
        'summary_text' => $summaryText
    ]);
}

function get_patient_particulars_audit_history(PDO $pdo, int $patientId): array {
    if ($patientId <= 0) return [];
    ensure_patient_particulars_audit_schema($pdo);

    $stmt = $pdo->prepare(
        "SELECT *
         FROM zimrx_patient_particulars_audit
         WHERE patient_id = :pid
         ORDER BY id DESC"
    );
    $stmt->execute(['pid' => $patientId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $history = [];
    foreach ($rows as $r) {
        $changes = json_decode((string)$r['changes_json'], true) ?: [];
        $history[] = [
            'id' => (int)$r['id'],
            'patient_id' => (int)$r['patient_id'],
            'patient_reg_no' => (string)$r['patient_reg_no'],
            'action_source' => (string)$r['action_source'],
            'changed_by_user_id' => (int)$r['changed_by_user_id'],
            'changed_by_role' => (string)$r['changed_by_role'],
            'changed_by_name' => (string)$r['changed_by_name'],
            'changes' => $changes,
            'summary_text' => (string)$r['summary_text'],
            'created_at' => (string)$r['created_at'],
            'created_at_formatted' => date('d M Y, h:i A', strtotime((string)$r['created_at']))
        ];
    }
    return $history;
}

function zimrx_record_user_occupation(PDO $pdo, int $doctorId, string $occupation): void {
    $occupation = trim($occupation);
    if (strlen($occupation) < 2) return;
    $doctorId = max(1, $doctorId);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO zimrx_user_occupations (
                doctor_id, name, usage_count, is_pinned, is_hidden, sort_order, created_at, updated_at
            ) VALUES (
                :doctor_id, :name, 1, 0, 0, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
            ON CONFLICT(doctor_id, name) DO UPDATE SET
                usage_count = zimrx_user_occupations.usage_count + 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            'doctor_id' => $doctorId,
            'name' => $occupation,
        ]);
    } catch (Exception $e) {
        // Silently ignore if table doesn't exist or concurrent write
    }
}
