<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/rx_regimen_lib.php';

function template_table_for_type(string $type): string {
    return match ($type) {
        'prescription' => 'zimrx_prescription_template',
        'treatment'    => 'zimrx_regimen_template',
        default        => 'zimrx_drug_template',
    };
}

try {
    $userPdo = rx_user_pdo();
    $type = rx_clean($_GET['type'] ?? 'drug');
    $table = template_table_for_type($type);
    if (!rx_table_exists($userPdo, $table)) {
        rx_json([]);
    }
    $doctorId = current_user_doctor_id();

    $groupId = rx_clean($_GET['id'] ?? '');
    if ($groupId !== '') {
        $stmt = $userPdo->prepare(
            "SELECT template_group_id, template_name, COUNT(*) AS row_count, MAX(usage_count) AS usage_count
             FROM {$table}
             WHERE template_group_id = :group_id
               AND doctor_id = :doctor_id
             GROUP BY template_group_id, template_name
             LIMIT 1"
        );
        $stmt->execute(['group_id' => $groupId, 'doctor_id' => $doctorId]);
        $template = $stmt->fetch();
        if (!$template) {
            rx_json(['error' => 'Template not found.']);
        }

        $rowsStmt = $userPdo->prepare(
            "SELECT item_order, item_type, brand_id, generic_id, brand_name,
                    generic_name, strength, form, dose, instruction, duration,
                    section, content, content_json
             FROM {$table}
             WHERE template_group_id = :group_id
               AND doctor_id = :doctor_id
             ORDER BY item_order ASC, id ASC"
        );
        $rowsStmt->execute(['group_id' => $groupId, 'doctor_id' => $doctorId]);

        $userPdo->prepare(
            "UPDATE {$table}
             SET usage_count = usage_count + 1, updated_at = CURRENT_TIMESTAMP
             WHERE template_group_id = :group_id AND doctor_id = :doctor_id"
        )->execute(['group_id' => $groupId, 'doctor_id' => $doctorId]);

        $rows = array_map(function ($row) {
            $master = rx_resolve_master(null, $row['brand_id'] ?? null, $row['generic_id'] ?? null);
            $row['catalog_id'] = $master ? rx_clean($master['id']) : '';
            return $row;
        }, $rowsStmt->fetchAll());

        rx_json([
            'template' => $template,
            'rows' => $rows,
        ]);
    }

    $query = rx_clean($_GET['q'] ?? '');
    $stmt = $userPdo->prepare(
        "SELECT template_group_id AS id, template_name AS name, COUNT(*) AS row_count, MAX(usage_count) AS usage_count
         FROM {$table}
         WHERE doctor_id = :doctor_id
           AND (:q = '' OR template_name LIKE :q_like)
         GROUP BY template_group_id, template_name
         ORDER BY
           CASE WHEN template_name LIKE :q_start THEN 0 ELSE 1 END,
           usage_count DESC,
           template_name ASC
         LIMIT 30"
    );
    $stmt->execute([
        'doctor_id' => $doctorId,
        'q' => $query,
        'q_like' => '%' . $query . '%',
        'q_start' => $query . '%',
    ]);

    rx_json($stmt->fetchAll());
} catch (Exception $e) {
    rx_json(['error' => $e->getMessage()]);
}
