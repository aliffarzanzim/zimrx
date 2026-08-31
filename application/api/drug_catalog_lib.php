<?php

require_once __DIR__ . '/user_drug_lib.php';

function drug_catalog_class_expr(string $genericExpr = 'p.generic_id'): string {
    return "COALESCE((
        SELECT GROUP_CONCAT(tc.class_name, ', ')
        FROM drug_therapeutic_class tc
        WHERE (',' || COALESCE(tc.generic_ids, '') || ',') LIKE '%,' || CAST({$genericExpr} AS TEXT) || ',%'
    ), '')";
}

function drug_catalog_select_sql(): string {
    return "
        p.brand_id AS id,
        p.brand_id AS brand_id,
        p.brand_id AS system_brand_id,
        p.prescribe_brand_short AS pres_new_upper,
        p.prescribe_brand_full AS full_form_brand_name,
        p.brand_name AS brand_name,
        p.generic_name AS generic,
        p.generic_name AS generic_name,
        p.labelled_generic_short AS labelled_generic_short,
        p.labelled_generic_full AS labelled_generic_full,
        COALESCE(p.prescribe_generic_short, '') AS prescribe_generic_short,
        COALESCE(p.prescribe_generic_full, '') AS prescribe_generic_full,
        COALESCE(p.us_generic_name, '') AS us_generic_name,
        COALESCE(p.who_atc_class, '') AS who_atc_class,
        p.generic_id AS generic_id,
        p.manufacturer_id AS company_id,
        COALESCE(NULLIF(p.manufacturer_name, ''), p.manufacturer_id) AS manufacturer,
        COALESCE(NULLIF(p.manufacturer_name_short, ''), NULLIF(p.manufacturer_name, ''), p.manufacturer_id) AS man_short,
        COALESCE(p.price, '') AS price,
        COALESCE(p.packsize, '') AS packsize,
        COALESCE(p.pregnancy_category, '') AS preg_cat,
        COALESCE(p.drug_class, '') AS cls,
        COALESCE(p.is_antibiotic, 0) AS is_antibiotic,
        COALESCE(p.is_high_alert_medicine, 0) AS is_high_alert_medicine,
        COALESCE(p.is_safe_in_pregnancy, 0) AS is_safe_in_pregnancy,
        COALESCE(p.is_safe_in_lactation, 0) AS is_safe_in_lactation,
        COALESCE(p.require_renal_adjustments, 0) AS require_renal_adjustment,
        COALESCE(p.is_safe_in_hepatic_impairment, 0) AS is_safe_in_hepatic_impairment,
        COALESCE(p.is_safe_in_paediatric, 0) AS is_safe_in_paediatrics,
        COALESCE(p.requires_tapering, 0) AS requires_tapering,
        COALESCE(p.immediate_warning, '') AS immediate_warning,
        COALESCE((
            SELECT g.precaution
            FROM drug_generic g
            WHERE CAST(g.generic_id AS TEXT) = CAST(p.generic_id AS TEXT)
            LIMIT 1
        ), '') AS precaution,
        COALESCE((
            SELECT g.interaction
            FROM drug_generic g
            WHERE CAST(g.generic_id AS TEXT) = CAST(p.generic_id AS TEXT)
            LIMIT 1
        ), '') AS generic_interaction,
        p.manufacturer_preference AS man_srt,
        p.form_order AS type_srt,
        p.form AS form,
        p.std_form AS form_new,
        p.strength AS strength
    ";
}

function drug_catalog_search_select_sql(): string {
    return "
        p.brand_id AS id,
        p.brand_id AS brand_id,
        p.brand_id AS system_brand_id,
        p.prescribe_brand_short AS pres_new_upper,
        p.prescribe_brand_full AS full_form_brand_name,
        p.brand_name AS brand_name,
        p.generic_name AS generic,
        p.generic_name AS generic_name,
        p.labelled_generic_short AS labelled_generic_short,
        p.labelled_generic_full AS labelled_generic_full,
        COALESCE(p.prescribe_generic_short, '') AS prescribe_generic_short,
        COALESCE(p.prescribe_generic_full, '') AS prescribe_generic_full,
        '' AS us_generic_name,
        '' AS who_atc_class,
        p.generic_id AS generic_id,
        p.manufacturer_id AS company_id,
        COALESCE(NULLIF(p.manufacturer_name, ''), p.manufacturer_id) AS manufacturer,
        COALESCE(NULLIF(p.manufacturer_name_short, ''), NULLIF(p.manufacturer_name, ''), p.manufacturer_id) AS man_short,
        COALESCE(p.price, '') AS price,
        COALESCE(p.packsize, '') AS packsize,
        COALESCE(p.pregnancy_category, '') AS preg_cat,
        COALESCE(p.drug_class, '') AS cls,
        COALESCE(p.is_antibiotic, 0) AS is_antibiotic,
        COALESCE(p.is_high_alert_medicine, 0) AS is_high_alert_medicine,
        COALESCE(p.is_safe_in_pregnancy, 0) AS is_safe_in_pregnancy,
        COALESCE(p.is_safe_in_lactation, 0) AS is_safe_in_lactation,
        COALESCE(p.require_renal_adjustments, 0) AS require_renal_adjustment,
        COALESCE(p.is_safe_in_hepatic_impairment, 0) AS is_safe_in_hepatic_impairment,
        COALESCE(p.is_safe_in_paediatric, 0) AS is_safe_in_paediatrics,
        COALESCE(p.requires_tapering, 0) AS requires_tapering,
        COALESCE(p.immediate_warning, '') AS immediate_warning,
        '' AS precaution,
        '' AS generic_interaction,
        p.manufacturer_preference AS man_srt,
        p.form_order AS type_srt,
        p.form AS form,
        p.std_form AS form_new,
        p.strength AS strength
    ";
}

