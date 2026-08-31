<?php
// Resolves default and learned medication regimens based on generic templates, doctor history, and route constraints.
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/rx_regimen_lib.php';
require_once ZIMRX_BASE_DIR . '/lib/rx_template_lib.php';

function rx_regimen_local_route_context(array $context): bool {
    $text = rx_norm(
        ($context['form'] ?? '') . ' ' .
        ($context['generic_name'] ?? '') . ' ' .
        ($context['brand_name'] ?? '')
    );

    foreach ([
        'vaginal', 'rectal', 'topical', 'cream', 'ointment', 'gel',
        'eye', 'ear', 'nasal', 'inhaler', 'inhalation', 'mouthwash',
        'gargle', 'spray', 'drops', 'suppository',
    ] as $needle) {
        if (str_contains($text, $needle)) {
            return true;
        }
    }

    return false;
}

function rx_regimen_has_oral_food_timing(array $row): bool {
    $instruction = rx_norm($row['instruction'] ?? '');
    if ($instruction === '') {
        return false;
    }

    foreach ([
        'নাস্তা', 'খাবার', 'খাওয়ার', 'খাওয়ার', 'আহার',
        'before meal', 'after meal', 'before food', 'after food',
        'empty stomach', 'খালি পেটে', 'ভরা পেটে',
    ] as $needle) {
        if (str_contains($instruction, rx_norm($needle))) {
            return true;
        }
    }

    return false;
}

function rx_regimen_should_skip_learned_row(array $row, array $context): bool {
    return rx_regimen_local_route_context($context) && rx_regimen_has_oral_food_timing($row);
}

function rx_regimen_response(array $rows, string $source): array {
    $payloadRows = array_map('rx_regimen_payload', $rows);
    return [
        'found' => count($payloadRows) > 0,
        'source' => $source,
        'regimen' => $payloadRows[0] ?? null,
        'regimen_rows' => $payloadRows,
    ];
}

function rx_regimen_instruction_row(array $context, array $instruction, string $preferredLang = 'bn'): array {
    $bn = rx_clean($instruction['instruction_bn'] ?? '');
    $en = rx_clean($instruction['instruction_en'] ?? '');
    $display = ($preferredLang === 'en') ? ($en !== '' ? $en : $bn) : ($bn !== '' ? $bn : $en);

    return [
        'dose' => '',
        'dose_bn' => '',
        'dose_en' => '',
        'instruction' => $display,
        'instruction_bn' => $bn,
        'instruction_en' => $en,
        'duration' => '',
        'duration_bn' => '',
        'duration_en' => '',
        'brand_id' => $context['brand_id'] ?? '',
        'catalog_id' => $context['catalog_id'] ?? '',
        'generic_id' => $context['generic_id'] ?? '',
        'brand_name' => $context['brand_name'] ?? '',
        'generic_name' => $context['generic_name'] ?? '',
        'strength' => $context['strength'] ?? '',
        'form' => $context['form'] ?? '',
    ];
}

function rx_regimen_with_instruction_default(array $rows, array $context, ?array $defaultInstruction, string $preferredLang = 'bn'): array {
    if (!$defaultInstruction) {
        return $rows;
    }

    $bn = rx_clean($defaultInstruction['instruction_bn'] ?? '');
    $en = rx_clean($defaultInstruction['instruction_en'] ?? '');
    $instruction = ($preferredLang === 'en') ? ($en !== '' ? $en : $bn) : ($bn !== '' ? $bn : $en);
    if ($instruction === '') {
        return $rows;
    }

    $anotherRow = (int)($defaultInstruction['default_instruction_in_another_row'] ?? 0) === 1;

    if (!$rows) {
        if ($anotherRow) {
            return [
                [
                    'dose' => '',
                    'dose_bn' => '',
                    'dose_en' => '',
                    'instruction' => '',
                    'instruction_bn' => '',
                    'instruction_en' => '',
                    'duration' => '',
                    'duration_bn' => '',
                    'duration_en' => '',
                    'brand_id' => $context['brand_id'] ?? '',
                    'catalog_id' => $context['catalog_id'] ?? '',
                    'generic_id' => $context['generic_id'] ?? '',
                    'brand_name' => $context['brand_name'] ?? '',
                    'generic_name' => $context['generic_name'] ?? '',
                    'strength' => $context['strength'] ?? '',
                    'form' => $context['form'] ?? '',
                ],
                rx_regimen_instruction_row($context, $defaultInstruction, $preferredLang),
            ];
        }
        return [rx_regimen_instruction_row($context, $defaultInstruction, $preferredLang)];
    }

    if ($anotherRow) {
        foreach ($rows as &$row) {
            if (rx_norm($row['instruction'] ?? '') === rx_norm($instruction) || rx_norm($row['instruction_bn'] ?? '') === rx_norm($bn) || rx_norm($row['instruction_en'] ?? '') === rx_norm($en)) {
                $row['instruction'] = '';
            }
        }
        unset($row);
        $rows[] = rx_regimen_instruction_row($context, $defaultInstruction, $preferredLang);
        return $rows;
    }

    if (rx_clean($rows[0]['instruction'] ?? '') === '') {
        $rows[0]['instruction'] = $instruction;
        $rows[0]['instruction_bn'] = $bn;
        $rows[0]['instruction_en'] = $en;
    }
    return $rows;
}

