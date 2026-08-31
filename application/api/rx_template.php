<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/rx_template_lib.php';

function rx_template_json($payload): void {
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function rx_template_payload(string $type, int $doctorId): array {
    $config = rx_template_config($type);
    return [
        'config' => [
            'type' => $config['type'],
            'title' => $config['title'],
            'label_bn' => $config['label_bn'],
            'label_en' => $config['label_en'],
            'has_default_form' => $config['has_default_form'],
        ],
        'settings' => rx_phrase_settings($type, $doctorId),
        'rows' => rx_phrase_rows($type, $doctorId, true),
        'default_rows' => rx_phrase_static_rows($type),
    ];
}

function rx_template_next_sort(PDO $pdo, string $table, int $doctorId): int {
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {$table} WHERE doctor_id = :doctor_id AND is_hidden = 0");
    $stmt->execute(['doctor_id' => $doctorId]);
    return max(1, (int)$stmt->fetchColumn());
}

function rx_template_shift_sort(PDO $pdo, string $table, int $doctorId, int $fromOrder): void {
    $stmt = $pdo->prepare(
        "UPDATE {$table}
         SET sort_order = sort_order + 1, updated_at = " . DbSql::now() . "
         WHERE doctor_id = :doctor_id AND sort_order >= :sort_order"
    );
    $stmt->execute(['doctor_id' => $doctorId, 'sort_order' => $fromOrder]);
}

function rx_template_save_row(string $type, int $doctorId, array $row): array {
    $config = rx_template_config($type);
    $pdo = rx_user_pdo();
    rx_phrase_ensure_schema($type, $pdo);
    $table = $config['user_table'];
    $bn = $config['bn_column'];
    $en = $config['en_column'];
    $id = (int)($row['id'] ?? 0);
    $staticId = (int)($row['static_id'] ?? 99);
    $valueBn = rx_clean($row['value_bn'] ?? $row[$bn] ?? '');
    $valueEn = rx_clean($row['value_en'] ?? $row[$en] ?? '');
    $categoryBn = rx_clean($row['category_bn'] ?? '');
    $categoryEn = rx_clean($row['category_en'] ?? '');
    $categorySearchAlias = rx_clean($row['category_search_alias'] ?? '');
    $searchAlias = rx_clean($row['search_alias'] ?? '');
    $isPinned = (int)($row['is_pinned'] ?? 0) === 1 ? 1 : 0;
    $defaultForm = json_encode(rx_json_string_list($row['default_dosage_form'] ?? '[]'), JSON_UNESCAPED_UNICODE);
    $sortOrder = max(1, (int)($row['sort_order'] ?? 0));
    $addPosition = rx_clean($row['add_position'] ?? 'last');

    if ($valueBn === '' && $valueEn === '') {
        rx_template_json(['error' => 'Template text is required.']);
    }
    if ($type === 'advice' && $categoryEn === '' && $categoryBn === '') {
        rx_template_json(['error' => 'Category is required.']);
    }

    if ($id <= 0 && $sortOrder <= 1 && $addPosition !== 'first') {
        $sortOrder = rx_template_next_sort($pdo, $table, $doctorId);
    }
    if ($id <= 0 && $addPosition === 'first') {
        $sortOrder = 1;
        rx_template_shift_sort($pdo, $table, $doctorId, 1);
    }

    $columns = "doctor_id, static_id, {$bn}, {$en}, usage_count, is_pinned, is_hidden, sort_order, is_edited, created_at, updated_at";
    $values = ":doctor_id, :static_id, :bn, :en, :usage_count, :is_pinned, :is_hidden, :sort_order, :is_edited, " . DbSql::now() . ", " . DbSql::now();
    if ($type !== 'advice') {
        $columns .= ', search_alias';
        $values .= ', :search_alias';
    }
    if ($config['has_default_form']) {
        $columns .= ', default_dosage_form';
        $values .= ', :default_dosage_form';
    }
    if ($type === 'advice') {
        $columns .= ', name, category_bn, category_en, category_search_alias';
        $values .= ', :name, :category_bn, :category_en, :category_search_alias';
    }

    $params = [
        'doctor_id' => $doctorId,
        'static_id' => $staticId,
        'bn' => $valueBn,
        'en' => $valueEn,
        'usage_count' => max(0, (int)($row['usage_count'] ?? 0)),
        'is_pinned' => $isPinned,
        'is_hidden' => (int)($row['is_hidden'] ?? 0) === 1 ? 1 : 0,
        'sort_order' => $sortOrder,
        'is_edited' => $staticId > 0 && $staticId !== 99 ? 1 : 0,
    ];
    if ($type !== 'advice') {
        $params['search_alias'] = $searchAlias;
    }
    if ($type === 'advice') {
        $params['name'] = $categoryEn;
        $params['category_bn'] = $categoryBn;
        $params['category_en'] = $categoryEn;
        $params['category_search_alias'] = $categorySearchAlias;
    }
    if ($config['has_default_form']) {
        $params['default_dosage_form'] = $defaultForm;
    }

    if ($id > 0) {
        $assignments = "{$bn} = :bn, {$en} = :en,
            is_pinned = :is_pinned, is_hidden = :is_hidden, sort_order = :sort_order,
            is_edited = :is_edited, updated_at = " . DbSql::now();
        if ($type !== 'advice') {
            $assignments .= ", search_alias = :search_alias";
        }
        if ($type === 'advice') {
            $assignments .= ", name = :name, category_bn = :category_bn, category_en = :category_en, category_search_alias = :category_search_alias";
        }
        if ($config['has_default_form']) {
            $assignments .= ", default_dosage_form = :default_dosage_form";
        }
        $params['id'] = $id;
        $stmt = $pdo->prepare("UPDATE {$table} SET {$assignments} WHERE id = :id AND doctor_id = :doctor_id");
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$values})");
        $stmt->execute($params);
    }

    return rx_template_payload($type, $doctorId);
}