function drug_catalog_from_sql(): string {
    return "
        FROM drug_prescribe p
    ";
}

function drug_catalog_order_sql(string $brandStartParam = ':qStart', string $genericStartParam = ':qStart'): string {
    return "
        CASE
            WHEN p.brand_name LIKE {$brandStartParam} THEN 0
            WHEN p.prescribe_brand_short LIKE {$brandStartParam} THEN 0
            WHEN p.generic_name LIKE {$genericStartParam} THEN 1
            ELSE 2
        END ASC,
        CAST(p.manufacturer_preference AS INTEGER) ASC,
        CAST(p.form_order AS INTEGER) ASC,
        p.brand_name ASC,
        p.prescribe_brand_short ASC
    ";
}

function drug_catalog_normalize_search_text(string $value): string {
    $value = strtolower(trim($value));
    $value = str_replace(['.', '/', '-', '(', ')', '+', ','], ' ', $value);
    return preg_replace('/\s+/', ' ', $value) ?: '';
}

function drug_catalog_canonical_search_text(string $value): string {
    $aliases = [
        'tablet' => 'tab',
        'tabs' => 'tab',
        'tab' => 'tab',
        'capsule' => 'cap',
        'capsules' => 'cap',
        'cap' => 'cap',
        'syrup' => 'syp',
        'syp' => 'syp',
        'suspension' => 'susp',
        'susp' => 'susp',
        'injection' => 'inj',
        'inject' => 'inj',
        'inj' => 'inj',
        'cream' => 'crm',
        'ointment' => 'oint',
        'suppository' => 'supp',
    ];
    $tokens = array_values(array_filter(explode(' ', drug_catalog_normalize_search_text($value)), static fn(string $token): bool => $token !== ''));
    foreach ($tokens as &$token) {
        $token = $aliases[$token] ?? $token;
    }
    unset($token);
    return implode(' ', $tokens);
}

function drug_catalog_search_tokens(string $value): array {
    $canonical = drug_catalog_canonical_search_text($value);
    if ($canonical === '') {
        return [];
    }
    return array_values(array_filter(explode(' ', $canonical), static fn(string $token): bool => $token !== ''));
}

function drug_catalog_non_form_tokens(array $tokens): array {
    $formTokens = ['tab', 'cap', 'syp', 'susp', 'inj', 'crm', 'oint', 'supp'];
    return array_values(array_filter($tokens, static fn(string $token): bool => !in_array($token, $formTokens, true)));
}

function drug_catalog_search_variants(string $query): array {
    $variants = [];
    $raw = trim($query);
    if ($raw !== '') {
        $variants[] = $raw;
    }

    $canonical = drug_catalog_canonical_search_text($query);
    if ($canonical !== '' && !in_array($canonical, $variants, true)) {
        $variants[] = $canonical;
    }

    $tokens = drug_catalog_search_tokens($query);
    $nonFormTokens = drug_catalog_non_form_tokens($tokens);
    if ($nonFormTokens) {
        $firstMeaningful = $nonFormTokens[0];
        if (!in_array($firstMeaningful, $variants, true)) {
            $variants[] = $firstMeaningful;
        }
    }

    return array_values(array_unique(array_filter($variants, static fn(string $item): bool => trim($item) !== '')));
}

function drug_catalog_primary_variant(string $query): string {
    $variants = drug_catalog_search_variants($query);
    if (!$variants) {
        return '';
    }
    $tokens = drug_catalog_non_form_tokens(drug_catalog_search_tokens($query));
    if ($tokens) {
        return $tokens[0];
    }
    return $variants[0];
}

