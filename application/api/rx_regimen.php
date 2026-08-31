<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/rx_regimen_lib.php';

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

function rx_regimen_instruction_row(array $context, array $instruction): array {
    return [
        'dose' => '',
        'instruction' => rx_clean($instruction['instruction_bn'] ?? ''),
        'duration' => '',
        'brand_id' => $context['brand_id'] ?? '',
        'catalog_id' => $context['catalog_id'] ?? '',
        'generic_id' => $context['generic_id'] ?? '',
        'brand_name' => $context['brand_name'] ?? '',
        'generic_name' => $context['generic_name'] ?? '',
        'strength' => $context['strength'] ?? '',
        'form' => $context['form'] ?? '',
    ];
}

function rx_regimen_with_instruction_default(array $rows, array $context, ?array $defaultInstruction): array {
    if (!$defaultInstruction || rx_clean($defaultInstruction['instruction_bn'] ?? '') === '') {
        return $rows;
    }

    $instruction = rx_clean($defaultInstruction['instruction_bn'] ?? '');
    $anotherRow = (int)($defaultInstruction['default_instruction_in_another_row'] ?? 0) === 1;

    if (!$rows) {
        if ($anotherRow) {
            return [
                [
                    'dose' => '',
                    'instruction' => '',
                    'duration' => '',
                    'brand_id' => $context['brand_id'] ?? '',
                    'catalog_id' => $context['catalog_id'] ?? '',
                    'generic_id' => $context['generic_id'] ?? '',
                    'brand_name' => $context['brand_name'] ?? '',
                    'generic_name' => $context['generic_name'] ?? '',
                    'strength' => $context['strength'] ?? '',
                    'form' => $context['form'] ?? '',
                ],
                rx_regimen_instruction_row($context, $defaultInstruction),
            ];
        }
        return [rx_regimen_instruction_row($context, $defaultInstruction)];
    }

    if ($anotherRow) {
        foreach ($rows as &$row) {
            if (rx_norm($row['instruction'] ?? '') === rx_norm($instruction)) {
                $row['instruction'] = '';
            }
        }
        unset($row);
        $rows[] = rx_regimen_instruction_row($context, $defaultInstruction);
        return $rows;
    }

    if (rx_clean($rows[0]['instruction'] ?? '') === '') {
        $rows[0]['instruction'] = $instruction;
    }
    return $rows;
}

function rx_regimen_system_template_rows(PDO $systemPdo, array $context, string $strengthNorm, string $formNorm): array {
    if (!rx_table_exists($systemPdo, 'drug_template') || rx_clean($context['generic_id'] ?? '') === '') {
        return [];
    }

    $stmt = $systemPdo->prepare(
        "SELECT
            '' AS brand_id,
            :catalog_id AS catalog_id,
            CAST(generic_id AS TEXT) AS generic_id,
            generic_name,
            strength,
            std_form AS form,
            COALESCE(NULLIF(dose_digit_bn, ''), dose_text_bn) AS dose,
            instruction_bn AS instruction,
            duration_bn AS duration
         FROM drug_template
         WHERE CAST(generic_id AS TEXT) = CAST(:generic_id AS TEXT)
           AND (:strength_norm = '' OR REPLACE(LOWER(strength), ' ', '') = :strength_norm)
           AND (:form_norm = '' OR REPLACE(REPLACE(LOWER(std_form), ' ', ''), '.', '') = :form_norm)
         ORDER BY \"row\" ASC"
    );
    $stmt->execute([
        'catalog_id' => $context['catalog_id'] ?? '',
        'generic_id' => $context['generic_id'],
        'strength_norm' => $strengthNorm,
        'form_norm' => $formNorm,
    ]);

    return $stmt->fetchAll() ?: [];
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
    $defaultInstruction = rx_instruction_default_for_form($context['form'], $doctorId);

    if ($genericId !== '' && $hasSystemTemplate) {
        $templateRows = rx_regimen_system_template_rows($systemPdo, $context, $strengthNorm, $formNorm);
        if ($templateRows) {
            rx_json(rx_regimen_response(
                rx_regimen_with_instruction_default($templateRows, $context, $defaultInstruction),
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
            rx_json(rx_regimen_response(
                rx_regimen_with_instruction_default([$row], $context, $defaultInstruction),
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
                rx_json(rx_regimen_response(
                    rx_regimen_with_instruction_default([$row], $context, $defaultInstruction),
                    'learned_generic'
                ));
            }
        }
    }

    if ($defaultInstruction) {
        rx_json(rx_regimen_response(
            rx_regimen_with_instruction_default([], $context, $defaultInstruction),
            'instruction_template'
        ));
    }

    rx_json(['found' => false]);
} catch (Exception $e) {
    rx_json(['error' => $e->getMessage()]);
}
