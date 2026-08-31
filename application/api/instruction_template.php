<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/rx_regimen_lib.php';

ini_set('display_errors', '0');

function instruction_template_payload(int $doctorId): array {
    return [
        'settings' => rx_instruction_template_settings($doctorId),
        'rows' => rx_instruction_template_rows($doctorId, true),
        'default_rows' => rx_static_instruction_rows(),
    ];
}

function instruction_template_form_suggestions(int $doctorId, string $query = ''): array {
    $query = rx_clean($query);
    $queryLike = '%' . $query . '%';
    $forms = [];
    $seen = [];

    $addForm = static function ($value) use (&$forms, &$seen): void {
        $form = rx_clean($value);
        if ($form === '') {
            return;
        }
        $key = mb_strtolower($form, 'UTF-8');
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $forms[] = $form;
    };

    $systemPdo = rx_system_pdo();
    if (rx_table_exists($systemPdo, 'drug_prescribe')) {
        $stmt = $systemPdo->prepare(
            "SELECT DISTINCT std_form AS form
             FROM drug_prescribe
             WHERE COALESCE(std_form, '') <> ''
               AND (:q = '' OR std_form LIKE :q_like)
             ORDER BY std_form ASC
             LIMIT 40"
        );
        $stmt->execute(['q' => $query, 'q_like' => $queryLike]);
        foreach ($stmt->fetchAll() as $row) {
            $addForm($row['form'] ?? '');
        }
    }

    if (rx_table_exists($systemPdo, 'drug_template')) {
        $stmt = $systemPdo->prepare(
            "SELECT DISTINCT std_form AS form
             FROM drug_template
             WHERE COALESCE(std_form, '') <> ''
               AND (:q = '' OR std_form LIKE :q_like)
             ORDER BY std_form ASC
             LIMIT 40"
        );
        $stmt->execute(['q' => $query, 'q_like' => $queryLike]);
        foreach ($stmt->fetchAll() as $row) {
            $addForm($row['form'] ?? '');
        }
    }

    $userPdo = rx_user_pdo();
    if (rx_table_exists($userPdo, 'zimrx_user_drugs')) {
        $stmt = $userPdo->prepare(
            "SELECT DISTINCT form
             FROM zimrx_user_drug
             WHERE doctor_id = :doctor_id
               AND COALESCE(form, '') <> ''
               AND (:q = '' OR form LIKE :q_like)
             ORDER BY form ASC
             LIMIT 40"
        );
        $stmt->execute([
            'doctor_id' => $doctorId,
            'q' => $query,
            'q_like' => $queryLike,
        ]);
        foreach ($stmt->fetchAll() as $row) {
            $addForm($row['form'] ?? '');
        }
    }

    return ['forms' => array_slice($forms, 0, 40)];
}

function instruction_template_save_settings(PDO $userPdo, int $doctorId, array $settings): void {
    $table = rx_instruction_template_settings_table();
    $defaults = rx_instruction_template_default_settings();
    $showMode = rx_clean($settings['show_mode'] ?? $defaults['show_mode']);
    if (!in_array($showMode, ['serial', 'usage'], true)) {
        $showMode = $defaults['show_mode'];
    }
    $showCustomTyped = (int)($settings['show_custom_typed'] ?? $defaults['show_custom_typed']) === 1 ? 1 : 0;

    $stmt = $userPdo->prepare(
        "INSERT INTO {$table} (doctor_id, instruction_id, setting_key, setting_value, updated_at)
         VALUES (:doctor_id, 0, :setting_key, :setting_value, " . DbSql::now() . ")
         " . DbSql::upsert(
             'doctor_id, instruction_id, setting_key',
             ['setting_value', 'updated_at'],
             ['updated_at' => DbSql::now()]
         )
    );
    foreach ([
        'show_mode' => $showMode,
        'show_custom_typed' => (string)$showCustomTyped,
    ] as $key => $value) {
        $stmt->execute([
            'doctor_id' => $doctorId,
            'setting_key' => $key,
            'setting_value' => $value,
        ]);
    }
}