function drug_catalog_matches_word_prefix(string $haystack, string $needle): bool {
    $haystack = drug_catalog_canonical_search_text($haystack);
    $needle = drug_catalog_canonical_search_text($needle);
    if ($haystack === '' || $needle === '') {
        return false;
    }

    return str_starts_with($haystack, $needle) || strpos($haystack, ' ' . $needle) !== false;
}

function drug_catalog_row_fields(array $row): array {
    return [
        (string)($row['pres_new_upper'] ?? ''),
        (string)($row['full_form_brand_name'] ?? ''),
        (string)($row['brand_name'] ?? ''),
        (string)($row['generic_name'] ?? $row['generic'] ?? ''),
    ];
}

function drug_catalog_row_matches_all_tokens(array $row, array $tokens): bool {
    if (!$tokens) {
        return true;
    }

    $haystacks = array_map('drug_catalog_canonical_search_text', drug_catalog_row_fields($row));
    foreach ($tokens as $token) {
        $matched = false;
        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && (str_starts_with($haystack, $token) || strpos($haystack, ' ' . $token) !== false)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            return false;
        }
    }
    return true;
}

function drug_catalog_row_score(array $row, string $query): int {
    $canonicalQuery = drug_catalog_canonical_search_text($query);
    $tokens = drug_catalog_search_tokens($query);
    $nonFormTokens = drug_catalog_non_form_tokens($tokens);
    $fields = drug_catalog_row_fields($row);
    $best = 0;

    foreach ($fields as $index => $field) {
        $canonicalField = drug_catalog_canonical_search_text($field);
        $fieldWeight = 12 - ($index * 2);

        if ($canonicalQuery !== '' && $canonicalField === $canonicalQuery) {
            $best = max($best, 400 + $fieldWeight);
        }
        if ($canonicalQuery !== '' && str_starts_with($canonicalField, $canonicalQuery)) {
            $best = max($best, 360 + $fieldWeight);
        }
        if ($canonicalQuery !== '' && strpos($canonicalField, ' ' . $canonicalQuery) !== false) {
            $best = max($best, 320 + $fieldWeight);
        }
        if ($canonicalQuery !== '' && strpos($canonicalField, $canonicalQuery) !== false) {
            $best = max($best, 180 + $fieldWeight);
        }
    }

    if ($tokens && drug_catalog_row_matches_all_tokens($row, $tokens)) {
        $best = max($best, 340);
    }
    if ($nonFormTokens && drug_catalog_row_matches_all_tokens($row, $nonFormTokens)) {
        $best = max($best, 300);
    }

    return $best;
}