function rx_template_upsert_system_row(PDO $pdo, string $type, int $doctorId, int $staticId): int {
    $config = rx_template_config($type);
    $table = $config['user_table'];
    $bn = $config['bn_column'];
    $en = $config['en_column'];
    $staticMap = rx_phrase_static_map($type);
    $static = $staticMap[$staticId] ?? null;
    if (!$static) {
        return 0;
    }

    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE doctor_id = :doctor_id AND static_id = :static_id LIMIT 1");
    $stmt->execute(['doctor_id' => $doctorId, 'static_id' => $staticId]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    if ($id > 0) {
        return $id;
    }

    $defaultForm = rx_clean($static['default_dosage_form'] ?? '[]');
    $columns = "doctor_id, static_id, {$bn}, {$en}, usage_count, is_pinned, is_hidden, sort_order, is_edited, created_at, updated_at";
    $values = ":doctor_id, :static_id, :bn, :en, 0, 0, 0, :sort_order, 0, " . DbSql::now() . ", " . DbSql::now();
    if ($type !== 'advice') {
        $columns .= ', search_alias';
        $values .= ', :search_alias';
    }
    $params = [
        'doctor_id' => $doctorId,
        'static_id' => $staticId,
        'bn' => rx_clean($static[$bn] ?? $static['body'] ?? ''),
        'en' => rx_clean($static[$en] ?? ''),
        'sort_order' => (int)($static['sort_order'] ?? 0),
    ];
    if ($type !== 'advice') {
        $params['search_alias'] = rx_clean($static['search_alias'] ?? '');
    }
    if ($type === 'advice') {
        $columns .= ', name, category_bn, category_en, category_search_alias';
        $values .= ', :name, :category_bn, :category_en, :category_search_alias';
        $params['name'] = rx_clean($static['category_en'] ?? $static['name'] ?? '');
        $params['category_bn'] = rx_clean($static['category_bn'] ?? '');
        $params['category_en'] = rx_clean($static['category_en'] ?? $static['name'] ?? '');
        $params['category_search_alias'] = rx_clean($static['category_search_alias'] ?? '');
    }
    if ($config['has_default_form']) {
        $columns .= ', default_dosage_form';
        $values .= ', :default_dosage_form';
        $params['default_dosage_form'] = $defaultForm;
    }
    $stmt = $pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$values})");
    $stmt->execute($params);
    return (int)$pdo->lastInsertId();
}

