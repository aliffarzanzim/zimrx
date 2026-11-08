<?php
declare(strict_types=1);

// Address settings endpoint: manages preferred districts, custom address entries, pinning, and visibility.

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/init.php';
require_login();

function respond(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo_static = DbConnections::staticDb();
    $pdo_user = DbConnections::userdata();

    $doctorId = current_user_doctor_id();
    if ($doctorId <= 0) {
        http_response_code(401);
        respond(['ok' => false, 'error' => 'Unauthorized']);
    }

    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    if (!is_array($jsonInput)) {
        $jsonInput = [];
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? $jsonInput['action'] ?? 'list';
    if ($action !== 'list') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            respond(['ok' => false, 'error' => 'Method not allowed.']);
        }
        if (!zimrx_verify_csrf()) {
            http_response_code(403);
            respond(['ok' => false, 'error' => 'CSRF verification failed.']);
        }
    }
    $name = trim((string)($_POST['name'] ?? $jsonInput['name'] ?? ''));
    $newName = trim((string)($_POST['new_name'] ?? $jsonInput['new_name'] ?? ''));

    if ($action === 'list') {
        $searchQ = trim((string)($_GET['q'] ?? $jsonInput['q'] ?? ''));
        $filterType = trim((string)($_GET['filter'] ?? $jsonInput['filter'] ?? 'all')); // 'all', 'custom', 'system', 'pinned', 'hidden'
        $activePack = \ZimRx\Services\AddressPackService::getConfiguredPack($pdo_static, $pdo_user);
        $packId = is_array($activePack) ? (string)$activePack['pack_id'] : '';
        $defaultLanguage = is_array($activePack) ? (string)$activePack['default_language'] : 'en';
        $displayLanguage = is_array($activePack) ? (string)$activePack['display_language'] : $defaultLanguage;
        $packSource = is_array($activePack) ? (string)$activePack['source'] : 'system';
        $packTables = \ZimRx\Services\AddressPackService::tableNames($packSource);
        $packPdo = $packSource === 'user' ? $pdo_user : $pdo_static;
        $placesTable = $packTables['places'];
        $namesTable = $packTables['names'];

        // Custom address entries saved by doctor
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

        // Fall back to static administrative units when searching or viewing catalog
        $systemItems = [];
        if ($packId !== '' && ($filterType === 'system' || ($filterType === 'all' && $searchQ !== '')) && count($customItems) < 100) {
            $limitSystem = 100 - count($customItems);
            $pLike = "%{$searchQ}%";

            $sqlSys = "
                SELECT h.id, h.type_key AS entity,
                       COALESCE(display_name.name, default_name.name, '') AS name
                FROM {$placesTable} h
                LEFT JOIN {$namesTable} display_name
                    ON display_name.place_id = h.id AND display_name.language = :display_language
                LEFT JOIN {$namesTable} default_name
                    ON default_name.place_id = h.id AND default_name.language = :default_language
                WHERE h.pack_id = :pack_id
                  AND EXISTS (
                      SELECT 1 FROM {$namesTable} searchable_name
                      WHERE searchable_name.place_id = h.id AND searchable_name.name LIKE :search_name
                  )
                ORDER BY h.level ASC
                LIMIT :row_limit
            ";
            $stmtSys = $packPdo->prepare($sqlSys);
            $stmtSys->bindValue(':display_language', $displayLanguage, PDO::PARAM_STR);
            $stmtSys->bindValue(':default_language', $defaultLanguage, PDO::PARAM_STR);
            $stmtSys->bindValue(':pack_id', $packId, PDO::PARAM_STR);
            $stmtSys->bindValue(':search_name', $pLike, PDO::PARAM_STR);
            $stmtSys->bindValue(':row_limit', $limitSystem, PDO::PARAM_INT);
            $stmtSys->execute();
            while ($sr = $stmtSys->fetch(PDO::FETCH_ASSOC)) {
                $sName = trim((string)$sr['name']);
                if ($sName === '') {
                    continue;
                }
                $systemItems[] = [
                    'id' => 's_' . (int)$sr['id'],
                    'name' => $sName,
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
            'addresses' => $allAddresses,
            'total_loaded' => count($allAddresses),
            'filter' => $filterType,
            'query' => $searchQ,
        ]);
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
        $stmtResetAddr = $pdo_user->prepare("
            UPDATE zimrx_user_address
            SET is_pinned = 0, is_hidden = 0
            WHERE doctor_id = :doc
        ");
        $stmtResetAddr->execute(['doc' => $doctorId]);

        respond(['ok' => true]);
    }

    respond(['ok' => false, 'error' => 'Invalid action']);

} catch (Throwable $e) {
    if (isset($pdo_user) && $pdo_user instanceof PDO && $pdo_user->inTransaction()) {
        try {
            $pdo_user->rollBack();
        } catch (Throwable) {}
    }
    error_log('[ZimRx] address_settings error: ' . $e->getMessage());
    respond(['ok' => false, 'error' => 'An internal error occurred. Please try again.']);
}