function drug_catalog_fetch_search_rows(PDO $pdo, string $column, string $operator, string $value, int $limit): array {
    $limit = max(1, $limit);
    $sql = "
        SELECT " . drug_catalog_search_select_sql() . "
        " . drug_catalog_from_sql() . "
        WHERE {$column} COLLATE NOCASE {$operator} :query
        ORDER BY CAST(p.manufacturer_preference AS INTEGER) ASC,
                 CAST(p.form_order AS INTEGER) ASC,
                 p.brand_name ASC,
                 p.prescribe_brand_short ASC
        LIMIT {$limit}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['query' => $value]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function drug_catalog_search_brands(PDO $pdo, string $query, int $limit = 50, int $candidateLimit = 120): array {
    $query = trim($query);
    if ($query === '') {
        $stmt = $pdo->prepare(
            "SELECT " . drug_catalog_select_sql() . "
             " . drug_catalog_from_sql() . "
             ORDER BY CAST(p.manufacturer_preference AS INTEGER) ASC,
                      CAST(p.form_order AS INTEGER) ASC,
                      p.prescribe_brand_short ASC
             LIMIT 20"
        );
        $stmt->execute();
        return zimrx_user_drug_merge_search($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], '', $limit);
    }

    $candidateLimit = max(40, min(80, (int)$candidateLimit));
    $rows = [];
    $seen = [];
    $primaryVariant = drug_catalog_primary_variant($query);
    $canonicalQuery = drug_catalog_canonical_search_text($query);
    $prefixColumns = [
        'p.prescribe_brand_short',
        'p.brand_name',
        'p.prescribe_brand_full',
    ];
    $secondaryPrefixColumns = ['p.generic_name'];

    if ($primaryVariant !== '') {
        $qStart = "{$primaryVariant}%";
        foreach ($prefixColumns as $column) {
            foreach (drug_catalog_fetch_search_rows($pdo, $column, 'LIKE', $qStart, $candidateLimit) as $row) {
                $id = (string)($row['brand_id'] ?? $row['id'] ?? '');
                if ($id !== '' && isset($seen[$id])) {
                    continue;
                }
                if (drug_catalog_row_score($row, $query) <= 0) {
                    continue;
                }
                $seen[$id] = true;
                $rows[] = $row;
                if (count($rows) >= $candidateLimit) {
                    break 2;
                }
            }
        }
    }

    if (count($rows) < $limit) {
        $genericVariant = $primaryVariant !== '' ? $primaryVariant : $canonicalQuery;
        if ($genericVariant !== '') {
            $qStart = "{$genericVariant}%";
            foreach ($secondaryPrefixColumns as $column) {
                foreach (drug_catalog_fetch_search_rows($pdo, $column, 'LIKE', $qStart, $candidateLimit) as $row) {
                    $id = (string)($row['brand_id'] ?? $row['id'] ?? '');
                    if ($id !== '' && isset($seen[$id])) {
                        continue;
                    }
                    if (drug_catalog_row_score($row, $query) <= 0) {
                        continue;
                    }
                    $seen[$id] = true;
                    $rows[] = $row;
                    if (count($rows) >= $candidateLimit) {
                        break 2;
                    }
                }
            }
        }
    }

    if (count($rows) < $limit && $primaryVariant !== '') {
        $qLike = "%{$primaryVariant}%";
        foreach (['p.prescribe_brand_short', 'p.brand_name'] as $column) {
            foreach (drug_catalog_fetch_search_rows($pdo, $column, 'LIKE', $qLike, $candidateLimit) as $row) {
                $id = (string)($row['brand_id'] ?? $row['id'] ?? '');
                if ($id !== '' && isset($seen[$id])) {
                    continue;
                }
                if (drug_catalog_row_score($row, $query) <= 0) {
                    continue;
                }
                $seen[$id] = true;
                $rows[] = $row;
                if (count($rows) >= $candidateLimit) {
                    break 2;
                }
            }
        }
    }

    usort($rows, static function (array $a, array $b) use ($query): int {
        $scoreCmp = drug_catalog_row_score($b, $query) <=> drug_catalog_row_score($a, $query);
        if ($scoreCmp !== 0) {
            return $scoreCmp;
        }

        $manCmp = ((int)($a['man_srt'] ?? 0)) <=> ((int)($b['man_srt'] ?? 0));
        if ($manCmp !== 0) {
            return $manCmp;
        }

        $formCmp = ((int)($a['type_srt'] ?? 0)) <=> ((int)($b['type_srt'] ?? 0));
        if ($formCmp !== 0) {
            return $formCmp;
        }

        $brandCmp = strcasecmp((string)($a['brand_name'] ?? ''), (string)($b['brand_name'] ?? ''));
        if ($brandCmp !== 0) {
            return $brandCmp;
        }

        return strcasecmp((string)($a['pres_new_upper'] ?? ''), (string)($b['pres_new_upper'] ?? ''));
    });

    return zimrx_user_drug_merge_search($rows, $query, $limit);
}

function drug_catalog_fetch_brand(PDO $pdo, string $brandId): ?array {
    $userRow = zimrx_user_drug_fetch_index($brandId);
    if ($userRow) {
        return zimrx_user_drug_index_to_catalog_row($userRow);
    }

    $hidden = zimrx_user_drug_hidden_ids();
    if (isset($hidden[(string)$brandId])) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT " . drug_catalog_select_sql() . "
         " . drug_catalog_from_sql() . "
         WHERE CAST(p.brand_id AS TEXT) = CAST(:brand_id AS TEXT)
         LIMIT 1"
    );
    $stmt->execute(['brand_id' => $brandId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function drug_catalog_fetch_generic_ids_for_class(PDO $pdo, string $className): array {
    $stmt = $pdo->prepare(
        "SELECT generic_ids
         FROM drug_therapeutic_class tc
         WHERE tc.class_name = :class_name OR tc.class_id = :class_name
         LIMIT 1"
    );
    $stmt->execute(['class_name' => $className]);
    $genericIds = (string)($stmt->fetchColumn() ?: '');
    return array_values(array_filter(array_map('trim', explode(',', $genericIds))));
}

function drug_catalog_fetch_generic_ids_for_indication(PDO $pdo, string $indicationId): array {
    $stmt = $pdo->prepare(
        "SELECT generic_ids
         FROM drug_indication
         WHERE indication_id = :indication_id
         LIMIT 1"
    );
    $stmt->execute(['indication_id' => $indicationId]);
    $genericIds = (string)($stmt->fetchColumn() ?: '');
    return array_values(array_filter(array_map('trim', explode(',', $genericIds))));
}

function drug_catalog_placeholder_list(array $values): string {
    return implode(', ', array_fill(0, count($values), '?'));
}