function rx_template_row_action(string $type, int $doctorId, array $payload): array {
    $config = rx_template_config($type);
    $pdo = rx_user_pdo();
    rx_phrase_ensure_schema($type, $pdo);
    $table = $config['user_table'];
    $action = rx_clean($payload['action'] ?? '');
    $id = (int)($payload['id'] ?? 0);
    $staticId = (int)($payload['static_id'] ?? 0);

    if ($id <= 0 && $staticId > 0 && $staticId !== 99) {
        $id = rx_template_upsert_system_row($pdo, $type, $doctorId, $staticId);
    }
    if ($id <= 0) {
        return rx_template_payload($type, $doctorId);
    }

    if ($action === 'toggle_pin') {
        $stmt = $pdo->prepare("UPDATE {$table} SET is_pinned = CASE WHEN is_pinned = 1 THEN 0 ELSE 1 END, updated_at = " . DbSql::now() . " WHERE id = :id AND doctor_id = :doctor_id");
        $stmt->execute(['id' => $id, 'doctor_id' => $doctorId]);
    } elseif ($action === 'toggle_hidden') {
        $stmt = $pdo->prepare("UPDATE {$table} SET is_hidden = CASE WHEN is_hidden = 1 THEN 0 ELSE 1 END, updated_at = " . DbSql::now() . " WHERE id = :id AND doctor_id = :doctor_id");
        $stmt->execute(['id' => $id, 'doctor_id' => $doctorId]);
    } elseif ($action === 'reset_row') {
        if ($staticId > 0 && $staticId !== 99) {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = :id AND doctor_id = :doctor_id");
            $stmt->execute(['id' => $id, 'doctor_id' => $doctorId]);
        } else {
            $stmt = $pdo->prepare("UPDATE {$table} SET usage_count = 0, is_pinned = 0, is_hidden = 0, updated_at = " . DbSql::now() . " WHERE id = :id AND doctor_id = :doctor_id");
            $stmt->execute(['id' => $id, 'doctor_id' => $doctorId]);
        }
    } elseif ($action === 'delete_permanent') {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = :id AND doctor_id = :doctor_id AND static_id IN (0, 99)");
        $stmt->execute(['id' => $id, 'doctor_id' => $doctorId]);
    }

    return rx_template_payload($type, $doctorId);
}

function rx_template_bulk_action(string $type, int $doctorId, string $action): array {
    $config = rx_template_config($type);
    $pdo = rx_user_pdo();
    rx_phrase_ensure_schema($type, $pdo);
    $table = $config['user_table'];

    if ($action === 'reset_all' || $action === 'reset_full') {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE doctor_id = :doctor_id");
        $stmt->execute(['doctor_id' => $doctorId]);
    } elseif ($action === 'reset_usage') {
        $stmt = $pdo->prepare("UPDATE {$table} SET usage_count = 0, updated_at = " . DbSql::now() . " WHERE doctor_id = :doctor_id");
        $stmt->execute(['doctor_id' => $doctorId]);
    } elseif ($action === 'remove_all') {
        foreach (rx_phrase_static_rows($type) as $row) {
            rx_template_upsert_system_row($pdo, $type, $doctorId, (int)$row['id']);
        }
        $stmt = $pdo->prepare("UPDATE {$table} SET is_hidden = 1, updated_at = " . DbSql::now() . " WHERE doctor_id = :doctor_id");
        $stmt->execute(['doctor_id' => $doctorId]);
    } elseif ($action === 'remove_custom_typed') {
        $stmt = $pdo->prepare("UPDATE {$table} SET is_hidden = 1, updated_at = " . DbSql::now() . " WHERE doctor_id = :doctor_id AND static_id = 0");
        $stmt->execute(['doctor_id' => $doctorId]);
    } elseif ($action === 'remove_added') {
        $stmt = $pdo->prepare("UPDATE {$table} SET is_hidden = 1, updated_at = " . DbSql::now() . " WHERE doctor_id = :doctor_id AND static_id = 99");
        $stmt->execute(['doctor_id' => $doctorId]);
    }

    return rx_template_payload($type, $doctorId);
}

