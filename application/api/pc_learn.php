<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/rx_regimen_lib.php';
require_once __DIR__ . '/pc_catalog_lib.php';

function pc_is_valid_complaint_term(string $term): bool {
    $clean = trim($term);
    if (mb_strlen($clean, 'UTF-8') < 2) {
        return false;
    }
    // Must contain at least 2 alphanumeric characters
    return (bool)preg_match('/[\p{L}\p{N}].*[\p{L}\p{N}]/u', $clean);
}

try {
    header('Content-Type: application/json');
    $userPdo = rx_user_pdo();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rx_json(['error' => 'POST required.']);
    }

    if (!rx_table_exists($userPdo, 'zimrx_user_pc')) {
        rx_json(['learned' => 0, 'skipped' => 0]);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    $complaints = is_array($payload['complaints'] ?? null) ? $payload['complaints'] : [];
    $doctorId = current_user_doctor_id();

    $stmt = $userPdo->prepare(
        "INSERT INTO zimrx_user_pc (
            doctor_id, category, term, source, usage_count, created_at, updated_at
        ) VALUES (
            :doctor_id, :category, :term, :source, 1, " . DbSql::now() . ", " . DbSql::now() . "
        )
        " . DbSql::upsert(
            'doctor_id, category, term',
            ['usage_count', 'updated_at'],
            [
                'usage_count' => 'zimrx_user_pc.usage_count + 1',
                'updated_at' => DbSql::now()
            ],
            'zimrx_user_pc'
        )
    );

    $learned = 0;
    $skipped = 0;
    $seen = [];

    $learn = function (string $category, string $value, string $source = 'system') use (&$stmt, &$learned, &$seen, $doctorId) {
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        if ($value === '') {
            return;
        }

        $key = rx_norm(implode('|', [$doctorId, $category, $value]));
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;

        $stmt->execute([
            'doctor_id' => $doctorId,
            'category' => $category,
            'term' => $value,
            'source' => $source,
        ]);
        $learned++;
    };

    foreach ($complaints as $row) {
        if (!is_array($row)) {
            $skipped++;
            continue;
        }

        $complaint = rx_clean($row['complaint'] ?? '');
        $duration = rx_clean($row['duration'] ?? '');
        $unit = rx_clean($row['unit'] ?? '');

        if ($complaint === '' && $duration === '' && $unit === '') {
            $skipped++;
            continue;
        }

        if ($complaint !== '' && pc_is_valid_complaint_term($complaint)) {
            $source = pc_static_term_exists($complaint) ? 'system' : 'user';
            $learn('PC', $complaint, $source);
        }
        if ($duration !== '') {
            $source = in_array($duration, pc_duration_defaults(), true) ? 'system' : 'user';
            $learn('pc_duration', $duration, $source);
        }
        if ($unit !== '') {
            $source = in_array($unit, pc_unit_defaults(), true) ? 'system' : 'user';
            $learn('pc_unit', $unit, $source);
        }
    }

    rx_json(['learned' => $learned, 'skipped' => $skipped]);
} catch (Exception $e) {
    rx_json(['error' => $e->getMessage()]);
}
