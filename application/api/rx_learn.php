<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/rx_regimen_lib.php';
require_once __DIR__ . '/rx_template_lib.php';

try {
    header('Content-Type: application/json');
    $userPdo = rx_user_pdo();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rx_json(['error' => 'POST required.']);
    }

    if (!rx_table_exists($userPdo, 'zimrx_user_drugs')) {
        rx_json(['learned' => 0, 'skipped' => 0]);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    $drugs = is_array($payload['drugs'] ?? null) ? $payload['drugs'] : [];
    $doctorId = current_user_doctor_id();

    $learned = 0;
    $skipped = 0;
    $stmt = $userPdo->prepare(
        "INSERT INTO zimrx_user_drug (
            doctor_id, brand_id, generic_id, brand_name, generic_name, strength,
            form, dose, instruction, duration, normalized_key, use_count,
            last_used_at, updated_at
        ) VALUES (
            :doctor_id, :brand_id, :generic_id, :brand_name, :generic_name, :strength,
            :form, :dose, :instruction, :duration, :normalized_key, 1,
            " . DbSql::now() . ", " . DbSql::now() . "
        )
        " . DbSql::upsert(
            'doctor_id, normalized_key',
            ['doctor_id', 'brand_id', 'generic_id', 'brand_name', 'generic_name', 'strength', 'form', 'dose', 'instruction', 'duration', 'use_count', 'last_used_at', 'updated_at'],
            [
                'use_count' => 'zimrx_user_drug.use_count + 1',
                'last_used_at' => DbSql::now(),
                'updated_at' => DbSql::now()
            ],
            'zimrx_user_drugs'
        )
    );
    $instructionTable = rx_instruction_usage_table($userPdo);
    $selectInstructionByStaticStmt = $userPdo->prepare(
        "SELECT id, usage_count FROM {$instructionTable}
         WHERE doctor_id = :doctor_id AND static_id = :static_id
         LIMIT 1"
    );
    $selectInstructionCustomStmt = $userPdo->prepare(
        "SELECT id, usage_count FROM {$instructionTable}
         WHERE doctor_id = :doctor_id
           AND (static_id IS NULL OR static_id = 0)
           AND instruction_bn = :instruction_bn
         LIMIT 1"
    );
    $updateInstructionStmt = $userPdo->prepare(
        "UPDATE {$instructionTable}
         SET usage_count = usage_count + 1,
             updated_at = " . DbSql::now() . "
         WHERE id = :id"
    );
    $insertInstructionStmt = $userPdo->prepare(
        "INSERT INTO {$instructionTable} (
            usage_count, static_id, doctor_id, instruction_en, instruction_bn, search_alias,
            is_pinned, is_hidden, sort_order, default_dosage_form, default_instruction_in_another_row,
            is_edited, created_at, updated_at
        ) VALUES (
            1, :static_id, :doctor_id, :instruction_en, :instruction_bn, :search_alias,
            0, 0, :sort_order, :default_dosage_form, :default_instruction_in_another_row,
            :is_edited, " . DbSql::now() . ", " . DbSql::now() . "
        )"
    );

    foreach ($drugs as $drug) {
        if (!is_array($drug)) {
            $skipped++;
            continue;
        }

        $dose = rx_clean($drug['dose'] ?? '');
        $instruction = rx_clean($drug['instruction'] ?? '');
        $duration = rx_clean($drug['duration'] ?? '');
        if ($dose === '' && $instruction === '' && $duration === '') {
            $skipped++;
            continue;
        }

        $context = rx_context_from_request($drug);
        $drugKey = $context['brand_id'];
        if ($drugKey === '') {
            $skipped++;
            continue;
        }

        $normalizedKey = implode('|', [
            'doctor:' . $doctorId,
            $drugKey,
            rx_norm($dose),
            rx_norm($instruction),
            rx_norm($duration),
        ]);

        $stmt->execute([
            'doctor_id' => $doctorId,
            'brand_id' => $context['brand_id'],
            'generic_id' => $context['generic_id'],
            'brand_name' => $context['brand_name'] ?: rx_clean($drug['brand'] ?? ''),
            'generic_name' => $context['generic_name'] ?: rx_clean($drug['generic'] ?? ''),
            'strength' => $context['strength'],
            'form' => $context['form'],
            'dose' => $dose,
            'instruction' => $instruction,
            'duration' => $duration,
            'normalized_key' => $normalizedKey,
        ]);
        if ($dose !== '') {
            rx_phrase_learn('dose', $dose, $doctorId);
        }
        if ($instruction !== '') {
            $staticInstruction = rx_find_static_instruction_match($instruction);
            if ($staticInstruction) {
                $selectInstructionByStaticStmt->execute([
                    'doctor_id' => $doctorId,
                    'static_id' => (int)$staticInstruction['id'],
                ]);
                $existingInstruction = $selectInstructionByStaticStmt->fetch();
                if ($existingInstruction) {
                    $updateInstructionStmt->execute(['id' => (int)$existingInstruction['id']]);
                } else {
                    $insertInstructionStmt->execute([
                        'static_id' => (int)$staticInstruction['id'],
                        'doctor_id' => $doctorId,
                        'instruction_en' => rx_clean($staticInstruction['instruction_en'] ?? ''),
                        'instruction_bn' => rx_clean($staticInstruction['instruction_bn'] ?? ''),
                        'search_alias' => rx_clean($staticInstruction['search_alias'] ?? ''),
                        'sort_order' => (int)($staticInstruction['sort_order'] ?? 0),
                        'default_dosage_form' => rx_clean($staticInstruction['default_dosage_form'] ?? '[]'),
                        'default_instruction_in_another_row' => (int)($staticInstruction['default_instruction_in_another_row'] ?? 0) === 1 ? 1 : 0,
                        'is_edited' => 0,
                    ]);
                }
            } else {
                $selectInstructionCustomStmt->execute([
                    'doctor_id' => $doctorId,
                    'instruction_bn' => $instruction,
                ]);
                $existingInstruction = $selectInstructionCustomStmt->fetch();
                if ($existingInstruction) {
                    $updateInstructionStmt->execute(['id' => (int)$existingInstruction['id']]);
                } else {
                    $insertInstructionStmt->execute([
                        'static_id' => null,
                        'doctor_id' => $doctorId,
                        'instruction_en' => '',
                        'instruction_bn' => $instruction,
                        'search_alias' => '',
                        'sort_order' => 0,
                        'default_dosage_form' => '[]',
                        'default_instruction_in_another_row' => 0,
                        'is_edited' => 1,
                    ]);
                }
            }
        }
        if ($duration !== '') {
            rx_phrase_learn('duration', $duration, $doctorId);
        }
        $learned++;
    }

    rx_json(['learned' => $learned, 'skipped' => $skipped]);
} catch (Exception $e) {
    rx_json(['error' => $e->getMessage()]);
}