function rx_regimen_system_template_rows(PDO $systemPdo, array $context, string $strengthNorm, string $formNorm, string $preferredLang = 'bn'): array {
    if (!rx_table_exists($systemPdo, 'drug_template') || rx_clean($context['generic_id'] ?? '') === '') {
        return [];
    }

    $stmt = $systemPdo->prepare(
        "SELECT
            generic_id,
            generic_name,
            strength,
            form,
            lang,
            COALESCE(NULLIF(dose_digit, ''), dose_text) AS dose,
            dose_digit,
            dose_text,
            instruction,
            duration,
            \"row\"
         FROM drug_template
         WHERE CAST(generic_id AS TEXT) = CAST(:generic_id AS TEXT)
           AND (:strength_norm = '' OR REPLACE(LOWER(strength), ' ', '') = :strength_norm)
           AND (:form_norm = '' OR REPLACE(REPLACE(LOWER(form), ' ', ''), '.', '') = :form_norm)
           AND (NULLIF(dose_digit, '') IS NOT NULL OR NULLIF(dose_text, '') IS NOT NULL OR NULLIF(instruction, '') IS NOT NULL)
         ORDER BY \"row\" ASC, CASE WHEN lang = :preferred_lang THEN 0 ELSE 1 END"
    );
    $stmt->execute([
        'generic_id' => $context['generic_id'],
        'strength_norm' => $strengthNorm,
        'form_norm' => $formNorm,
        'preferred_lang' => $preferredLang,
    ]);

    $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$rawRows) {
        return [];
    }

    $grouped = [];
    foreach ($rawRows as $r) {
        $rowKey = (int)$r['row'];
        $lang = $r['lang'] ?? 'en';
        if (!isset($grouped[$rowKey])) {
            $grouped[$rowKey] = [
                'primary' => null,
                'bn' => null,
                'en' => null,
            ];
        }
        if ($lang === 'bn') {
            $grouped[$rowKey]['bn'] = $r;
        } elseif ($lang === 'en') {
            $grouped[$rowKey]['en'] = $r;
        }
        if ($grouped[$rowKey]['primary'] === null && $lang === $preferredLang) {
            $grouped[$rowKey]['primary'] = $r;
        }
    }

    $catalogId = $context['catalog_id'] ?? '';
    $finalRows = [];
    foreach ($grouped as $rowNum => $entry) {
        $p = $entry['primary'] ?? ($preferredLang === 'en' ? ($entry['en'] ?? $entry['bn']) : ($entry['bn'] ?? $entry['en']));
        if (!$p) continue;

        $bn = $entry['bn'] ?? [];
        $en = $entry['en'] ?? [];

        $finalRows[] = [
            'brand_id' => '',
            'catalog_id' => $catalogId,
            'generic_id' => (string)$p['generic_id'],
            'generic_name' => (string)$p['generic_name'],
            'strength' => (string)$p['strength'],
            'form' => (string)$p['form'],
            'dose' => (string)$p['dose'],
            'dose_bn' => (string)($bn['dose'] ?? ''),
            'dose_en' => (string)($en['dose'] ?? ''),
            'instruction' => (string)$p['instruction'],
            'instruction_bn' => (string)($bn['instruction'] ?? ''),
            'instruction_en' => (string)($en['instruction'] ?? ''),
            'duration' => (string)$p['duration'],
            'duration_bn' => (string)($bn['duration'] ?? ''),
            'duration_en' => (string)($en['duration'] ?? ''),
        ];
    }

    return $finalRows;
}

function rx_enrich_learned_row_language(array $row, string $preferredLang): array {
    if (empty($row['dose_bn']) || empty($row['dose_en'])) {
        $doseVal = rx_clean($row['dose'] ?? '');
        if ($doseVal !== '' && function_exists('rx_phrase_find_static_match')) {
            $m = rx_phrase_find_static_match('dose', $doseVal);
            if ($m) {
                $row['dose_bn'] = $m['value_bn'] ?? ($m['dosage_bn'] ?? '');
                $row['dose_en'] = $m['value_en'] ?? ($m['dosage_en'] ?? '');
            }
        }
    }
    if (!empty($row['dose_en']) && !empty($row['dose_bn'])) {
        $row['dose'] = ($preferredLang === 'en') ? $row['dose_en'] : $row['dose_bn'];
    }

    if (empty($row['instruction_bn']) || empty($row['instruction_en'])) {
        $instVal = rx_clean($row['instruction'] ?? '');
        if ($instVal !== '' && function_exists('rx_find_static_instruction_match')) {
            $m = rx_find_static_instruction_match($instVal);
            if ($m) {
                $row['instruction_bn'] = $m['instruction_bn'] ?? '';
                $row['instruction_en'] = $m['instruction_en'] ?? '';
            }
        }
    }
    if (!empty($row['instruction_en']) && !empty($row['instruction_bn'])) {
        $row['instruction'] = ($preferredLang === 'en') ? $row['instruction_en'] : $row['instruction_bn'];
    }

    if (empty($row['duration_bn']) || empty($row['duration_en'])) {
        $durVal = rx_clean($row['duration'] ?? '');
        if ($durVal !== '' && function_exists('rx_phrase_find_static_match')) {
            $m = rx_phrase_find_static_match('duration', $durVal);
            if ($m) {
                $row['duration_bn'] = $m['value_bn'] ?? ($m['duration_bn'] ?? '');
                $row['duration_en'] = $m['value_en'] ?? ($m['duration_en'] ?? '');
            }
        }
    }
    if (!empty($row['duration_en']) && !empty($row['duration_bn'])) {
        $row['duration'] = ($preferredLang === 'en') ? $row['duration_en'] : $row['duration_bn'];
    }

    return $row;
}

