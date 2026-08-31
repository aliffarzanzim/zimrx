<?php
define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

try {
    $doctorId = function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1;
    $pdo_static = DbConnections::staticDb();
    $pdo_user = DbConnections::userdata();

    $occupations = [];
    $seen = [];

    // 1. Fetch static default occupations
    $staticMap = [];
    $stmtStatic = $pdo_static->query("SELECT id, name FROM zimrx_static_occupations ORDER BY name ASC");
    $staticRows = $stmtStatic->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($staticRows as $sr) {
        $staticMap[strtolower(trim((string)$sr['name']))] = [
            'id' => (int)$sr['id'],
            'name' => trim((string)$sr['name']),
        ];
    }

    // 2. Fetch user learned / custom occupations for the current doctor
    $userMap = [];
    if (DbSchema::tableExists($pdo_user, 'zimrx_user_occupations')) {
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
    }

    $allKeys = [];
    foreach ($staticMap as $k => $_) $allKeys[$k] = true;
    foreach ($userMap as $k => $_) $allKeys[$k] = true;

    $items = [];
    foreach ($allKeys as $key => $_) {
        $isStatic = isset($staticMap[$key]);
        $u = $userMap[$key] ?? null;
        $name = $u ? trim((string)$u['name']) : $staticMap[$key]['name'];
        $isHidden = $u ? (int)$u['is_hidden'] : 0;
        if ($isHidden === 1) continue;

        $isPinned = $u ? (int)$u['is_pinned'] : 0;
        $usageCount = $u ? (int)$u['usage_count'] : 0;
        $sortOrder = $u ? (int)($u['sort_order'] ?? 0) : 0;
        $id = $u ? ('u_' . $u['id']) : ('s_' . $staticMap[$key]['id']);

        $items[] = [
            'id' => $id,
            'name' => $name,
            'is_custom' => $isStatic ? 0 : 1,
            'usage_count' => $usageCount,
            'is_pinned' => $isPinned,
            'sort_order' => $sortOrder,
        ];
    }

    // Sort: is_pinned DESC, (sort_order > 0 ? sort_order : 999999) ASC, usage_count DESC, name ASC
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

    echo json_encode($items, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}