function rx_template_normalize_row(string $type, array $row, array $staticMap, int $doctorId, int $fallbackSortOrder): ?array {
    $config = rx_template_config($type);
    $bn = $config['bn_column'];
    $en = $config['en_column'];

    $staticId = (int)($row['static_id'] ?? 0);
    $kind = $staticId === 99 ? 'added' : ($staticId === 0 ? 'custom_typed' : 'system');
    $usageCount = max(0, (int)($row['usage_count'] ?? 0));
    $sortOrder = max(1, (int)($row['sort_order'] ?? $fallbackSortOrder));
    $isPinned = (int)($row['is_pinned'] ?? 0) === 1 ? 1 : 0;
    $isHidden = (int)($row['is_hidden'] ?? 0) === 1 ? 1 : 0;
    $valueBn = rx_clean($row['value_bn'] ?? $row[$bn] ?? '');
    $valueEn = rx_clean($row['value_en'] ?? $row[$en] ?? '');
    $searchAlias = rx_clean($row['search_alias'] ?? '');

    if ($kind === 'system' && isset($staticMap[$staticId])) {
        $staticRow = $staticMap[$staticId];
        $valueBn = rx_clean($row['value_bn'] ?? $row[$bn] ?? $staticRow[$bn] ?? '');
        $valueEn = rx_clean($row['value_en'] ?? $row[$en] ?? $staticRow[$en] ?? '');
        $searchAlias = rx_clean($row['search_alias'] ?? $staticRow['search_alias'] ?? '');
        
        $isEdited = 0;
        if ($valueBn !== rx_clean($staticRow[$bn] ?? '') ||
            $valueEn !== rx_clean($staticRow[$en] ?? '') ||
            $searchAlias !== rx_clean($staticRow['search_alias'] ?? '')) {
            $isEdited = 1;
        }

        $res = [
            'doctor_id' => $doctorId,
            'static_id' => $staticId,
            'bn' => $valueBn,
            'en' => $valueEn,
            'search_alias' => $searchAlias,
            'usage_count' => $usageCount,
            'is_pinned' => $isPinned,
            'is_hidden' => $isHidden,
            'sort_order' => $sortOrder,
            'is_edited' => $isEdited,
        ];
        if ($config['has_default_form']) {
            $res['default_dosage_form'] = json_encode(rx_json_string_list($row['default_dosage_form'] ?? $staticRow['default_dosage_form'] ?? '[]'), JSON_UNESCAPED_UNICODE);
        }
        if ($type === 'advice') {
            $categoryBn = rx_clean($row['category_bn'] ?? $staticRow['category_bn'] ?? '');
            $categoryEn = rx_clean($row['category_en'] ?? $staticRow['category_en'] ?? $staticRow['name'] ?? '');
            $categorySearchAlias = rx_clean($row['category_search_alias'] ?? $staticRow['category_search_alias'] ?? '');
            if ($categoryBn !== rx_clean($staticRow['category_bn'] ?? '') ||
                $categoryEn !== rx_clean($staticRow['category_en'] ?? $staticRow['name'] ?? '') ||
                $categorySearchAlias !== rx_clean($staticRow['category_search_alias'] ?? '')) {
                $res['is_edited'] = 1;
            }
            $res['name'] = $categoryEn;
            $res['category_bn'] = $categoryBn;
            $res['category_en'] = $categoryEn;
            $res['category_search_alias'] = $categorySearchAlias;
        }
        return $res;
    }

    if ($valueBn === '' && $valueEn === '') {
        return null;
    }

    $res = [
        'doctor_id' => $doctorId,
        'static_id' => $kind === 'added' ? 99 : 0,
        'bn' => $valueBn,
        'en' => $valueEn,
        'search_alias' => $searchAlias,
        'usage_count' => $usageCount,
        'is_pinned' => $isPinned,
        'is_hidden' => $isHidden,
        'sort_order' => $sortOrder,
        'is_edited' => 0,
    ];
    if ($config['has_default_form']) {
        $res['default_dosage_form'] = json_encode(rx_json_string_list($row['default_dosage_form'] ?? '[]'), JSON_UNESCAPED_UNICODE);
    }
    if ($type === 'advice') {
        $categoryBn = rx_clean($row['category_bn'] ?? '');
        $categoryEn = rx_clean($row['category_en'] ?? '');
        $categorySearchAlias = rx_clean($row['category_search_alias'] ?? '');
        $res['name'] = $categoryEn;
        $res['category_bn'] = $categoryBn;
        $res['category_en'] = $categoryEn;
        $res['category_search_alias'] = $categorySearchAlias;
    }
    return $res;
}

