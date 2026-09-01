<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/pc_catalog_lib.php';

function pc_default_options(string $field): array {
    if ($field === 'duration') {
        return pc_duration_defaults();
    }
    if ($field === 'unit') {
        return pc_unit_defaults();
    }
    return [];
}

function pc_aux_category(string $field): string {
    return $field === 'duration' ? 'pc_duration' : 'pc_unit';
}

function pc_aux_suggestions(PDO $pdo, int $doctorId, string $field, string $term, int $limit = 20): array {
    $category = pc_aux_category($field);
    $defaults = pc_default_options($field);
    $suggestions = [];
    $seen = [];

    $add = function (string $value, int $score, string $source) use (&$suggestions, &$seen) {
        $value = rx_clean($value);
        if ($value === '') {
            return;
        }
        $key = rx_norm($value);
        if (isset($seen[$key]) && $seen[$key] >= $score) {
            return;
        }
        $seen[$key] = $score;
        $suggestions[$key] = [
            'label' => $value,
            'value' => $value,
            'source' => $source,
            'score' => $score,
        ];
    };

    foreach (pc_learned_terms($pdo, $doctorId, $category, $term, 'recent', 12) as $index => $row) {
        $add((string)($row['term'] ?? ''), 600 - $index, 'learned');
    }

    foreach ($defaults as $index => $value) {
        if ($term !== '' && stripos($value, $term) === false) {
            continue;
        }
        $add($value, 320 - $index, 'default');
    }

    usort($suggestions, static function ($a, $b) {
        return ($b['score'] <=> $a['score']) ?: strcmp($a['label'], $b['label']);
    });
    return array_slice(array_values($suggestions), 0, $limit);
}

function pc_enabled_priority_rows(PDO $pdo, int $doctorId): array {
    $rows = pc_priority_rows($pdo, $doctorId);
    $rows = array_values(array_filter($rows, static function ($row) {
        return (int)($row['is_enabled'] ?? 0) === 1;
    }));

    if ($rows) {
        return $rows;
    }

    return pc_priority_default_rows();
}

