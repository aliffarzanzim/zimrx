<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/pc_catalog_lib.php';

function pc_settings_used_groups(PDO $pdo, int $doctorId, array $priorityRows): array {
    $groups = [];
    foreach ($priorityRows as $row) {
        $groups[$row['source']] = [
            'source' => $row['source'],
            'label' => $row['label'],
            'is_enabled' => (int)($row['is_enabled'] ?? 0),
            'items' => [],
        ];
    }

    if (!isset($groups['most_used'])) {
        $groups['most_used'] = [
            'source' => 'most_used',
            'label' => pc_source_label('most_used'),
            'is_enabled' => 1,
            'items' => [],
        ];
    }

    $hiddenMap = pc_hidden_map($pdo, $doctorId);
    foreach (pc_learned_terms($pdo, $doctorId, 'PC', '', 'usage', 60) as $row) {
        $term = rx_clean($row['term'] ?? '');
        if ($term === '') {
            continue;
        }

        $source = pc_classify_term_source($pdo, $doctorId, $term, $priorityRows);
        if (!isset($groups[$source])) {
            $groups[$source] = [
                'source' => $source,
                'label' => pc_source_label($source),
                'is_enabled' => 1,
                'items' => [],
            ];
        }

        $groups[$source]['items'][] = [
            'term' => $term,
            'usage_count' => (int)($row['usage_count'] ?? 0),
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'is_hidden' => pc_is_hidden($hiddenMap, $source, $term),
        ];
    }

    return array_values($groups);
}

