<?php
/**
 * ZimRx Medical History Library
 * Manages static medical history catalog merging, doctor preferences,
 * custom categories/conditions, and active OPD quick-grid definitions.
 */

require_once __DIR__ . '/../db.php';

function med_history_user_pdo(): PDO {
    return DbConnections::userdata();
}

function med_history_static_pdo(): PDO {
    return DbConnections::staticDb();
}

function med_history_ensure_schema(?PDO $userPdo = null): void {
    $pdo = $userPdo ?: med_history_user_pdo();
    zimrx_db_ensure_medical_history_settings_schema($pdo);
}

function med_history_static_rows(): array {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $staticPdo = med_history_static_pdo();
    if (!zimrx_db_table_exists($staticPdo, 'zimrx_static_medical_history')) {
        $cached = [];
        return $cached;
    }

    $stmt = $staticPdo->query(
        "SELECT id, category, condition_key, display_label, full_name, field_type,
                dropdown_options, placeholder, is_default_active, sort_order
         FROM zimrx_static_medical_history
         ORDER BY id ASC"
    );
    $cached = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return $cached;
}

function med_history_get_doctor_config(?int $doctorId = null): array {
    $doctorId = max(1, (int)($doctorId ?: (function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1)));
    $userPdo = med_history_user_pdo();
    med_history_ensure_schema($userPdo);

    $userRows = [];
    if (zimrx_db_table_exists($userPdo, 'zimrx_user_medical_history_settings')) {
        $stmt = $userPdo->prepare(
            "SELECT id, doctor_id, category, condition_key, display_label, full_name,
                    field_type, dropdown_options, placeholder, is_active, sort_order, is_custom
             FROM zimrx_user_medical_history_settings
             WHERE doctor_id = :doctor_id
             ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute(['doctor_id' => $doctorId]);
        $userRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $staticRows = med_history_static_rows();
    $userMap = [];
    $customRows = [];

    foreach ($userRows as $uRow) {
        $key = trim((string)($uRow['condition_key'] ?? ''));
        if ((int)($uRow['is_custom'] ?? 0) === 1) {
            $customRows[] = $uRow;
        } elseif ($key !== '') {
            $userMap[$key] = $uRow;
        }
    }

    $hasCustomizations = !empty($userRows);
    $mergedConditions = [];
    $categoryOrder = [];

    foreach ($staticRows as $sRow) {
        $key = trim((string)($sRow['condition_key'] ?? ''));
        $uRow = $userMap[$key] ?? null;

        $cat = trim((string)($uRow['category'] ?? $sRow['category'] ?? ''));
        if ($cat !== '' && !in_array($cat, $categoryOrder, true)) {
            $categoryOrder[] = $cat;
        }

        $isActive = $uRow !== null
            ? (int)($uRow['is_active'] ?? 0)
            : (int)($sRow['is_default_active'] ?? 0);

        $mergedConditions[] = [
            'id' => (int)($uRow['id'] ?? $sRow['id'] ?? 0),
            'static_id' => (int)($sRow['id'] ?? 0),
            'condition_key' => $key,
            'category' => $cat,
            'display_label' => trim((string)($uRow['display_label'] ?? $sRow['display_label'] ?? '')),
            'full_name' => trim((string)($uRow['full_name'] ?? $sRow['full_name'] ?? '')),
            'field_type' => trim((string)($uRow['field_type'] ?? $sRow['field_type'] ?? 'none')),
            'dropdown_options' => trim((string)($uRow['dropdown_options'] ?? $sRow['dropdown_options'] ?? '')),
            'placeholder' => trim((string)($uRow['placeholder'] ?? $sRow['placeholder'] ?? '')),
            'is_active' => $isActive,
            'sort_order' => (int)($uRow['sort_order'] ?? $sRow['sort_order'] ?? 10),
            'is_custom' => 0,
            'is_default_active' => (int)($sRow['is_default_active'] ?? 0),
        ];
    }

    foreach ($customRows as $cRow) {
        $cat = trim((string)($cRow['category'] ?? 'Other'));
        if ($cat !== '' && !in_array($cat, $categoryOrder, true)) {
            $categoryOrder[] = $cat;
        }

        $mergedConditions[] = [
            'id' => (int)($cRow['id'] ?? 0),
            'static_id' => 0,
            'condition_key' => trim((string)($cRow['condition_key'] ?? '')),
            'category' => $cat,
            'display_label' => trim((string)($cRow['display_label'] ?? '')),
            'full_name' => trim((string)($cRow['full_name'] ?? '')),
            'field_type' => trim((string)($cRow['field_type'] ?? 'none')),
            'dropdown_options' => trim((string)($cRow['dropdown_options'] ?? '')),
            'placeholder' => trim((string)($cRow['placeholder'] ?? '')),
            'is_active' => (int)($cRow['is_active'] ?? 1),
            'sort_order' => (int)($cRow['sort_order'] ?? 10),
            'is_custom' => 1,
            'is_default_active' => 0,
        ];
    }

    // Build active_groups for OPD quick-grid
    $activeGroups = [];
    foreach ($mergedConditions as $cond) {
        if ($cond['is_active'] === 1) {
            $cat = $cond['category'];
            if (!isset($activeGroups[$cat])) {
                $activeGroups[$cat] = [];
            }
            $activeGroups[$cat][] = $cond;
        }
    }

    return [
        'has_customizations' => $hasCustomizations,
        'categories' => $categoryOrder,
        'conditions' => $mergedConditions,
        'active_groups' => $activeGroups,
    ];
}

function med_history_save_config(int $doctorId, array $items): array {
    $doctorId = max(1, $doctorId);
    $userPdo = med_history_user_pdo();
    med_history_ensure_schema($userPdo);

    $userPdo->beginTransaction();
    try {
        $deleteStmt = $userPdo->prepare("DELETE FROM zimrx_user_medical_history_settings WHERE doctor_id = :doctor_id");
        $deleteStmt->execute(['doctor_id' => $doctorId]);

        $insertStmt = $userPdo->prepare(
            "INSERT INTO zimrx_user_medical_history_settings (
                doctor_id, category, condition_key, display_label, full_name,
                field_type, dropdown_options, placeholder, is_active, sort_order,
                is_custom, created_at, updated_at
            ) VALUES (
                :doctor_id, :category, :condition_key, :display_label, :full_name,
                :field_type, :dropdown_options, :placeholder, :is_active, :sort_order,
                :is_custom, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )"
        );

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = trim((string)($item['condition_key'] ?? ''));
            if ($key === '') {
                $label = trim((string)($item['display_label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $key = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $label)));
            }

            $category = trim((string)($item['category'] ?? 'Other')) ?: 'Other';
            $displayLabel = trim((string)($item['display_label'] ?? '')) ?: $key;
            $fullName = trim((string)($item['full_name'] ?? '')) ?: $displayLabel;
            $fieldType = trim((string)($item['field_type'] ?? 'none'));
            if (!in_array($fieldType, ['none', 'textbox', 'dropdown', 'dropdown_text'], true)) {
                $fieldType = 'none';
            }
            $dropdownOptions = trim((string)($item['dropdown_options'] ?? ''));
            $placeholder = trim((string)($item['placeholder'] ?? ''));
            $isActive = (int)($item['is_active'] ?? 0) === 1 ? 1 : 0;
            $sortOrder = max(1, (int)($item['sort_order'] ?? ($index + 1) * 10));
            $isCustom = (int)($item['is_custom'] ?? 0) === 1 ? 1 : 0;

            $insertStmt->execute([
                'doctor_id' => $doctorId,
                'category' => $category,
                'condition_key' => $key,
                'display_label' => $displayLabel,
                'full_name' => $fullName,
                'field_type' => $fieldType,
                'dropdown_options' => $dropdownOptions,
                'placeholder' => $placeholder,
                'is_active' => $isActive,
                'sort_order' => $sortOrder,
                'is_custom' => $isCustom,
            ]);
        }

        $userPdo->commit();
    } catch (Throwable $e) {
        if ($userPdo->inTransaction()) {
            $userPdo->rollBack();
        }
        throw $e;
    }

    return med_history_get_doctor_config($doctorId);
}

function med_history_reset_to_default(int $doctorId): array {
    $doctorId = max(1, $doctorId);
    $userPdo = med_history_user_pdo();
    med_history_ensure_schema($userPdo);

    $stmt = $userPdo->prepare("DELETE FROM zimrx_user_medical_history_settings WHERE doctor_id = :doctor_id");
    $stmt->execute(['doctor_id' => $doctorId]);

    return med_history_get_doctor_config($doctorId);
}