function instruction_template_normalize_row(array $row, array $staticMap, int $doctorId, int $fallbackSortOrder): ?array {
    $staticId = (int)($row['static_id'] ?? 0);
    $kind = $staticId === 99 ? 'added' : ($staticId === 0 ? 'custom_typed' : 'system');
    $usageCount = max(0, (int)($row['usage_count'] ?? 0));
    $sortOrder = max(1, (int)($row['sort_order'] ?? $fallbackSortOrder));
    $isPinned = (int)($row['is_pinned'] ?? 0) === 1 ? 1 : 0;
    $isHidden = (int)($row['is_hidden'] ?? 0) === 1 ? 1 : 0;
    $defaultDosageForm = rx_clean($row['default_dosage_form'] ?? '[]');
    $defaultInstructionInAnotherRow = (int)($row['default_instruction_in_another_row'] ?? 0) === 1 ? 1 : 0;
    $defaultDosageForm = json_encode(rx_json_string_list($defaultDosageForm), JSON_UNESCAPED_UNICODE);

    if ($kind === 'system' && isset($staticMap[$staticId])) {
        $staticRow = $staticMap[$staticId];
        $instructionBn = trim((string)($row['instruction_bn'] ?? $staticRow['instruction_bn'] ?? ''));
        $instructionEn = trim((string)($row['instruction_en'] ?? $staticRow['instruction_en'] ?? ''));
        $searchAlias = trim((string)($row['search_alias'] ?? $staticRow['search_alias'] ?? ''));
        $defaultDosageForm = rx_clean($row['default_dosage_form'] ?? ($staticRow['default_dosage_form'] ?? '[]'));
        $defaultDosageForm = json_encode(rx_json_string_list($defaultDosageForm), JSON_UNESCAPED_UNICODE);

        $isEdited = rx_instruction_template_system_is_edited([
            'instruction_bn' => $instructionBn,
            'instruction_en' => $instructionEn,
            'search_alias' => $searchAlias,
            'default_dosage_form' => $defaultDosageForm,
            'default_instruction_in_another_row' => $defaultInstructionInAnotherRow,
        ], $staticRow);

        return [
            'static_id' => $staticId,
            'doctor_id' => $doctorId,
            'instruction_en' => $instructionEn,
            'instruction_bn' => $instructionBn,
            'search_alias' => $searchAlias,
            'usage_count' => $usageCount,
            'is_pinned' => $isPinned,
            'is_hidden' => $isHidden,
            'sort_order' => $sortOrder,
            'default_dosage_form' => $defaultDosageForm,
            'default_instruction_in_another_row' => $defaultInstructionInAnotherRow,
            'is_edited' => $isEdited,
        ];
    }

    $instructionBn = trim((string)($row['instruction_bn'] ?? ''));
    $instructionEn = trim((string)($row['instruction_en'] ?? ''));
    $searchAlias = trim((string)($row['search_alias'] ?? ''));
    if ($instructionBn === '' && $instructionEn === '' && $searchAlias === '') {
        return null;
    }

    return [
        'static_id' => $kind === 'added' ? 99 : 0,
        'doctor_id' => $doctorId,
        'instruction_en' => $instructionEn,
        'instruction_bn' => $instructionBn,
        'search_alias' => $searchAlias,
        'usage_count' => $usageCount,
        'is_pinned' => $isPinned,
        'is_hidden' => $isHidden,
        'sort_order' => $sortOrder,
        'default_dosage_form' => $defaultDosageForm,
        'default_instruction_in_another_row' => $defaultInstructionInAnotherRow,
        'is_edited' => 0,
    ];
}

