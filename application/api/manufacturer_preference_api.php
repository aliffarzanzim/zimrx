<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$userDb = DbConnections::userdata();
$systemDb = DbConnections::systemDb();

$doctorId = max(1, (int)(function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1));
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

try {
    // 1. Get complete list of preferences and manufacturers
    if ($action === 'get_list') {
        // Fetch user custom sorting
        $uStmt = $userDb->prepare(
            "SELECT manufacturer_id, manufacturer_name, sort_order, is_hidden 
             FROM zimrx_user_manufacturer_sorting 
             WHERE doctor_id = :doc"
        );
        $uStmt->execute(['doc' => $doctorId]);
        $userPrefs = [];
        while ($r = $uStmt->fetch(PDO::FETCH_ASSOC)) {
            $userPrefs[(string)$r['manufacturer_id']] = [
                'sort_order' => (int)$r['sort_order'],
                'is_hidden' => (int)$r['is_hidden']
            ];
        }

        // Fetch all manufacturers and brand counts from system DB
        $sStmt = $systemDb->query(
            "SELECT 
                p.manufacturer_id, 
                COALESCE(NULLIF(p.manufacturer_name, ''), m.name, 'Unknown') AS name,
                p.manufacturer_name_short AS short_name,
                MIN(p.manufacturer_preference) AS default_preference,
                COUNT(*) AS brand_count
             FROM drug_prescribe p
             LEFT JOIN drug_manufacturer m ON CAST(m.id AS TEXT) = CAST(p.manufacturer_id AS TEXT)
             WHERE p.manufacturer_id IS NOT NULL AND p.manufacturer_id != ''
             GROUP BY p.manufacturer_id
             ORDER BY MIN(p.manufacturer_preference) ASC, brand_count DESC"
        );
        $all = $sStmt->fetchAll(PDO::FETCH_ASSOC);

        $customRanked = [];
        $hiddenList = [];
        $manufacturers = [];

        foreach ($all as $row) {
            $mid = (string)$row['manufacturer_id'];
            $name = (string)$row['name'];
            $shortName = (string)($row['short_name'] ?: $name);
            $defaultPref = (int)($row['default_preference'] ?: 9999);
            $brandCount = (int)$row['brand_count'];

            $userPref = $userPrefs[$mid] ?? null;
            $isCustom = false;
            $sortOrder = 0;
            $isHidden = 0;

            if ($userPref) {
                $sortOrder = (int)$userPref['sort_order'];
                $isHidden = (int)$userPref['is_hidden'];
                if ($sortOrder > 0) {
                    $isCustom = true;
                }
            }

            $item = [
                'id' => $mid,
                'name' => $name,
                'short_name' => $shortName,
                'brand_count' => $brandCount,
                'default_preference' => $defaultPref,
                'sort_order' => $sortOrder,
                'is_hidden' => $isHidden,
                'is_custom' => $isCustom
            ];

            $manufacturers[] = $item;

            if ($isHidden === 1) {
                $hiddenList[] = $item;
            } elseif ($isCustom) {
                $customRanked[] = $item;
            }
        }

        // Sort custom ranked list by sort_order ascending
        usort($customRanked, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        echo json_encode([
            'success' => true,
            'doctor_id' => $doctorId,
            'custom_ranked' => $customRanked,
            'hidden' => $hiddenList,
            'all' => $manufacturers,
            'counts' => [
                'total' => count($manufacturers),
                'custom' => count($customRanked),
                'hidden' => count($hiddenList)
            ]
        ]);
        exit;
    }

    // 2. Save complete custom priority order
    if ($action === 'save_order') {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: $_POST;
        $items = $body['items'] ?? [];

        if (!is_array($items)) {
            echo json_encode(['success' => false, 'error' => 'Invalid items payload']);
            exit;
        }

        $userDb->beginTransaction();

        // Remove non-hidden custom rankings for this doctor and re-insert new ranks
        $userDb->prepare(
            "UPDATE zimrx_user_manufacturer_sorting 
             SET sort_order = 0, updated_at = CURRENT_TIMESTAMP 
             WHERE doctor_id = :doc AND is_hidden = 0"
        )->execute(['doc' => $doctorId]);

        $upsert = $userDb->prepare(
            "INSERT INTO zimrx_user_manufacturer_sorting 
                (doctor_id, manufacturer_id, manufacturer_name, sort_order, is_hidden, created_at, updated_at)
             VALUES 
                (:doc, :mid, :name, :so, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON CONFLICT(doctor_id, manufacturer_name) DO UPDATE SET
                sort_order = :so,
                manufacturer_id = :mid,
                is_hidden = 0,
                updated_at = CURRENT_TIMESTAMP"
        );

        $rank = 1;
        foreach ($items as $it) {
            $mid = trim((string)($it['id'] ?? ''));
            $mName = trim((string)($it['name'] ?? ''));
            if (!$mid || !$mName) continue;

            $upsert->execute([
                'doc' => $doctorId,
                'mid' => $mid,
                'name' => $mName,
                'so' => $rank
            ]);
            $rank++;
        }

        $userDb->commit();

        echo json_encode(['success' => true, 'saved_count' => $rank - 1]);
        exit;
    }

    // 3. Toggle hide/show manufacturer
    if ($action === 'toggle_hide') {
        $mid = trim((string)($_POST['manufacturer_id'] ?? ''));
        $mName = trim((string)($_POST['manufacturer_name'] ?? ''));
        $hide = (int)($_POST['is_hidden'] ?? 0);

        if (!$mid || !$mName) {
            echo json_encode(['success' => false, 'error' => 'Missing manufacturer ID or name']);
            exit;
        }

        $upsert = $userDb->prepare(
            "INSERT INTO zimrx_user_manufacturer_sorting 
                (doctor_id, manufacturer_id, manufacturer_name, sort_order, is_hidden, created_at, updated_at)
             VALUES 
                (:doc, :mid, :name, 0, :hid, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON CONFLICT(doctor_id, manufacturer_name) DO UPDATE SET
                is_hidden = :hid,
                updated_at = CURRENT_TIMESTAMP"
        );
        $upsert->execute([
            'doc' => $doctorId,
            'mid' => $mid,
            'name' => $mName,
            'hid' => $hide ? 1 : 0
        ]);

        echo json_encode(['success' => true, 'is_hidden' => $hide ? 1 : 0]);
        exit;
    }

    // 4. Remove single company from custom ranking (reverts to default)
    if ($action === 'remove_custom') {
        $mid = trim((string)($_POST['manufacturer_id'] ?? ''));
        if (!$mid) {
            echo json_encode(['success' => false, 'error' => 'Missing manufacturer ID']);
            exit;
        }

        $del = $userDb->prepare(
            "UPDATE zimrx_user_manufacturer_sorting 
             SET sort_order = 0, updated_at = CURRENT_TIMESTAMP 
             WHERE doctor_id = :doc AND manufacturer_id = :mid"
        );
        $del->execute(['doc' => $doctorId, 'mid' => $mid]);

        echo json_encode(['success' => true]);
        exit;
    }

    // 5. Reset all customizations back to system defaults
    if ($action === 'reset_defaults') {
        $del = $userDb->prepare(
            "DELETE FROM zimrx_user_manufacturer_sorting WHERE doctor_id = :doc"
        );
        $del->execute(['doc' => $doctorId]);

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
} catch (Throwable $e) {
    if ($userDb->inTransaction()) {
        $userDb->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