function rx_template_save_all(string $type, int $doctorId, array $rows, array $settings): array {
    $config = rx_template_config($type);
    $pdo = rx_user_pdo();
    rx_phrase_ensure_schema($type, $pdo);
    $table = $config['user_table'];
    $staticMap = rx_phrase_static_map($type);
    $bn = $config['bn_column'];
    $en = $config['en_column'];

    $pdo->beginTransaction();
    try {
        rx_phrase_save_settings($pdo, $type, $doctorId, $settings);

        $deleteStmt = $pdo->prepare("DELETE FROM {$table} WHERE doctor_id = :doctor_id");
        $deleteStmt->execute(['doctor_id' => $doctorId]);

        $columns = "doctor_id, static_id, {$bn}, {$en}, usage_count, is_pinned, is_hidden, sort_order, is_edited, created_at, updated_at";
        $values = ":doctor_id, :static_id, :bn, :en, :usage_count, :is_pinned, :is_hidden, :sort_order, :is_edited, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP";
        if ($type !== 'advice') {
            $columns .= ', search_alias';
            $values .= ', :search_alias';
        }
        if ($config['has_default_form']) {
            $columns .= ', default_dosage_form';
            $values .= ', :default_dosage_form';
        }
        if ($type === 'advice') {
            $columns .= ', name, category_bn, category_en, category_search_alias';
            $values .= ', :name, :category_bn, :category_en, :category_search_alias';
        }

        $insertStmt = $pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$values})");

        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = rx_template_normalize_row($type, $row, $staticMap, $doctorId, $index + 1);
            if ($normalized === null) {
                continue;
            }
            $params = [
                'doctor_id' => $normalized['doctor_id'],
                'static_id' => $normalized['static_id'],
                'bn' => $normalized['bn'],
                'en' => $normalized['en'],
                'usage_count' => $normalized['usage_count'],
                'is_pinned' => $normalized['is_pinned'],
                'is_hidden' => $normalized['is_hidden'],
                'sort_order' => $normalized['sort_order'],
                'is_edited' => $normalized['is_edited'],
            ];
            if ($type !== 'advice') {
                $params['search_alias'] = $normalized['search_alias'];
            }
            if ($config['has_default_form']) {
                $params['default_dosage_form'] = $normalized['default_dosage_form'];
            }
            if ($type === 'advice') {
                $params['name'] = $normalized['name'];
                $params['category_bn'] = $normalized['category_bn'];
                $params['category_en'] = $normalized['category_en'];
                $params['category_search_alias'] = $normalized['category_search_alias'];
            }
            $insertStmt->execute($params);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return rx_template_payload($type, $doctorId);
}

try {
    $type = rx_clean($_GET['type'] ?? $_POST['type'] ?? '');
    if ($type === '') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (is_array($body)) {
            $type = rx_clean($body['type'] ?? '');
        }
    }
    $type = $type ?: 'dose';
    $doctorId = rx_active_doctor_id();
    rx_template_config($type);
    rx_phrase_ensure_schema($type);

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        rx_template_json(rx_template_payload($type, $doctorId));
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        rx_template_json(['error' => 'Invalid payload.']);
    }

    $action = rx_clean($payload['action'] ?? 'save_row');
    if ($action === 'save_settings') {
        rx_phrase_save_settings(rx_user_pdo(), $type, $doctorId, is_array($payload['settings'] ?? null) ? $payload['settings'] : []);
        rx_template_json(rx_template_payload($type, $doctorId));
    }
    if ($action === 'save_row') {
        rx_template_json(rx_template_save_row($type, $doctorId, is_array($payload['row'] ?? null) ? $payload['row'] : []));
    }
    if ($action === 'save_all') {
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
        $settings = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
        rx_template_json(rx_template_save_all($type, $doctorId, $rows, $settings));
    }
    if (in_array($action, ['toggle_pin', 'toggle_hidden', 'reset_row', 'delete_permanent'], true)) {
        rx_template_json(rx_template_row_action($type, $doctorId, $payload));
    }
    if ($action === 'reorder') {
        $orderedIds = $payload['ids'] ?? [];
        if (!empty($orderedIds) && is_array($orderedIds)) {
            $config = rx_template_config($type);
            $pdo = rx_user_pdo();
            $table = $config['user_table'];
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE {$table} SET sort_order = :sort_order, updated_at = " . DbSql::now() . " WHERE id = :id AND doctor_id = :doctor_id");
                foreach ($orderedIds as $index => $rowId) {
                    $stmt->execute([
                        'sort_order' => $index + 1,
                        'id' => (int)$rowId,
                        'doctor_id' => $doctorId
                    ]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
        rx_template_json(rx_template_payload($type, $doctorId));
    }
    if (in_array($action, ['reset_all', 'reset_full', 'reset_usage', 'remove_all', 'remove_custom_typed', 'remove_added'], true)) {
        rx_template_json(rx_template_bulk_action($type, $doctorId, $action));
    }

    rx_template_json(['error' => 'Unsupported action.']);
} catch (Throwable $e) {
    rx_template_json(['error' => $e->getMessage()]);
}