function instruction_template_save_all(int $doctorId, array $rows, array $settings): array {
    $userPdo = rx_user_pdo();
    rx_instruction_template_ensure_schema($userPdo);
    $table = rx_instruction_usage_table($userPdo);
    $staticMap = rx_static_instruction_map();

    $userPdo->beginTransaction();
    try {
        instruction_template_save_settings($userPdo, $doctorId, $settings);

        $deleteStmt = $userPdo->prepare("DELETE FROM {$table} WHERE doctor_id = :doctor_id");
        $deleteStmt->execute(['doctor_id' => $doctorId]);

        $insertStmt = $userPdo->prepare(
            "INSERT INTO {$table} (
                static_id, doctor_id, instruction_en, instruction_bn, search_alias,
                usage_count, is_pinned, is_hidden, sort_order, default_dosage_form,
                default_instruction_in_another_row,
                is_edited, created_at, updated_at
            ) VALUES (
                :static_id, :doctor_id, :instruction_en, :instruction_bn, :search_alias,
                :usage_count, :is_pinned, :is_hidden, :sort_order, :default_dosage_form,
                :default_instruction_in_another_row,
                :is_edited, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )"
        );

        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = instruction_template_normalize_row($row, $staticMap, $doctorId, $index + 1);
            if ($normalized === null) {
                continue;
            }
            $insertStmt->execute($normalized);
        }

        $userPdo->commit();
    } catch (Throwable $e) {
        if ($userPdo->inTransaction()) {
            $userPdo->rollBack();
        }
        throw $e;
    }

    return instruction_template_payload($doctorId);
}

function instruction_template_reset_full(int $doctorId): array {
    $userPdo = rx_user_pdo();
    rx_instruction_template_ensure_schema($userPdo);
    $settingsTable = rx_instruction_template_settings_table();
    $instructionTables = [
        'zimrx_user_drug_instructionss',
        'zimrx_user_drug_instructions',
        'zimrx_user_instructions',
    ];

    $userPdo->beginTransaction();
    try {
        foreach ($instructionTables as $table) {
            if (rx_table_exists($userPdo, $table)) {
                if (DbSchema::columnExists($userPdo, $table, 'doctor_id')) {
                    $stmt = $userPdo->prepare("DELETE FROM {$table} WHERE doctor_id = :doctor_id");
                    $stmt->execute(['doctor_id' => $doctorId]);
                } else {
                    $userPdo->exec("DELETE FROM {$table}");
                }
            }
        }

        if (rx_table_exists($userPdo, $settingsTable)) {
            if (DbSchema::columnExists($userPdo, $settingsTable, 'doctor_id')) {
                $stmt = $userPdo->prepare("DELETE FROM {$settingsTable} WHERE doctor_id = :doctor_id");
                $stmt->execute(['doctor_id' => $doctorId]);
            } else {
                $userPdo->exec("DELETE FROM {$settingsTable}");
            }
        }

        $userPdo->commit();
    } catch (Throwable $e) {
        if ($userPdo->inTransaction()) {
            $userPdo->rollBack();
        }
        throw $e;
    }

    return instruction_template_payload($doctorId);
}

try {
    $doctorId = rx_active_doctor_id();
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    rx_instruction_template_ensure_schema(rx_user_pdo());

    if ($method === 'GET') {
        $action = strtolower(rx_clean($_GET['action'] ?? ''));
        if ($action === 'form_suggestions') {
            rx_json(instruction_template_form_suggestions($doctorId, rx_clean($_GET['q'] ?? '')));
        }

        rx_json(instruction_template_payload($doctorId));
    }

    if ($method !== 'POST') {
        rx_json(['error' => 'Unsupported request method.']);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        rx_json(['error' => 'Invalid payload.']);
    }

    $action = strtolower(rx_clean($payload['action'] ?? 'save_all'));
    if ($action === 'reset_full') {
        rx_json(instruction_template_reset_full($doctorId));
    }

    if ($action !== 'save_all') {
        rx_json(['error' => 'Unsupported action.']);
    }

    $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
    $settings = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
    rx_json(instruction_template_save_all($doctorId, $rows, $settings));
} catch (Throwable $e) {
    rx_json(['error' => $e->getMessage()]);
}
