<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/rx_regimen_lib.php';
require_once __DIR__ . '/rx_template_lib.php';

try {
    $userPdo = rx_user_pdo();
    $systemPdo = rx_system_pdo();
    $type = rx_clean($_GET['type'] ?? '');
    $columns = [
        'dose' => ['column' => 'dose'],
        'instruction' => ['column' => 'instruction'],
        'duration' => ['column' => 'duration'],
    ];

    if (!isset($columns[$type])) {
        rx_json(['error' => 'Invalid suggestion type.']);
    }

    $column = $columns[$type]['column'];
    $term = rx_clean($_GET['term'] ?? '');
    $termLike = '%' . $term . '%';
    $context = rx_context_from_request($_GET);

    $suggestions = [];
    $seen = [];
    $rank = 0;

    $addSuggestion = function ($value, int $score, array $extra = []) use (&$suggestions, &$seen, &$rank) {
        $value = rx_clean($value);
        if ($value === '') {
            return;
        }

        $key = rx_norm($value);
        if (isset($seen[$key]) && $seen[$key] >= $score) {
            if (!empty($extra)) {
                $suggestions[$key] = array_merge($suggestions[$key], $extra);
            }
            return;
        }

        $rank++;
        $seen[$key] = $score;
        $suggestions[$key] = array_merge([
            'value' => $value,
            'label' => $value,
            'score' => $score,
            'rank' => $rank,
        ], $extra);
    };

    $searchRegimenTable = function (PDO $sourcePdo, string $table, int $scoreBase) use ($column, $term, $termLike, $context, $addSuggestion) {
        if (!rx_table_exists($sourcePdo, $table)) {
            return;
        }

        $whereTerm = $term === '' ? '' : "AND {$column} LIKE :term";
        $params = ['brand_id' => $context['brand_id']];
        if ($term !== '') {
            $params['term'] = $termLike;
        }

        $order = 'use_count DESC, id DESC';
        $weightColumn = 'use_count';

        $stmt = $sourcePdo->prepare(
            "SELECT {$column} AS value, COALESCE({$weightColumn}, 0) AS weight
             FROM {$table}
             WHERE brand_id <> '' AND brand_id = :brand_id
                   {$whereTerm}
             ORDER BY {$order}
             LIMIT 25"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $addSuggestion($row['value'], $scoreBase + (int)($row['weight'] ?? 0));
        }
    };

    $searchGenericTable = function (PDO $sourcePdo, string $table, int $scoreBase) use ($column, $term, $termLike, $context, $addSuggestion) {
        if (!rx_table_exists($sourcePdo, $table) || $context['generic_id'] === '') {
            return;
        }

        $whereTerm = $term === '' ? '' : "AND {$column} LIKE :term";
        $params = [
            'generic_id' => $context['generic_id'],
            'strength_norm' => rx_norm_compact($context['strength']),
            'form_norm' => rx_norm_compact($context['form']),
        ];
        if ($term !== '') {
            $params['term'] = $termLike;
        }

        $order = 'use_count DESC, id DESC';
        $weightColumn = 'use_count';

        $stmt = $sourcePdo->prepare(
            "SELECT {$column} AS value, COALESCE({$weightColumn}, 0) AS weight
             FROM {$table}
             WHERE generic_id = :generic_id
               AND (:strength_norm = '' OR REPLACE(LOWER(strength), ' ', '') = :strength_norm)
               AND (:form_norm = '' OR REPLACE(REPLACE(LOWER(form), ' ', ''), '.', '') = :form_norm)
                   {$whereTerm}
             ORDER BY {$order}
             LIMIT 25"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $addSuggestion($row['value'], $scoreBase + (int)($row['weight'] ?? 0));
        }
    };

    $searchTemplateTable = function (PDO $sourcePdo, int $scoreBase) use ($type, $term, $termLike, $context, $addSuggestion) {
        if (!rx_table_exists($sourcePdo, 'drug_template') || $context['generic_id'] === '') {
            return;
        }

        $map = [
            'dose' => "COALESCE(NULLIF(dose_digit_bn, ''), dose_text_bn)",
            'instruction' => 'instruction_bn',
            'duration' => 'duration_bn',
        ];
        $columnExpr = $map[$type] ?? '';
        if ($columnExpr === '') {
            return;
        }

        $whereTerm = $term === '' ? '' : "AND {$columnExpr} LIKE :term";
        $params = [
            'generic_id' => $context['generic_id'],
            'strength_norm' => rx_norm_compact($context['strength']),
            'form_norm' => rx_norm_compact($context['form']),
        ];
        if ($term !== '') {
            $params['term'] = $termLike;
        }

        $stmt = $sourcePdo->prepare(
            "SELECT {$columnExpr} AS value, 0 AS weight
             FROM drug_template
             WHERE CAST(generic_id AS TEXT) = CAST(:generic_id AS TEXT)
               AND (:strength_norm = '' OR REPLACE(LOWER(strength), ' ', '') = :strength_norm)
               AND (:form_norm = '' OR REPLACE(REPLACE(LOWER(std_form), ' ', ''), '.', '') = :form_norm)
                   {$whereTerm}
             ORDER BY \"row\" ASC
             LIMIT 25"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $addSuggestion($row['value'], $scoreBase);
        }
    };

    $searchRegimenTable($userPdo, 'zimrx_user_drugs', 400);
    $searchGenericTable($userPdo, 'zimrx_user_drugs', 200);
    $searchTemplateTable($systemPdo, 150);

    if ($type === 'instruction') {
        foreach (rx_instruction_suggestions($term, rx_active_doctor_id(), 100) as $row) {
            $addSuggestion($row['value'], 10 + (int)$row['usage_count'] + ((int)$row['is_pinned'] * 1000), [
                'is_pinned' => (int)($row['is_pinned'] ?? 0)
            ]);
        }
    } elseif (in_array($type, ['dose', 'duration'], true)) {
        foreach (rx_phrase_suggestions_for_type($type, $term, rx_active_doctor_id(), 100) as $row) {
            $addSuggestion($row['value'], 10 + (int)($row['usage_count'] ?? 0) + ((int)($row['is_pinned'] ?? 0) * 1000), [
                'is_pinned' => (int)($row['is_pinned'] ?? 0),
            ]);
        }
    }

    usort($suggestions, function ($a, $b) {
        return $b['score'] <=> $a['score'] ?: $a['rank'] <=> $b['rank'];
    });

    $results = array_map(function ($item) {
        unset($item['rank']);
        return $item;
    }, array_slice(array_values($suggestions), 0, 100));

    rx_json($results);
} catch (Exception $e) {
    rx_json(['error' => $e->getMessage()]);
}
