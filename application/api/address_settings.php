<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

function respond(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo_static = DbConnections::staticDb();
    $pdo_user = DbConnections::userdata();

    $doctorId = function_exists('current_user_doctor_id') ? current_user_doctor_id() : (int)($_SESSION['doctor_id'] ?? 1);
    if ($doctorId <= 0) $doctorId = 1;

    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    if (!is_array($jsonInput)) {
        $jsonInput = [];
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? $jsonInput['action'] ?? 'list';
    $name = trim((string)($_POST['name'] ?? $jsonInput['name'] ?? ''));
    $newName = trim((string)($_POST['new_name'] ?? $jsonInput['new_name'] ?? ''));

    if ($action === 'list') {
        $searchQ = trim((string)($_GET['q'] ?? $jsonInput['q'] ?? ''));
        $filterType = trim((string)($_GET['filter'] ?? $jsonInput['filter'] ?? 'all')); // 'all', 'custom', 'system', 'pinned', 'hidden'

        // 1. Fetch saved preferred districts from interface settings
        $preferredDistricts = [];
        $stmtPref = $pdo_user->prepare("
            SELECT setting_value FROM zimrx_interface_settings 
            WHERE doctor_id = :doc AND setting_scope = 'prescription' AND setting_key = 'preferred_districts' 
            LIMIT 1
        ");
        $stmtPref->execute(['doc' => $doctorId]);
        $prefRow = $stmtPref->fetch(PDO::FETCH_ASSOC);
        if ($prefRow && !empty($prefRow['setting_value'])) {
            $decoded = json_decode($prefRow['setting_value'], true);
            if (is_array($decoded)) {
                $preferredDistricts = array_map('trim', $decoded);
            }
        }

        // 2. Fetch all 64 districts from static DB
        $stmtDist = $pdo_static->query("SELECT id, name, bn_name FROM zimrx_static_address_districts ORDER BY name ASC");
        $districtsRaw = $stmtDist->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $districts = [];
        foreach ($districtsRaw as $d) {
            $dName = trim((string)$d['name']);
            $isSelected = empty($preferredDistricts) || in_array($dName, $preferredDistricts, true);
            $districts[] = [
                'id' => (int)$d['id'],
                'name' => $dName,
                'bn_name' => trim((string)($d['bn_name'] ?? '')),
                'is_selected' => $isSelected,
            ];
        }

        // 3. Fetch custom addresses from userdata
        $customItems = [];
        if ($filterType !== 'system') {
            $sqlUser = "
                SELECT id, name, usage_count, is_pinned, is_hidden, sort_order 
                FROM zimrx_user_address 
                WHERE doctor_id = :doc
            ";
            $paramsUser = ['doc' => $doctorId];

            if ($filterType === 'pinned') {
                $sqlUser .= " AND is_pinned = 1";
            } elseif ($filterType === 'hidden') {
                $sqlUser .= " AND is_hidden = 1";
            }

            if ($searchQ !== '') {
                $sqlUser .= " AND name LIKE :q";
                $paramsUser['q'] = "%{$searchQ}%";
            }

            $sqlUser .= " ORDER BY is_pinned DESC, usage_count DESC, updated_at DESC, name ASC LIMIT 100";
            $stmtUser = $pdo_user->prepare($sqlUser);
            $stmtUser->execute($paramsUser);
            while ($r = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
                $customItems[] = [
                    'id' => (int)$r['id'],
                    'name' => trim((string)$r['name']),
                    'usage_count' => (int)($r['usage_count'] ?? 0),
                    'is_pinned' => (int)($r['is_pinned'] ?? 0),
                    'is_hidden' => (int)($r['is_hidden'] ?? 0),
                    'kind' => 'custom',
                ];
            }
        }

        // 4. If filter is 'all' or 'system' and search is active (or viewing system), fetch static entries
        $systemItems = [];
        if (($filterType === 'system' || ($filterType === 'all' && $searchQ !== '')) && count($customItems) < 100) {
            $limitSystem = 100 - count($customItems);
            $pLike = "%{$searchQ}%";

            $sqlSys = "
                SELECT 'district' as entity, name, bn_name FROM zimrx_static_address_districts WHERE name LIKE :p OR bn_name LIKE :p LIMIT 15
                UNION ALL
                SELECT 'upazila' as entity, name, bn_name FROM zimrx_static_address_upazillas WHERE name LIKE :p OR bn_name LIKE :p LIMIT 25
                UNION ALL
                SELECT 'thana' as entity, name, bn_name FROM zimrx_static_address_thana WHERE name LIKE :p OR bn_name LIKE :p LIMIT 25
                UNION ALL
                SELECT 'union' as entity, name, bn_name FROM zimrx_static_address_unions WHERE name LIKE :p OR bn_name LIKE :p LIMIT 25
                UNION ALL
                SELECT 'postoffice' as entity, name, bn_name FROM zimrx_static_address_postoffice WHERE name LIKE :p OR bn_name LIKE :p LIMIT 20
            ";
            $stmtSys = $pdo_static->prepare($sqlSys);
            $stmtSys->execute(['p' => $pLike]);
            while ($sr = $stmtSys->fetch(PDO::FETCH_ASSOC)) {
                $sName = trim((string)$sr['name']);
                $systemItems[] = [
                    'id' => 's_' . md5($sName),
                    'name' => $sName . (!empty($sr['bn_name']) ? " ({$sr['bn_name']})" : ""),
                    'usage_count' => 0,
                    'is_pinned' => 0,
                    'is_hidden' => 0,
                    'kind' => 'system',
                ];
                if (count($systemItems) >= $limitSystem) break;
            }
        }

        $allAddresses = array_merge($customItems, $systemItems);

        respond([
            'ok' => true,
            'districts' => $districts,
            'preferred_districts' => $preferredDistricts,
            'addresses' => $allAddresses,
            'total_loaded' => count($allAddresses),
            'filter' => $filterType,
            'query' => $searchQ,
        ]);
    }

    if ($action === 'save_districts') {
        $selected = $jsonInput['districts'] ?? $_POST['districts'] ?? [];
        if (!is_array($selected)) $selected = [];
        $selected = array_values(array_unique(array_filter(array_map('trim', $selected))));

        $jsonStr = json_encode($selected, JSON_UNESCAPED_UNICODE);

        $stmtSave = $pdo_user->prepare("
            INSERT INTO zimrx_interface_settings (doctor_id, setting_scope, setting_key, setting_value, updated_at)
            VALUES (:doc, 'prescription', 'preferred_districts', :json, CURRENT_TIMESTAMP)
            ON CONFLICT(doctor_id, setting_scope, setting_key) DO UPDATE SET
                setting_value = :json,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmtSave->execute(['doc' => $doctorId, 'json' => $jsonStr]);

        respond(['ok' => true, 'saved_districts' => $selected]);
    }

    if ($action === 'add') {
        if ($name === '') {
            respond(['ok' => false, 'error' => 'Address name cannot be empty']);
        }

        $stmt = $pdo_user->prepare("
            INSERT INTO zimrx_user_address (doctor_id, name, usage_count, is_pinned, is_hidden, updated_at)
            VALUES (:doc, :name, 1, 0, 0, CURRENT_TIMESTAMP)
            ON CONFLICT(doctor_id, name) DO UPDATE SET
                usage_count = usage_count + 1,
                is_hidden = 0,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute(['doc' => $doctorId, 'name' => $name]);

        respond(['ok' => true]);
    }

    if ($action === 'edit') {
        if ($name === '' || $newName === '') {
            respond(['ok' => false, 'error' => 'Valid names required']);
        }

        $stmt = $pdo_user->prepare("
            UPDATE zimrx_user_address 
            SET name = :new_name, updated_at = CURRENT_TIMESTAMP 
            WHERE doctor_id = :doc AND name = :name
        ");
        $stmt->execute(['new_name' => $newName, 'doc' => $doctorId, 'name' => $name]);

        respond(['ok' => true]);
    }

    if ($action === 'toggle_pin') {
        if ($name === '') {
            respond(['ok' => false, 'error' => 'Address name required']);
        }

        $stmt = $pdo_user->prepare("
            UPDATE zimrx_user_address 
            SET is_pinned = CASE WHEN is_pinned = 1 THEN 0 ELSE 1 END,
                updated_at = CURRENT_TIMESTAMP 
            WHERE doctor_id = :doc AND name = :name
        ");
        $stmt->execute(['doc' => $doctorId, 'name' => $name]);

        respond(['ok' => true]);
    }

    if ($action === 'toggle_hide') {
        if ($name === '') {
            respond(['ok' => false, 'error' => 'Address name required']);
        }

        $stmt = $pdo_user->prepare("
            UPDATE zimrx_user_address 
            SET is_hidden = CASE WHEN is_hidden = 1 THEN 0 ELSE 1 END,
                updated_at = CURRENT_TIMESTAMP 
            WHERE doctor_id = :doc AND name = :name
        ");
        $stmt->execute(['doc' => $doctorId, 'name' => $name]);

        respond(['ok' => true]);
    }

    if ($action === 'delete') {
        if ($name === '') {
            respond(['ok' => false, 'error' => 'Address name required']);
        }

        $stmt = $pdo_user->prepare("
            DELETE FROM zimrx_user_address 
            WHERE doctor_id = :doc AND name = :name
        ");
        $stmt->execute(['doc' => $doctorId, 'name' => $name]);

        respond(['ok' => true]);
    }

    if ($action === 'reset') {
        // Reset preferred districts
        $stmtDelPref = $pdo_user->prepare("
            DELETE FROM zimrx_interface_settings 
            WHERE doctor_id = :doc AND setting_scope = 'prescription' AND setting_key = 'preferred_districts'
        ");
        $stmtDelPref->execute(['doc' => $doctorId]);

        // Unhide all custom addresses
        $stmtResetAddr = $pdo_user->prepare("
            UPDATE zimrx_user_address 
            SET is_pinned = 0, is_hidden = 0 
            WHERE doctor_id = :doc
        ");
        $stmtResetAddr->execute(['doc' => $doctorId]);

        respond(['ok' => true]);
    }

    respond(['ok' => false, 'error' => 'Invalid action']);

} catch (Exception $e) {
    respond(['ok' => false, 'error' => $e->getMessage()]);
}