try {
    $userPdo = rx_user_pdo();
    $systemPdo = rx_system_pdo();
    $hasUserDrug = rx_table_exists($userPdo, 'zimrx_user_drugs');
    $hasSystemTemplate = rx_table_exists($systemPdo, 'drug_template');

    $context = rx_context_from_request($_GET);
    $brandId = $context['brand_id'];
    $genericId = $context['generic_id'];
    $strengthNorm = rx_norm_compact($context['strength']);
    $formNorm = rx_norm_compact($context['form']);
    $doctorId = current_user_doctor_id();
    $preferredLang = function_exists('zimrx_clinical_language')
        ? zimrx_clinical_language($userPdo, $doctorId, $_GET['lang'] ?? null)
        : (rx_clean($_GET['lang'] ?? '') ?: 'en');
    $defaultInstruction = rx_instruction_default_for_form($context['form'], $doctorId, $preferredLang);

    if ($genericId !== '' && $hasSystemTemplate) {
        $templateRows = rx_regimen_system_template_rows($systemPdo, $context, $strengthNorm, $formNorm, $preferredLang);
        if ($templateRows) {
            rx_json(rx_regimen_response(
                rx_regimen_with_instruction_default($templateRows, $context, $defaultInstruction, $preferredLang),
                'drug_template'
            ));
        }
    }

    $exactParams = ['brand_id' => $brandId, 'doctor_id' => $doctorId];

    if ($hasUserDrug) {
        $stmt = $userPdo->prepare(
            "SELECT *
             FROM zimrx_user_drug
             WHERE brand_id <> '' AND brand_id = :brand_id
               AND doctor_id = :doctor_id
             ORDER BY use_count DESC, datetime(COALESCE(last_used_at, updated_at, created_at)) DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute($exactParams);
        $row = $stmt->fetch();
        if ($row && !rx_regimen_should_skip_learned_row($row, $context)) {
            $enriched = rx_enrich_learned_row_language($row, $preferredLang);
            rx_json(rx_regimen_response(
                rx_regimen_with_instruction_default([$enriched], $context, $defaultInstruction, $preferredLang),
                'learned_drug'
            ));
        }
    }

    if ($genericId !== '') {
        $genericParams = [
            'doctor_id' => $doctorId,
            'generic_id' => $genericId,
            'strength_norm' => $strengthNorm,
            'form_norm' => $formNorm,
        ];

        if ($hasUserDrug) {
            $stmt = $userPdo->prepare(
                "SELECT *
                 FROM zimrx_user_drug
                 WHERE generic_id = :generic_id
                   AND doctor_id = :doctor_id
                   AND (:strength_norm = '' OR REPLACE(LOWER(strength), ' ', '') = :strength_norm)
                   AND (:form_norm = '' OR REPLACE(REPLACE(LOWER(form), ' ', ''), '.', '') = :form_norm)
                 ORDER BY use_count DESC, datetime(COALESCE(last_used_at, updated_at, created_at)) DESC, id DESC
                 LIMIT 1"
            );
            $stmt->execute($genericParams);
            $row = $stmt->fetch();
            if ($row && !rx_regimen_should_skip_learned_row($row, $context)) {
                $enriched = rx_enrich_learned_row_language($row, $preferredLang);
                rx_json(rx_regimen_response(
                    rx_regimen_with_instruction_default([$enriched], $context, $defaultInstruction, $preferredLang),
                    'learned_generic'
                ));
            }
        }
    }

    if ($defaultInstruction) {
        rx_json(rx_regimen_response(
            rx_regimen_with_instruction_default([], $context, $defaultInstruction, $preferredLang),
            'instruction_template'
        ));
    }

    rx_json(['found' => false]);
} catch (Throwable $e) {
    error_log('[ZimRx] rx_regimen error: ' . $e->getMessage());
    rx_json(['error' => 'An internal error occurred. Please try again.']);
}