function pc_settings_custom_terms(PDO $pdo, int $doctorId): array {
    return array_map(static function ($row) {
        return [
            'term' => (string)($row['term'] ?? ''),
            'usage_count' => (int)($row['usage_count'] ?? 0),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }, pc_custom_terms($pdo, $doctorId, '', 80));
}

function pc_settings_custom_durations(PDO $pdo, int $doctorId): array {
    return array_map(static function ($row) {
        return [
            'term' => (string)($row['term'] ?? ''),
            'usage_count' => (int)($row['usage_count'] ?? 0),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }, pc_custom_durations($pdo, $doctorId, '', 80));
}

function pc_settings_search_results(PDO $pdo, int $doctorId, string $query, array $priorityRows): array {
    $query = rx_clean($query);
    if ($query === '') {
        return [];
    }

    $hiddenMap = pc_hidden_map($pdo, $doctorId);
    $rankMap = pc_source_rank_map($priorityRows);
    $results = [];
    $seen = [];
    $ordinal = 0;

    $add = function (string $source, string $term, array $extra = []) use (&$results, &$seen, $hiddenMap, $rankMap, &$ordinal) {
        $term = rx_clean($term);
        if ($term === '') {
            return;
        }
        $key = rx_norm($term);
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $results[] = array_merge([
            'source' => $source,
            'source_label' => pc_source_label($source),
            'term' => $term,
            'is_hidden' => pc_is_hidden($hiddenMap, $source, $term),
            'sort_rank' => $rankMap[$source] ?? 999,
            'ordinal' => ++$ordinal,
        ], $extra);
    };

    // 1. Most Used / Learned
    foreach (pc_learned_terms($pdo, $doctorId, 'PC', $query, 'usage', 20) as $row) {
        $add('most_used', (string)($row['term'] ?? ''), [
            'usage_count' => (int)($row['usage_count'] ?? 0),
        ]);
    }

    // 2. Custom Terms
    foreach (pc_custom_terms($pdo, $doctorId, $query, 20) as $row) {
        $add('custom', (string)($row['term'] ?? ''));
    }

    // 3. System Static P/C
    foreach (pc_static_pc_search($query, 40) as $row) {
        $add('static_pc', (string)($row['preferred_term'] ?? ''), [
            'category' => (string)($row['category'] ?? ''),
        ]);
    }

    usort($results, static function ($a, $b) use ($query) {
        $aTerm = strtolower($a['term']);
        $bTerm = strtolower($b['term']);
        $q = strtolower($query);

        $aExact = $aTerm === $q ? 0 : 1;
        $bExact = $bTerm === $q ? 0 : 1;
        if ($aExact !== $bExact) {
            return $aExact <=> $bExact;
        }

        $aPrefix = str_starts_with($aTerm, $q) ? 0 : 1;
        $bPrefix = str_starts_with($bTerm, $q) ? 0 : 1;
        if ($aPrefix !== $bPrefix) {
            return $aPrefix <=> $bPrefix;
        }

        if (($a['sort_rank'] ?? 999) !== ($b['sort_rank'] ?? 999)) {
            return ($a['sort_rank'] ?? 999) <=> ($b['sort_rank'] ?? 999);
        }

        $lenDiff = strlen($aTerm) <=> strlen($bTerm);
        if ($lenDiff !== 0) {
            return $lenDiff;
        }

        return ($a['ordinal'] ?? 0) <=> ($b['ordinal'] ?? 0)
            ?: strcmp($aTerm, $bTerm);
    });

    return array_slice(array_map(static function ($row) {
        unset($row['sort_rank']);
        unset($row['ordinal']);
        return $row;
    }, $results), 0, 50);
}

function pc_settings_usage_ranking(PDO $pdo, int $doctorId, array $priorityRows): array {
    $hiddenMap = pc_hidden_map($pdo, $doctorId);
    $items = [];
    $rank = 0;
    foreach (pc_learned_terms($pdo, $doctorId, 'PC', '', 'usage', 100) as $row) {
        $term = rx_clean($row['term'] ?? '');
        if ($term === '') {
            continue;
        }

        $source = pc_classify_term_source($pdo, $doctorId, $term, $priorityRows);
        $rank++;
        $items[] = [
            'rank' => $rank,
            'term' => $term,
            'source' => $source,
            'source_label' => pc_source_label($source),
            'usage_count' => (int)($row['usage_count'] ?? 0),
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'is_hidden' => pc_is_hidden($hiddenMap, $source, $term),
        ];
    }
    return $items;
}

function pc_settings_payload(PDO $pdo, int $doctorId): array {
    $priorityRows = pc_priority_rows($pdo, $doctorId);
    return [
        'priorities' => $priorityRows,
        'used_groups' => pc_settings_used_groups($pdo, $doctorId, $priorityRows),
        'usage_ranking' => pc_settings_usage_ranking($pdo, $doctorId, $priorityRows),
        'custom_terms' => pc_settings_custom_terms($pdo, $doctorId),
        'custom_durations' => pc_settings_custom_durations($pdo, $doctorId),
        'hidden_terms' => pc_hidden_terms($pdo, $doctorId),
    ];
}

function pc_settings_save_priorities(PDO $pdo, int $doctorId, array $priorities): void {
    $userPdo = rx_user_pdo();
    $validSources = array_keys(pc_supported_sources());
    $prepared = [];
    foreach ($priorities as $index => $row) {
        if (!is_array($row)) {
            continue;
        }
        $source = rx_clean($row['source'] ?? '');
        if (!in_array($source, $validSources, true) || isset($prepared[$source])) {
            continue;
        }
        $prepared[$source] = [
            'source' => $source,
            'sort_order' => count($prepared) + 1,
            'is_enabled' => (int)($row['is_enabled'] ?? 0) === 1 ? 1 : 0,
        ];
    }

    foreach (pc_priority_default_rows() as $defaultRow) {
        if (!isset($prepared[$defaultRow['source']])) {
            $prepared[$defaultRow['source']] = [
                'source' => $defaultRow['source'],
                'sort_order' => count($prepared) + 1,
                'is_enabled' => (int)$defaultRow['is_enabled'],
            ];
        }
    }

    $userPdo->beginTransaction();
    try {
        pc_ensure_priority_rows($pdo, $doctorId);
        $stmt = $userPdo->prepare(
            "UPDATE zimrx_user_pc_settings
             SET sort_order = :sort_order,
                 is_enabled = :is_enabled,
                 updated_at = CURRENT_TIMESTAMP
             WHERE doctor_id = :doctor_id
               AND setting_key = 'source_priority'
               AND source = :source"
        );
        foreach (array_values($prepared) as $index => $row) {
            $stmt->execute([
                'doctor_id' => max(1, $doctorId),
                'source' => $row['source'],
                'sort_order' => $index + 1,
                'is_enabled' => $row['is_enabled'],
            ]);
        }
        $userPdo->commit();
    } catch (Throwable $e) {
        if ($userPdo->inTransaction()) {
            $userPdo->rollBack();
        }
        throw $e;
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== realpath(__FILE__)) {
    return;
}

try {
    $doctorId = current_user_doctor_id();
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'GET') {
        $action = strtolower(rx_clean($_GET['action'] ?? ''));
        if ($action === 'search') {
            $query = rx_clean($_GET['q'] ?? '');
            $priorityRows = pc_priority_rows($pdo, $doctorId);
            rx_json(['results' => pc_settings_search_results($pdo, $doctorId, $query, $priorityRows)]);
        }

        rx_json(pc_settings_payload($pdo, $doctorId));
    }

    if ($method !== 'POST') {
        rx_json(['error' => 'Unsupported request method.']);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    $action = strtolower(rx_clean($payload['action'] ?? ''));

    if ($action === 'add_custom') {
        $userPdo = rx_user_pdo();
        $term = trim((string)preg_replace('/\s+/u', ' ', rx_clean($payload['term'] ?? '')));
        if ($term === '') {
            rx_json(['error' => 'Custom PC is required.']);
        }

        $stmt = $userPdo->prepare(
            "INSERT INTO zimrx_user_pc (
                doctor_id, category, term, usage_count, created_at, updated_at
            ) VALUES (
                :doctor_id, 'custom', :term, 0, " . DbSql::now() . ", " . DbSql::now() . "
            )
            " . DbSql::upsert('doctor_id, category, term', ['updated_at'], ['updated_at' => DbSql::now()])
        );
        $stmt->execute([
            'doctor_id' => max(1, $doctorId),
            'term' => $term,
        ]);

        $userPdo->prepare(
                "DELETE FROM zimrx_user_pc_settings
                 WHERE doctor_id = :doctor_id
                   AND setting_key = 'hidden_term'
                   AND source = 'custom'
                   AND term = :term COLLATE NOCASE"
            )->execute([
                'doctor_id' => max(1, $doctorId),
                'term' => $term,
            ]);

        rx_json(pc_settings_payload($pdo, $doctorId));
    }

    if ($action === 'remove_custom') {
        $userPdo = rx_user_pdo();
        $term = rx_clean($payload['term'] ?? '');
        $stmt = $userPdo->prepare(
            "DELETE FROM zimrx_user_pc
             WHERE doctor_id = :doctor_id
               AND category IN ('custom', 'PC')
               AND term = :term COLLATE NOCASE"
        );
        $stmt->execute([
            'doctor_id' => max(1, $doctorId),
            'term' => $term,
        ]);

        $userPdo->prepare(
            "DELETE FROM zimrx_user_pc_settings
             WHERE doctor_id = :doctor_id
               AND setting_key = 'hidden_term'
               AND term = :term COLLATE NOCASE"
        )->execute([
            'doctor_id' => max(1, $doctorId),
            'term' => $term,
        ]);

        rx_json(pc_settings_payload($pdo, $doctorId));
    }

    if ($action === 'edit_custom') {
        $userPdo = rx_user_pdo();
        $oldTerm = trim((string)preg_replace('/\s+/u', ' ', rx_clean($payload['old_term'] ?? '')));
        $newTerm = trim((string)preg_replace('/\s+/u', ' ', rx_clean($payload['new_term'] ?? '')));

        if ($oldTerm === '' || $newTerm === '') {
            rx_json(['error' => 'Both current and new complaint terms are required.']);
        }

        if (strcasecmp($oldTerm, $newTerm) !== 0) {
            $stmt = $userPdo->prepare(
                "UPDATE zimrx_user_pc
                 SET term = :new_term,
                     updated_at = " . DbSql::now() . "
                 WHERE doctor_id = :doctor_id
                   AND category IN ('custom', 'PC')
                   AND term = :old_term COLLATE NOCASE"
            );
            $stmt->execute([
                'doctor_id' => max(1, $doctorId),
                'old_term' => $oldTerm,
                'new_term' => $newTerm,
            ]);

            $userPdo->prepare(
                "UPDATE zimrx_user_pc_settings
                 SET term = :new_term
                 WHERE doctor_id = :doctor_id
                   AND setting_key = 'hidden_term'
                   AND term = :old_term COLLATE NOCASE"
            )->execute([
                'doctor_id' => max(1, $doctorId),
                'old_term' => $oldTerm,
                'new_term' => $newTerm,
            ]);
        }

        rx_json(pc_settings_payload($pdo, $doctorId));
    }

    if ($action === 'add_custom_duration') {
        $userPdo = rx_user_pdo();
        $term = trim((string)preg_replace('/\s+/u', ' ', rx_clean($payload['term'] ?? '')));
        if ($term === '') {
            rx_json(['error' => 'Duration value is required.']);
        }

        if (in_array($term, pc_duration_defaults(), true)) {
            rx_json(['error' => "\"$term\" is already a standard default duration."]);
        }

        foreach (['custom_duration', 'pc_duration'] as $category) {
            $stmt = $userPdo->prepare(
                "INSERT INTO zimrx_user_pc (
                    doctor_id, category, term, usage_count, created_at, updated_at
                ) VALUES (
                    :doctor_id, :category, :term, 0, " . DbSql::now() . ", " . DbSql::now() . "
                )
                " . DbSql::upsert('doctor_id, category, term', ['updated_at'], ['updated_at' => DbSql::now()])
            );
            $stmt->execute([
                'doctor_id' => max(1, $doctorId),
                'category' => $category,
                'term' => $term,
            ]);
        }

        rx_json(pc_settings_payload($pdo, $doctorId));
    }

    if ($action === 'edit_custom_duration') {
        $userPdo = rx_user_pdo();
        $oldTerm = trim((string)preg_replace('/\s+/u', ' ', rx_clean($payload['old_term'] ?? '')));
        $newTerm = trim((string)preg_replace('/\s+/u', ' ', rx_clean($payload['new_term'] ?? '')));

        if ($oldTerm === '' || $newTerm === '') {
            rx_json(['error' => 'Both current and new duration values are required.']);
        }

        if (in_array($newTerm, pc_duration_defaults(), true)) {
            rx_json(['error' => "\"$newTerm\" is already a standard default duration."]);
        }

        if (strcasecmp($oldTerm, $newTerm) !== 0) {
            $stmt = $userPdo->prepare(
                "UPDATE zimrx_user_pc
                 SET term = :new_term,
                     updated_at = " . DbSql::now() . "
                 WHERE doctor_id = :doctor_id
                   AND category IN ('custom_duration', 'pc_duration')
                   AND term = :old_term COLLATE NOCASE"
            );
            $stmt->execute([
                'doctor_id' => max(1, $doctorId),
                'new_term' => $newTerm,
                'old_term' => $oldTerm,
            ]);
        }

        rx_json(pc_settings_payload($pdo, $doctorId));
    }

    if ($action === 'remove_custom_duration') {
        $userPdo = rx_user_pdo();
        $term = rx_clean($payload['term'] ?? '');
        $stmt = $userPdo->prepare(
            "DELETE FROM zimrx_user_pc
             WHERE doctor_id = :doctor_id
               AND category IN ('custom_duration', 'pc_duration')
               AND term = :term COLLATE NOCASE"
        );
        $stmt->execute([
            'doctor_id' => max(1, $doctorId),
            'term' => $term,
        ]);

        rx_json(pc_settings_payload($pdo, $doctorId));
    }

    if ($action === 'save_priorities') {
        $priorities = is_array($payload['priorities'] ?? null) ? $payload['priorities'] : [];
        pc_settings_save_priorities($pdo, $doctorId, $priorities);
        rx_json(pc_settings_payload($pdo, $doctorId));
    }

    if ($action === 'unhide_all') {
        $userPdo = rx_user_pdo();
        $userPdo->prepare(
            "DELETE FROM zimrx_user_pc_settings
             WHERE doctor_id = :doctor_id
               AND setting_key = 'hidden_term'"
        )->execute(['doctor_id' => max(1, $doctorId)]);

        rx_json([
            'ok' => true,
            'data' => pc_settings_payload($pdo, $doctorId),
        ]);
    }

    if ($action === 'toggle_hidden') {
        $source = rx_clean($payload['source'] ?? '');
        $term = rx_clean($payload['term'] ?? '');
        $hidden = (int)($payload['hidden'] ?? 0) === 1;
        if ($term === '') {
            rx_json(['error' => 'Term is required.']);
        }
        if ($source === '') {
            $source = 'static_pc';
        }

        $userPdo = rx_user_pdo();
        // Clear all previous hidden entries for this term first
        $userPdo->prepare(
            "DELETE FROM zimrx_user_pc_settings
             WHERE doctor_id = :doctor_id
               AND setting_key = 'hidden_term'
               AND term = :term COLLATE NOCASE"
        )->execute([
            'doctor_id' => max(1, $doctorId),
            'term' => $term,
        ]);

        if ($hidden) {
            $userPdo->prepare(
                "INSERT INTO zimrx_user_pc_settings (
                    doctor_id, setting_key, source, term, sort_order, is_enabled, created_at, updated_at
                ) VALUES (
                    :doctor_id, 'hidden_term', :source, :term, 0, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )"
            )->execute([
                'doctor_id' => max(1, $doctorId),
                'source' => $source,
                'term' => $term,
            ]);
        }

        $priorityRows = pc_priority_rows($pdo, $doctorId);
        rx_json([
            'ok' => true,
            'results' => pc_settings_search_results($pdo, $doctorId, rx_clean($payload['query'] ?? ''), $priorityRows),
            'data' => pc_settings_payload($pdo, $doctorId),
        ]);
    }

    rx_json(['error' => 'Unknown action.']);
} catch (Exception $e) {
    rx_json(['error' => $e->getMessage()]);
}
