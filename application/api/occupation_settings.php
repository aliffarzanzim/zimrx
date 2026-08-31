<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../particulars_audit_lib.php';

function respond(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo_static = DbConnections::staticDb();
    $pdo_user = DbConnections::userdata();

    $doctorId = function_exists('current_user_doctor_id') ? current_user_doctor_id() : (int)($_SESSION['doctor_id'] ?? 1);
    if ($doctorId <= 0) $doctorId = 1;

    // Support both application/json and application/x-www-form-urlencoded
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    if (!is_array($jsonInput)) {
        $jsonInput = [];
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? $jsonInput['action'] ?? 'list';
    $name = trim((string)($_POST['name'] ?? $jsonInput['name'] ?? ''));
    $newName = trim((string)($_POST['new_name'] ?? $jsonInput['new_name'] ?? ''));

    if ($action === 'list') {
        $includeHidden = isset($_GET['include_hidden']) ? (int)$_GET['include_hidden'] === 1 : true;

        // 1. Load static occupations map (case-insensitive name => static_id)
        $staticMap = [];
        $stmtStatic = $pdo_static->query("SELECT id, name FROM zimrx_static_occupations ORDER BY name ASC");
        $staticRows = $stmtStatic->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($staticRows as $sr) {
            $staticMap[strtolower(trim((string)$sr['name']))] = [
                'static_id' => (int)$sr['id'],
                'name' => trim((string)$sr['name']),
            ];
        }

        // 2. Load user records from zimrx_user_occupations for this doctor
        $userMap = [];
        $stmtUser = $pdo_user->prepare(
            "SELECT id, name, usage_count, is_pinned, is_hidden, sort_order
             FROM zimrx_user_occupations
             WHERE doctor_id = :doctor_id"
        );
        $stmtUser->execute(['doctor_id' => $doctorId]);
        while ($r = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
            $key = strtolower(trim((string)($r['name'] ?? '')));
            if ($key !== '') {
                $userMap[$key] = $r;
            }
        }

        $allKeys = [];
        foreach ($staticMap as $key => $sData) {
            $allKeys[$key] = true;
        }
        foreach ($userMap as $key => $uData) {
            $allKeys[$key] = true;
        }

        $items = [];
        foreach ($allKeys as $key => $_) {
            $isSystem = isset($staticMap[$key]);
            $u = $userMap[$key] ?? null;
            $displayName = $u ? trim((string)$u['name']) : $staticMap[$key]['name'];
            
            $isPinned = $u ? (int)$u['is_pinned'] : 0;
            $isHidden = $u ? (int)$u['is_hidden'] : 0;
            $usageCount = $u ? (int)$u['usage_count'] : 0;
            $sortOrder = $u ? (int)($u['sort_order'] ?? 0) : 0;
            $id = $u ? (int)$u['id'] : ('s_' . $staticMap[$key]['static_id']);

            if (!$includeHidden && $isHidden === 1) {
                continue;
            }

            $items[] = [
                'id' => $id,
                'name' => $displayName,
                'usage_count' => $usageCount,
                'is_pinned' => $isPinned,
                'is_hidden' => $isHidden,
                'sort_order' => $sortOrder,
                'kind' => $isSystem ? 'system' : 'custom',
            ];
        }

        // Sort: is_pinned DESC, (sort_order > 0 ? sort_order : 99999) ASC, usage_count DESC, name ASC
        usort($items, function($a, $b) {
            if ($a['is_pinned'] !== $b['is_pinned']) {
                return $b['is_pinned'] <=> $a['is_pinned'];
            }
            $soA = $a['sort_order'] > 0 ? $a['sort_order'] : 999999;
            $soB = $b['sort_order'] > 0 ? $b['sort_order'] : 999999;
            if ($soA !== $soB) {
                return $soA <=> $soB;
            }
            if ($a['usage_count'] !== $b['usage_count']) {
                return $b['usage_count'] <=> $a['usage_count'];
            }
            return strcasecmp($a['name'], $b['name']);
        });

        respond(['status' => 'success', 'occupations' => $items]);
    }

    if ($action === 'reorder') {
        $orderedNames = $jsonInput['names'] ?? [];
        if (is_array($orderedNames)) {
            $stmt = $pdo_user->prepare("
                INSERT INTO zimrx_user_occupations (doctor_id, name, usage_count, is_pinned, is_hidden, sort_order, created_at, updated_at)
                VALUES (:doctor_id, :name, 0, 0, 0, :sort_order, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT(doctor_id, name) DO UPDATE SET
                    sort_order = :sort_order,
                    updated_at = CURRENT_TIMESTAMP
            ");
            foreach ($orderedNames as $idx => $n) {
                $n = trim((string)$n);
                if ($n === '') continue;
                $stmt->execute([
                    'doctor_id' => $doctorId,
                    'name' => $n,
                    'sort_order' => $idx + 1
                ]);
            }
        }
        respond(['status' => 'success']);
    }

    if ($action === 'add') {
        if (strlen($name) < 2) {
            respond(['status' => 'error', 'message' => 'Please enter a valid occupation name.']);
        }

        zimrx_record_user_occupation($pdo_user, $doctorId, $name);
        respond(['status' => 'success']);
    }

    if ($action === 'edit') {
        if ($name === '' || $newName === '' || strlen($newName) < 2) {
            respond(['status' => 'error', 'message' => 'Please enter a valid occupation name.']);
        }

        // If user occupation exists, update name
        $stmt = $pdo_user->prepare("
            INSERT INTO zimrx_user_occupations (doctor_id, name, usage_count, is_pinned, is_hidden, created_at, updated_at)
            VALUES (:doctor_id, :new_name, 1, 0, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(doctor_id, name) DO UPDATE SET
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute(['doctor_id' => $doctorId, 'new_name' => $newName]);

        // If old record was different, delete old
        if (strtolower($name) !== strtolower($newName)) {
            $delStmt = $pdo_user->prepare("DELETE FROM zimrx_user_occupations WHERE doctor_id = :doctor_id AND name = :name");
            $delStmt->execute(['doctor_id' => $doctorId, 'name' => $name]);
        }

        respond(['status' => 'success']);
    }

    if ($action === 'toggle_pin') {
        if ($name === '') respond(['status' => 'error', 'message' => 'Invalid name.']);

        $stmt = $pdo_user->prepare("
            INSERT INTO zimrx_user_occupations (doctor_id, name, usage_count, is_pinned, is_hidden, created_at, updated_at)
            VALUES (:doctor_id, :name, 0, 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(doctor_id, name) DO UPDATE SET
                is_pinned = CASE WHEN zimrx_user_occupations.is_pinned = 1 THEN 0 ELSE 1 END,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute(['doctor_id' => $doctorId, 'name' => $name]);
        respond(['status' => 'success']);
    }

    if ($action === 'toggle_hide') {
        if ($name === '') respond(['status' => 'error', 'message' => 'Invalid name.']);

        $stmt = $pdo_user->prepare("
            INSERT INTO zimrx_user_occupations (doctor_id, name, usage_count, is_pinned, is_hidden, created_at, updated_at)
            VALUES (:doctor_id, :name, 0, 0, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(doctor_id, name) DO UPDATE SET
                is_hidden = CASE WHEN zimrx_user_occupations.is_hidden = 1 THEN 0 ELSE 1 END,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute(['doctor_id' => $doctorId, 'name' => $name]);
        respond(['status' => 'success']);
    }

    if ($action === 'delete') {
        if ($name === '') respond(['status' => 'error', 'message' => 'Invalid name.']);

        // Check if it's a static system occupation
        $checkStatic = $pdo_static->prepare("SELECT id FROM zimrx_static_occupations WHERE LOWER(name) = LOWER(:name) LIMIT 1");
        $checkStatic->execute(['name' => $name]);
        $isStatic = (bool)$checkStatic->fetchColumn();

        if ($isStatic) {
            // Mark as hidden for system occupation
            $stmt = $pdo_user->prepare("
                INSERT INTO zimrx_user_occupations (doctor_id, name, usage_count, is_pinned, is_hidden, created_at, updated_at)
                VALUES (:doctor_id, :name, 0, 0, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT(doctor_id, name) DO UPDATE SET
                    is_hidden = 1,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute(['doctor_id' => $doctorId, 'name' => $name]);
        } else {
            // Delete custom occupation completely
            $stmt = $pdo_user->prepare("
                DELETE FROM zimrx_user_occupations
                WHERE doctor_id = :doctor_id AND name = :name
            ");
            $stmt->execute(['doctor_id' => $doctorId, 'name' => $name]);
        }

        respond(['status' => 'success']);
    }

    if ($action === 'reset') {
        $stmt = $pdo_user->prepare("
            DELETE FROM zimrx_user_occupations
            WHERE doctor_id = :doctor_id
        ");
        $stmt->execute(['doctor_id' => $doctorId]);
        respond(['status' => 'success']);
    }

    respond(['status' => 'error', 'message' => 'Unknown action.']);
} catch (Throwable $e) {
    respond(['status' => 'error', 'message' => $e->getMessage()]);
}
