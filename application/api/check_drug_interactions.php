<?php
define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

function rx_interaction_ids_from_request(): array {
    $raw = $_GET['generic_ids'] ?? $_POST['generic_ids'] ?? '';
    if (is_array($raw)) {
        $parts = $raw;
    } else {
        $parts = preg_split('/[,\s]+/', (string)$raw);
    }

    $ids = [];
    foreach ($parts as $part) {
        $id = trim((string)$part);
        if ($id !== '' && preg_match('/^\d+$/', $id)) {
            $ids[$id] = true;
        }
    }

    return array_keys($ids);
}

function rx_has_interaction_generic_columns(PDO $pdo): bool {
    $columns = [];
    foreach ($pdo->query('PRAGMA table_info(drug_interaction)') as $row) {
        $columns[$row['name'] ?? $row[1] ?? ''] = true;
    }

    return isset($columns['drug_a_generic_id'], $columns['drug_b_generic_id']);
}

function rx_norm_space($value): string {
    return trim(preg_replace('/\s+/', ' ', (string)($value ?? '')));
}

function rx_normalize_drug_name($value): string {
    $text = strtolower(rx_norm_space($value));
    $text = str_replace('&', ' and ', $text);
    $text = preg_replace('/\bplus\b/', ' and ', $text);
    $text = preg_replace('/[\/+,;]/', ' and ', $text);
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
    return rx_norm_space($text);
}

function rx_split_component_names($value): array {
    $text = rx_normalize_drug_name($value);
    if ($text === '') {
        return [];
    }

    $parts = preg_split('/\band\b/', $text);
    $parts = array_values(array_filter(array_map('rx_norm_space', $parts)));
    return count($parts) > 1 ? $parts : [$text];
}

function rx_build_generic_lookup(PDO $pdo): array {
    $keys = [];
    $ambiguous = [];
    foreach ($pdo->query('SELECT generic_id, generic_name, us_generic_name FROM drug_generic') as $row) {
        $genericId = rx_norm_space($row['generic_id'] ?? '');
        foreach ([$row['generic_name'] ?? '', $row['us_generic_name'] ?? ''] as $name) {
            $key = rx_normalize_drug_name($name);
            if ($key === '') {
                continue;
            }

            if (isset($keys[$key]) && $keys[$key] !== $genericId) {
                $ambiguous[$key] = true;
                unset($keys[$key]);
                continue;
            }

            if (!isset($ambiguous[$key])) {
                $keys[$key] = $genericId;
            }
        }
    }

    return $keys;
}

