<?php
/**
 * ZimRx Physical Examination Library
 * Manages static physical examination catalog merging, doctor preferences,
 * custom parameters, and active OPD quick-table definitions.
 */

require_once __DIR__ . '/../db.php';

function physical_exam_user_pdo(): PDO {
    return DbConnections::userdata();
}

function physical_exam_static_pdo(): PDO {
    return DbConnections::staticDb();
}

function physical_exam_ensure_schema(?PDO $userPdo = null): void {
    $pdo = $userPdo ?: physical_exam_user_pdo();
    zimrx_db_ensure_physical_examination_settings_schema($pdo);
}

function physical_exam_static_rows(): array {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $staticPdo = physical_exam_static_pdo();
    if (!zimrx_db_table_exists($staticPdo, 'zimrx_static_physical_examination')) {
        $cached = [];
        return $cached;
    }

    $stmt = $staticPdo->query(
        "SELECT id, system, category, item_code, display_name, full_name, input_type,
                delimiter, default_unit, normal_value, dropdown_options, finding_wordlists,
                is_default_active, sort_order
         FROM zimrx_static_physical_examination
         ORDER BY id ASC"
    );
    $cached = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return $cached;
}

function physical_exam_get_doctor_config(?int $doctorId = null): array {
    $doctorId = max(1, (int)($doctorId ?: (function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1)));
    $userPdo = physical_exam_user_pdo();
    physical_exam_ensure_schema($userPdo);

    $userRows = [];
    if (zimrx_db_table_exists($userPdo, 'zimrx_user_physical_examination_settings')) {
        $stmt = $userPdo->prepare(
            "SELECT id, doctor_id, system, category, item_code, display_name, full_name,
                    input_type, delimiter, default_unit, normal_value, dropdown_options,
                    finding_wordlists, is_active, sort_order, is_custom
             FROM zimrx_user_physical_examination_settings
             WHERE doctor_id = :doctor_id
             ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute(['doctor_id' => $doctorId]);
        $userRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $staticRows = physical_exam_static_rows();
    $userMap = [];
    $customRows = [];

    foreach ($userRows as $uRow) {
        $key = trim((string)($uRow['item_code'] ?? ''));
        if ((int)($uRow['is_custom'] ?? 0) === 1) {
            $customRows[] = $uRow;
        } elseif ($key !== '') {
            $userMap[$key] = $uRow;
        }
    }

    $mergedItems = [];
    $systemsOrder = [];

    foreach ($staticRows as $sRow) {
        $key = trim((string)($sRow['item_code'] ?? ''));
        $uRow = $userMap[$key] ?? null;

        $sys = trim((string)($uRow['system'] ?? $sRow['system'] ?? ''));
        if ($sys !== '' && !in_array($sys, $systemsOrder, true)) {
            $systemsOrder[] = $sys;
        }

        $isActive = $uRow !== null
            ? (int)($uRow['is_active'] ?? 0)
            : (int)($sRow['is_default_active'] ?? 0);

        $mergedItems[] = [
            'id' => (int)($uRow['id'] ?? $sRow['id'] ?? 0),
            'static_id' => (int)($sRow['id'] ?? 0),
            'item_code' => $key,
            'system' => $sys,
            'category' => trim((string)($uRow['category'] ?? $sRow['category'] ?? '')),
            'display_name' => trim((string)($uRow['display_name'] ?? $sRow['display_name'] ?? '')),
            'full_name' => trim((string)($uRow['full_name'] ?? $sRow['full_name'] ?? '')),
            'input_type' => trim((string)($uRow['input_type'] ?? $sRow['input_type'] ?? 'dropdown+textbox')),
            'delimiter' => (string)($uRow['delimiter'] ?? $sRow['delimiter'] ?? ''),
            'default_unit' => (string)($uRow['default_unit'] ?? $sRow['default_unit'] ?? ''),
            'normal_value' => (string)($uRow['normal_value'] ?? $sRow['normal_value'] ?? ''),
            'dropdown_options' => (string)($uRow['dropdown_options'] ?? $sRow['dropdown_options'] ?? ''),
            'finding_wordlists' => (string)($uRow['finding_wordlists'] ?? $sRow['finding_wordlists'] ?? ''),
            'is_active' => $isActive,
            'sort_order' => (int)($uRow['sort_order'] ?? $sRow['sort_order'] ?? 10),
            'is_custom' => 0,
            'is_default_active' => (int)($sRow['is_default_active'] ?? 0)
        ];
    }

    // Append custom rows
    foreach ($customRows as $cRow) {
        $sys = trim((string)($cRow['system'] ?? 'General'));
        if ($sys !== '' && !in_array($sys, $systemsOrder, true)) {
            $systemsOrder[] = $sys;
        }

        $mergedItems[] = [
            'id' => (int)($cRow['id'] ?? 0),
            'static_id' => 0,
            'item_code' => trim((string)($cRow['item_code'] ?? '')),
            'system' => $sys,
            'category' => trim((string)($cRow['category'] ?? 'Custom')),
            'display_name' => trim((string)($cRow['display_name'] ?? '')),
            'full_name' => trim((string)($cRow['full_name'] ?? '')),
            'input_type' => trim((string)($cRow['input_type'] ?? 'dropdown+textbox')),
            'delimiter' => (string)($cRow['delimiter'] ?? ''),
            'default_unit' => (string)($cRow['default_unit'] ?? ''),
            'normal_value' => (string)($cRow['normal_value'] ?? ''),
            'dropdown_options' => (string)($cRow['dropdown_options'] ?? ''),
            'finding_wordlists' => (string)($cRow['finding_wordlists'] ?? ''),
            'is_active' => (int)($cRow['is_active'] ?? 1),
            'sort_order' => (int)($cRow['sort_order'] ?? 100),
            'is_custom' => 1,
            'is_default_active' => 0
        ];
    }

    // Sort items by sort_order
    usort($mergedItems, function ($a, $b) {
        if ($a['sort_order'] === $b['sort_order']) {
            return $a['id'] <=> $b['id'];
        }
        return $a['sort_order'] <=> $b['sort_order'];
    });

    // Active items for OPD table
    $activeItems = array_values(array_filter($mergedItems, function ($item) {
        return (int)($item['is_active'] ?? 0) === 1;
    }));

    return [
        'doctor_id' => $doctorId,
        'has_customizations' => !empty($userRows),
        'systems' => $systemsOrder,
        'items' => $mergedItems,
        'active_items' => $activeItems
    ];
}

function physical_exam_save_doctor_config(int $doctorId, array $items): array {
    $doctorId = max(1, $doctorId);
    $userPdo = physical_exam_user_pdo();
    physical_exam_ensure_schema($userPdo);

    $userPdo->beginTransaction();
    try {
        $deleteStmt = $userPdo->prepare("DELETE FROM zimrx_user_physical_examination_settings WHERE doctor_id = :doctor_id");
        $deleteStmt->execute(['doctor_id' => $doctorId]);

        $insertStmt = $userPdo->prepare(
            "INSERT INTO zimrx_user_physical_examination_settings (
                doctor_id, system, category, item_code, display_name, full_name,
                input_type, delimiter, default_unit, normal_value, dropdown_options,
                finding_wordlists, is_active, sort_order, is_custom, created_at, updated_at
            ) VALUES (
                :doctor_id, :system, :category, :item_code, :display_name, :full_name,
                :input_type, :delimiter, :default_unit, :normal_value, :dropdown_options,
                :finding_wordlists, :is_active, :sort_order, :is_custom, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )"
        );

        $sortCounter = 10;
        foreach ($items as $item) {
            $code = trim((string)($item['item_code'] ?? ''));
            if ($code === '') continue;

            $insertStmt->execute([
                'doctor_id' => $doctorId,
                'system' => trim((string)($item['system'] ?? 'General')),
                'category' => trim((string)($item['category'] ?? 'General')),
                'item_code' => $code,
                'display_name' => trim((string)($item['display_name'] ?? $code)),
                'full_name' => trim((string)($item['full_name'] ?? $code)),
                'input_type' => trim((string)($item['input_type'] ?? 'dropdown+textbox')),
                'delimiter' => (string)($item['delimiter'] ?? ''),
                'default_unit' => (string)($item['default_unit'] ?? ''),
                'normal_value' => (string)($item['normal_value'] ?? ''),
                'dropdown_options' => (string)($item['dropdown_options'] ?? ''),
                'finding_wordlists' => (string)($item['finding_wordlists'] ?? ''),
                'is_active' => !empty($item['is_active']) ? 1 : 0,
                'sort_order' => isset($item['sort_order']) ? (int)$item['sort_order'] : $sortCounter,
                'is_custom' => !empty($item['is_custom']) ? 1 : 0
            ]);
            $sortCounter += 10;
        }

        $userPdo->commit();
    } catch (Throwable $e) {
        if ($userPdo->inTransaction()) {
            $userPdo->rollBack();
        }
        throw $e;
    }

    return physical_exam_get_doctor_config($doctorId);
}

function physical_exam_reset_doctor_config(int $doctorId): array {
    $doctorId = max(1, $doctorId);
    $userPdo = physical_exam_user_pdo();
    physical_exam_ensure_schema($userPdo);

    $stmt = $userPdo->prepare("DELETE FROM zimrx_user_physical_examination_settings WHERE doctor_id = :doctor_id");
    $stmt->execute(['doctor_id' => $doctorId]);

    return physical_exam_get_doctor_config($doctorId);
}
