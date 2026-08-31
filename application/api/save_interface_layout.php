<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

try {
    $doctorId = current_user_doctor_id();

    // Ensure table exists
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_interface_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL,
            setting_scope TEXT NOT NULL DEFAULT 'global',
            setting_key TEXT NOT NULL,
            setting_value TEXT,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(doctor_id, setting_scope, setting_key)
        )"
    );

    $keys = ['left_layout', 'right_layout', 'history_layout', 'dropdown_theme', 'dropdown_hover_bg', 'dropdown_hover_text'];
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        DbSql::insertIgnore(
            'zimrx_interface_settings',
            'doctor_id, setting_scope, setting_key, setting_value',
            ':doctor_id, \'dashboard\', :setting_key, :setting_value'
        )
    );

    $updateStmt = $pdo->prepare(
        "UPDATE zimrx_interface_settings 
         SET setting_value = :setting_value, updated_at = CURRENT_TIMESTAMP 
         WHERE doctor_id = :doctor_id AND setting_scope = 'dashboard' AND setting_key = :setting_key"
    );

    foreach ($keys as $key) {
        if (isset($payload[$key])) {
            $valJson = is_array($payload[$key]) ? json_encode($payload[$key], JSON_UNESCAPED_UNICODE) : (string)$payload[$key];
            
            // Try updating first
            $updateStmt->execute([
                'doctor_id' => $doctorId,
                'setting_key' => $key,
                'setting_value' => $valJson
            ]);
            
            // If nothing was updated, insert it
            if ($updateStmt->rowCount() === 0) {
                $stmt->execute([
                    'doctor_id' => $doctorId,
                    'setting_key' => $key,
                    'setting_value' => $valJson
                ]);
            }
        }
    }

    // Save customized table column widths
    if (isset($payload['table_columns']) && is_array($payload['table_columns'])) {
        $tblInsert = $pdo->prepare(
            DbSql::insertIgnore(
                'zimrx_interface_settings',
                'doctor_id, setting_scope, setting_key, setting_value',
                ':doctor_id, \'table_columns\', :setting_key, :setting_value'
            )
        );
        $tblUpdate = $pdo->prepare(
            "UPDATE zimrx_interface_settings 
             SET setting_value = :setting_value, updated_at = CURRENT_TIMESTAMP 
             WHERE doctor_id = :doctor_id AND setting_scope = 'table_columns' AND setting_key = :setting_key"
        );
        foreach ($payload['table_columns'] as $tblKey => $tblWidths) {
            $tblVal = is_array($tblWidths) ? json_encode($tblWidths) : (string)$tblWidths;
            $tblUpdate->execute(['doctor_id' => $doctorId, 'setting_key' => (string)$tblKey, 'setting_value' => $tblVal]);
            if ($tblUpdate->rowCount() === 0) {
                $tblInsert->execute(['doctor_id' => $doctorId, 'setting_key' => (string)$tblKey, 'setting_value' => $tblVal]);
            }
        }
    }

    // Reset customized table column widths
    if (isset($payload['reset_table_column'])) {
        $delStmt = $pdo->prepare(
            "DELETE FROM zimrx_interface_settings 
             WHERE doctor_id = :doctor_id AND setting_scope = 'table_columns' AND setting_key = :setting_key"
        );
        $delStmt->execute(['doctor_id' => $doctorId, 'setting_key' => (string)$payload['reset_table_column']]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['error' => $e->getMessage()]);
}