function rx_fetch_selected_generic_context(PDO $pdo, array $genericIds): array {
    $placeholders = implode(',', array_fill(0, count($genericIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT generic_id, generic_name, us_generic_name
         FROM drug_generic
         WHERE CAST(generic_id AS TEXT) IN ({$placeholders})"
    );
    $stmt->execute($genericIds);

    $lookup = rx_build_generic_lookup($pdo);
    $contexts = [];
    foreach ($stmt->fetchAll() as $row) {
        $selectedId = rx_norm_space($row['generic_id'] ?? '');
        $selectedName = rx_norm_space($row['generic_name'] ?? '');
        if ($selectedId === '') {
            continue;
        }

        $components = [$selectedId => $selectedName ?: $selectedId];
        foreach ([$row['generic_name'] ?? '', $row['us_generic_name'] ?? ''] as $name) {
            foreach (rx_split_component_names($name) as $componentName) {
                $componentId = $lookup[$componentName] ?? '';
                if ($componentId !== '') {
                    if (!isset($components[$componentId])) {
                        $components[$componentId] = ucwords($componentName);
                    }
                }
            }
        }

        $contexts[$selectedId] = [
            'generic_id' => (string)$selectedId,
            'generic_name' => $selectedName,
            'component_ids' => array_map('strval', array_keys($components)),
            'component_names' => $components,
        ];
    }

    return $contexts;
}

function rx_groups_for_component(array $contexts, string $componentId): array {
    $groups = [];
    $cid = (string)$componentId;
    foreach ($contexts as $selectedId => $context) {
        if (in_array($cid, $context['component_ids'], true)) {
            $groups[$selectedId] = $context['generic_name'] ?: $selectedId;
        }
    }

    return $groups;
}

function rx_pair_key(string $type, string $left, string $right, string $extra = ''): string {
    $pair = [$left, $right];
    sort($pair, SORT_NATURAL);
    return implode('|', [$type, $pair[0], $pair[1], $extra]);
}

try {
    $genericIds = rx_interaction_ids_from_request();
    if (count($genericIds) < 2) {
        echo json_encode(['interactions' => []]);
        exit;
    }

    $pdo = DbConnections::systemDb();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if (!rx_has_interaction_generic_columns($pdo)) {
        echo json_encode(['interactions' => []]);
        exit;
    }

    $contexts = rx_fetch_selected_generic_context($pdo, $genericIds);
    $componentIds = [];
    foreach ($contexts as $context) {
        foreach ($context['component_ids'] as $componentId) {
            if ($componentId !== '') {
                $componentIds[$componentId] = true;
            }
        }
    }
    $componentIds = array_keys($componentIds);

    if (count($componentIds) < 2) {
        echo json_encode(['interactions' => []]);
        exit;
    }

    $results = [];
    $seen = [];
    foreach ($contexts as $leftId => $leftContext) {
        foreach ($contexts as $rightId => $rightContext) {
            if ($leftId >= $rightId) {
                continue;
            }

            $shared = array_values(array_intersect($leftContext['component_ids'], $rightContext['component_ids']));
            foreach ($shared as $componentId) {
                if ($componentId === $leftId && $componentId === $rightId) {
                    continue;
                }

                $key = rx_pair_key('duplicate', $leftId, $rightId, $componentId);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $componentName = $leftContext['component_names'][$componentId]
                    ?? $rightContext['component_names'][$componentId]
                    ?? $componentId;
                $results[] = [
                    'id' => 'duplicate-' . $componentId . '-' . $leftId . '-' . $rightId,
                    'type' => 'duplicate_component',
                    'drug_a' => $leftContext['generic_name'],
                    'drug_b' => $rightContext['generic_name'],
                    'drug_a_generic_id' => $componentId,
                    'drug_b_generic_id' => $componentId,
                    'selected_generic_ids' => [$leftId, $rightId],
                    'selected_drug_names' => [$leftContext['generic_name'], $rightContext['generic_name']],
                    'interaction' => 'Duplicate active ingredient: ' . $componentName,
                ];
            }
        }
    }

    $placeholders = implode(',', array_fill(0, count($componentIds), '?'));
    $sql = "
        SELECT
            id,
            drug_a,
            drug_b,
            drug_a_generic_id,
            drug_b_generic_id,
            interaction
        FROM drug_interaction
        WHERE drug_a_generic_id IN ({$placeholders})
          AND drug_b_generic_id IN ({$placeholders})
          AND drug_a_generic_id <> ''
          AND drug_b_generic_id <> ''
        ORDER BY id
        LIMIT 50
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($componentIds, $componentIds));
    foreach ($stmt->fetchAll() as $row) {
        $leftGroups = rx_groups_for_component($contexts, rx_norm_space($row['drug_a_generic_id'] ?? ''));
        $rightGroups = rx_groups_for_component($contexts, rx_norm_space($row['drug_b_generic_id'] ?? ''));
        foreach ($leftGroups as $leftId => $leftName) {
            foreach ($rightGroups as $rightId => $rightName) {
                if ($leftId === $rightId) {
                    continue;
                }

                $key = rx_pair_key('interaction', $leftId, $rightId, (string)($row['id'] ?? ''));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $row['selected_generic_ids'] = [$leftId, $rightId];
                $row['selected_drug_names'] = [$leftName, $rightName];
                $row['type'] = 'interaction';
                $results[] = $row;
            }
        }
    }

    // Query lifestyle / food / alcohol advisories for prescribed drugs
    $lifestyleSql = "
        SELECT
            id,
            drug_a,
            drug_b,
            drug_a_generic_id,
            interaction
        FROM drug_interaction
        WHERE drug_a_generic_id IN ({$placeholders})
          AND interaction_type = 'lifestyle'
        ORDER BY id
        LIMIT 20
    ";
    $lifestyleStmt = $pdo->prepare($lifestyleSql);
    $lifestyleStmt->execute($componentIds);
    $lifestyleAdvisories = [];
    foreach ($lifestyleStmt->fetchAll() as $row) {
        $gid = rx_norm_space($row['drug_a_generic_id'] ?? '');
        $drugName = $contexts[$gid]['generic_name'] ?? $row['drug_a'];
        $key = 'lifestyle|' . $gid . '|' . strtolower($row['drug_b']);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $lifestyleAdvisories[] = [
            'id' => 'lifestyle-' . $row['id'],
            'type' => 'lifestyle',
            'drug' => $drugName,
            'substance' => $row['drug_b'],
            'generic_id' => $gid,
            'interaction' => $row['interaction']
        ];
    }

    echo json_encode([
        'interactions' => array_slice($results, 0, 50),
        'lifestyle_advisories' => $lifestyleAdvisories
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Drug interaction check failed']);
}
