<?php
// Persists doctor UI customization settings (column widths, module layouts, and themes).
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!zimrx_verify_csrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF verification failed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

try {
    $doctorId = current_user_doctor_id();
    if ($doctorId <= 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

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
    error_log('[ZimRx] save_interface_layout error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to save interface layout.']);
}