try {
    $field = strtolower(rx_clean($_GET['field'] ?? 'complaint'));
    $term = rx_clean($_GET['term'] ?? '');
    $doctorId = current_user_doctor_id();

    // Bulk prefetch: return all fields in one call
    if ($field === 'bulk') {
        $priorityRows = pc_enabled_priority_rows($pdo, $doctorId);
        $hiddenMap = pc_hidden_map($pdo, $doctorId);

        // Build complaint suggestions (same logic as below)
        $suggestions = [];
        $seen = [];
        $add = function (string $value, string $source, int $score, array $extra = []) use (&$suggestions, &$seen) {
            $value = rx_clean($value);
            if ($value === '') return;
            $key = rx_norm($value);
            if (isset($seen[$key]) && $seen[$key] >= $score) return;
            $seen[$key] = $score;
            $suggestions[$key] = array_merge(['label' => $value, 'value' => $value, 'source' => $source, 'score' => $score], $extra);
        };

        foreach ($priorityRows as $sourceIndex => $sourceRow) {
            $source = $sourceRow['source'];
            $baseScore = 1000 - ($sourceIndex * 240);
            if ($source === 'most_used') {
                foreach (pc_learned_terms($pdo, $doctorId, 'PC', '', 'usage', 24) as $index => $row) {
                    $value = (string)($row['term'] ?? '');
                    if (pc_is_hidden($hiddenMap, 'most_used', $value)) continue;
                    $add($value, 'most_used', $baseScore - $index + min(80, (int)($row['usage_count'] ?? 0)), ['usage_count' => (int)($row['usage_count'] ?? 0)]);
                }
            } elseif ($source === 'custom') {
                foreach (pc_custom_terms($pdo, $doctorId, '', 18) as $index => $row) {
                    $value = (string)($row['term'] ?? '');
                    if (pc_is_hidden($hiddenMap, 'custom', $value)) continue;
                    $add($value, 'custom', $baseScore - $index);
                }
            } elseif ($source === 'static_pc' || $source === 'snomed') {
                foreach (pc_static_pc_search('', 18) as $index => $row) {
                    $value = (string)($row['preferred_term'] ?? '');
                    if (pc_is_hidden($hiddenMap, $source, $value) || pc_is_hidden($hiddenMap, 'static_pc', $value) || pc_is_hidden($hiddenMap, 'snomed', $value)) continue;
                    $add($value, 'static_pc', $baseScore - $index, ['category' => (string)($row['category'] ?? '')]);
                }
            } elseif ($source === 'icd') {
                foreach (pc_icd_search('', 12) as $index => $row) {
                    $value = (string)($row['search_term'] ?? '');
                    if (pc_is_hidden($hiddenMap, 'icd', $value)) continue;
                    $score = $baseScore - $index;
                    if ((int)($row['is_official'] ?? 0) === 1) $score += 10;
                    $add($value, 'icd', $score, ['is_official' => (int)($row['is_official'] ?? 0)]);
                }
            }
        }
        usort($suggestions, static function ($a, $b) { return ($b['score'] <=> $a['score']) ?: strcmp($a['label'], $b['label']); });

        rx_json([
            'complaint' => array_slice(array_values($suggestions), 0, 60),
            'duration'  => pc_aux_suggestions($pdo, $doctorId, 'duration', '', 20),
            'unit'      => pc_aux_suggestions($pdo, $doctorId, 'unit', '', 20),
        ]);
    }

    if ($field === 'duration' || $field === 'unit') {
        rx_json(pc_aux_suggestions($pdo, $doctorId, $field, $term, 20));
    }

    $priorityRows = pc_enabled_priority_rows($pdo, $doctorId);
    $hiddenMap = pc_hidden_map($pdo, $doctorId);
    $suggestions = [];
    $seen = [];

    $add = function (string $value, string $source, int $score, array $extra = []) use (&$suggestions, &$seen) {
        $value = rx_clean($value);
        if ($value === '') {
            return;
        }
        $key = rx_norm($value);
        if (isset($seen[$key]) && $seen[$key] >= $score) {
            return;
        }
        $seen[$key] = $score;
        $suggestions[$key] = array_merge([
            'label' => $value,
            'value' => $value,
            'source' => $source,
            'score' => $score,
        ], $extra);
    };

    foreach ($priorityRows as $sourceIndex => $sourceRow) {
        $source = $sourceRow['source'];
        $baseScore = 1000 - ($sourceIndex * 240);

        if ($source === 'most_used') {
            foreach (pc_learned_terms($pdo, $doctorId, 'PC', $term, 'usage', 24) as $index => $row) {
                $value = (string)($row['term'] ?? '');
                if (pc_is_hidden($hiddenMap, 'most_used', $value)) {
                    continue;
                }
                $add($value, 'most_used', $baseScore - $index + min(80, (int)($row['usage_count'] ?? 0)), [
                    'usage_count' => (int)($row['usage_count'] ?? 0),
                ]);
            }
            continue;
        }

        if ($source === 'custom') {
            foreach (pc_custom_terms($pdo, $doctorId, $term, 18) as $index => $row) {
                $value = (string)($row['term'] ?? '');
                if (pc_is_hidden($hiddenMap, 'custom', $value)) {
                    continue;
                }
                $add($value, 'custom', $baseScore - $index);
            }
            continue;
        }

        if ($source === 'static_pc' || $source === 'snomed') {
            foreach (pc_static_pc_search($term, $term === '' ? 18 : 30) as $index => $row) {
                $value = (string)($row['preferred_term'] ?? '');
                if (pc_is_hidden($hiddenMap, $source, $value) || pc_is_hidden($hiddenMap, 'static_pc', $value) || pc_is_hidden($hiddenMap, 'snomed', $value)) {
                    continue;
                }
                $add($value, 'static_pc', $baseScore - $index, [
                    'category' => (string)($row['category'] ?? ''),
                ]);
            }
            continue;
        }

        if ($source === 'icd') {
            foreach (pc_icd_search($term, $term === '' ? 12 : 20) as $index => $row) {
                $value = (string)($row['search_term'] ?? '');
                if (pc_is_hidden($hiddenMap, 'icd', $value)) {
                    continue;
                }
                $score = $baseScore - $index;
                if ((int)($row['is_official'] ?? 0) === 1) {
                    $score += 10;
                }
                $add($value, 'icd', $score, [
                    'is_official' => (int)($row['is_official'] ?? 0),
                ]);
            }
        }
    }

    usort($suggestions, static function ($a, $b) use ($term) {
        if ($term !== '') {
            [$aMatch, $aDelta] = pc_match_priority((string)$a['label'], $term);
            [$bMatch, $bDelta] = pc_match_priority((string)$b['label'], $term);
            if ($aMatch !== $bMatch) {
                return $aMatch <=> $bMatch;
            }
            if ($aDelta !== $bDelta) {
                return $aDelta <=> $bDelta;
            }
        }
        return ($b['score'] <=> $a['score']) ?: strcmp($a['label'], $b['label']);
    });
    rx_json(array_slice(array_values($suggestions), 0, 60));
} catch (Exception $e) {
    rx_json(['error' => $e->getMessage()]);
}
