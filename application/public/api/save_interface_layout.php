<?php
// Persists doctor UI customization settings (column widths, module layouts, and themes).
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/DashboardModules.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/PatientCustomFields.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/VisitFormModules.php';
require_login();

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

$allowedDropdownThemes = ['slate-gray', 'subtle-tint', 'charcoal', 'theme-blue', 'soft-blue', 'emerald', 'custom'];
if (array_key_exists('dropdown_theme', $payload)
    && (!is_string($payload['dropdown_theme']) || !in_array($payload['dropdown_theme'], $allowedDropdownThemes, true))) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid dropdown theme']);
    exit;
}
foreach (['dropdown_hover_bg', 'dropdown_hover_text'] as $colorKey) {
    if (array_key_exists($colorKey, $payload)
        && (!is_string($payload[$colorKey]) || preg_match('/\A#[0-9a-fA-F]{6}\z/', $payload[$colorKey]) !== 1)) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid dropdown theme color']);
        exit;
    }
}

try {
    $dashboardModules = new \ZimRx\Services\DashboardModules(
        ZIMRX_BASE_DIR . '/views/modules'
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Dashboard module configuration is unavailable']);
    exit;
}

foreach (['left_layout' => 'left', 'right_layout' => 'right'] as $layoutKey => $panel) {
    if (!array_key_exists($layoutKey, $payload)) {
        continue;
    }
    if (!is_array($payload[$layoutKey])) {
        http_response_code(422);
        echo json_encode(['error' => 'Dashboard panel layout must be an array']);
        exit;
    }
    $selectedModules = [];
    foreach ($payload[$layoutKey] as $moduleName) {
        if (!is_string($moduleName) || !$dashboardModules->supportsPanel($moduleName, $panel)) {
            http_response_code(422);
            echo json_encode(['error' => 'Dashboard module order contains an unavailable module']);
            exit;
        }
        $canonicalName = $dashboardModules->canonicalName($moduleName);
        if ($canonicalName === null || isset($selectedModules[$canonicalName])) {
            http_response_code(422);
            echo json_encode(['error' => 'A dashboard module can appear only once per panel']);
            exit;
        }
        $selectedModules[$canonicalName] = true;
    }
}

if (array_key_exists('particulars_fields', $payload)) {
    $allowedFields = [
        'marital_status', 'email', 'nid_no', 'passport_no', 'spouse_name',
        'father_name', 'mother_name', 'emergency_name', 'emergency_mobile',
        'emergency_relation', 'emergency_address', 'nationality', 'company_name',
        'company_address'
    ];
    if (!is_array($payload['particulars_fields'])) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid particulars field settings']);
        exit;
    }
    foreach ($payload['particulars_fields'] as $field => $placement) {
        if (!is_string($field) || !in_array($field, $allowedFields, true)
            || !is_string($placement) || !in_array($placement, ['main', 'more_info', 'hidden'], true)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid particulars field settings']);
            exit;
        }
    }
}

$customFieldDefinitions = null;
if (array_key_exists('particulars_custom_fields', $payload)) {
    try {
        $customFieldDefinitions = \ZimRx\Services\PatientCustomFields::normalizeDefinitions($payload['particulars_custom_fields']);
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

$visitFormDefinitions = null;
if (array_key_exists('visit_form_modules', $payload)) {
    try {
        $visitFormDefinitions = \ZimRx\Services\VisitFormModules::normalizeDefinitions($payload['visit_form_modules']);
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
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

    // Save patient particulars field placement.
    if (isset($payload['particulars_fields']) && is_array($payload['particulars_fields'])) {
        $partInsert = $pdo->prepare(
            DbSql::insertIgnore(
                'zimrx_interface_settings',
                'doctor_id, setting_scope, setting_key, setting_value',
                ':doctor_id, \'particulars_fields\', \'field_placement\', :setting_value'
            )
        );
        $partUpdate = $pdo->prepare(
            "UPDATE zimrx_interface_settings
             SET setting_value = :setting_value, updated_at = CURRENT_TIMESTAMP
             WHERE doctor_id = :doctor_id AND setting_scope = 'particulars_fields' AND setting_key = 'field_placement'"
        );
        $partVal = json_encode($payload['particulars_fields'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $partUpdate->execute(['doctor_id' => $doctorId, 'setting_value' => $partVal]);
        if ($partUpdate->rowCount() === 0) {
            $partInsert->execute(['doctor_id' => $doctorId, 'setting_value' => $partVal]);
        }
    }

    if ($customFieldDefinitions !== null) {
        $customInsert = $pdo->prepare(
            DbSql::insertIgnore(
                'zimrx_interface_settings',
                'doctor_id, setting_scope, setting_key, setting_value',
                ':doctor_id, \'particulars_custom_fields\', \'definitions\', :setting_value'
            )
        );
        $customUpdate = $pdo->prepare(
            "UPDATE zimrx_interface_settings
             SET setting_value = :setting_value, updated_at = CURRENT_TIMESTAMP
             WHERE doctor_id = :doctor_id
               AND setting_scope = 'particulars_custom_fields'
               AND setting_key = 'definitions'"
        );
        $customValue = json_encode($customFieldDefinitions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $customUpdate->execute(['doctor_id' => $doctorId, 'setting_value' => $customValue]);
        if ($customUpdate->rowCount() === 0) {
            $customInsert->execute(['doctor_id' => $doctorId, 'setting_value' => $customValue]);
        }
    }

    if ($visitFormDefinitions !== null) {
        $formValue = json_encode($visitFormDefinitions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $formUpsert = $pdo->prepare(
            "INSERT INTO zimrx_interface_settings
             (doctor_id, setting_scope, setting_key, setting_value)
             VALUES (:doctor_id, 'visit_form_modules', 'definitions', :setting_value) "
            . DbSql::upsert(
                'doctor_id, setting_scope, setting_key',
                ['setting_value', 'updated_at'],
                ['updated_at' => 'CURRENT_TIMESTAMP']
            )
        );
        $formUpsert->execute(['doctor_id' => $doctorId, 'setting_value' => $formValue]);
    }

    // Reset patient particulars field placement
    if (!empty($payload['reset_particulars_fields'])) {
        $delPartStmt = $pdo->prepare(
            "DELETE FROM zimrx_interface_settings
             WHERE doctor_id = :doctor_id AND setting_scope = 'particulars_fields' AND setting_key = 'field_placement'"
        );
        $delPartStmt->execute(['doctor_id' => $doctorId]);
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
    echo json_encode([
        'ok' => true,
        'particulars_custom_fields' => $customFieldDefinitions,
        'visit_form_modules' => $visitFormDefinitions,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (Throwable) {}
    }
    http_response_code(500);
    error_log('[ZimRx] save_interface_layout error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to save interface layout.']);
}
