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
            doctor_id, category, term, usage_count, created_at, updated_at
        ) VALUES (
            :doctor_id, :category, :term, 1, " . DbSql::now() . ", " . DbSql::now() . "
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

    $learn = function (string $category, string $value) use (&$stmt, &$learned, &$seen, $doctorId) {
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
            $learn('PC', $complaint);
            // If this complaint does not exist in the pre-seeded system catalog, auto-register as custom P/C
            if (!pc_static_term_exists($complaint)) {
                $learn('custom', $complaint);
            }
        }
        if ($duration !== '') {
            $learn('pc_duration', $duration);
            if (!in_array($duration, pc_duration_defaults(), true)) {
                $learn('custom_duration', $duration);
            }
        }
        if ($unit !== '') {
            $learn('pc_unit', $unit);
        }
    }

    rx_json(['learned' => $learned, 'skipped' => $skipped]);
} catch (Exception $e) {
    rx_json(['error' => $e->getMessage()]);
}
