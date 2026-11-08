<?php
// Multi-tier address search across static administrative units and user address entries with contextual awareness.
declare(strict_types=1);

define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once dirname(__DIR__) . '/init.php';
require_login();
header('Content-Type: application/json');

try {
    $pdo_static = DbConnections::staticDb();
    $pdo_user = DbConnections::userdata();
    $doctorId = current_user_doctor_id();
    if ($doctorId <= 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'An active doctor account is required.']);
        exit;
    }

    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $segment = isset($_GET['segment']) ? (int)$_GET['segment'] : 0;
    $prev = isset($_GET['prev']) ? trim($_GET['prev']) : '';

    $activePack = \ZimRx\Services\AddressPackService::getConfiguredPack($pdo_static, $pdo_user);
    $packId = is_array($activePack) ? (string)$activePack['pack_id'] : '';
    $packSource = is_array($activePack) ? (string)$activePack['source'] : 'system';
    $packTables = \ZimRx\Services\AddressPackService::tableNames($packSource);
    $packPdo = $packSource === 'user' ? $pdo_user : $pdo_static;
    $placesTable = $packTables['places'];
    $namesTable = $packTables['names'];
    $hasPackPlaces = $packId !== '';
    $defaultLanguage = is_array($activePack) ? (string)$activePack['default_language'] : 'en';
    $displayLanguage = is_array($activePack) ? (string)$activePack['display_language'] : $defaultLanguage;

    $all_suggestions = [];

    // Contextual suggestions based on preceding address segments
    if ($hasPackPlaces && $prev !== '') {
        $stmtContext = $packPdo->prepare("
            SELECT
                COALESCE(parent_name.name, parent_default.name, '') AS parent_name,
                p.type_key AS parent_type,
                COALESCE(grandparent_name.name, grandparent_default.name, '') AS grandparent_name,
                gp.type_key AS grandparent_type
            FROM {$placesTable} c
            LEFT JOIN {$placesTable} p ON c.parent_id = p.id AND p.pack_id = :parent_pack
            LEFT JOIN {$namesTable} parent_name
                ON parent_name.place_id = p.id AND parent_name.language = :parent_language
            LEFT JOIN {$namesTable} parent_default
                ON parent_default.place_id = p.id AND parent_default.language = :parent_default_language
            LEFT JOIN {$placesTable} gp ON p.parent_id = gp.id AND gp.pack_id = :grandparent_pack
            LEFT JOIN {$namesTable} grandparent_name
                ON grandparent_name.place_id = gp.id AND grandparent_name.language = :grandparent_language
            LEFT JOIN {$namesTable} grandparent_default
                ON grandparent_default.place_id = gp.id AND grandparent_default.language = :grandparent_default_language
            WHERE c.pack_id = :current_pack
              AND (EXISTS (
                    SELECT 1 FROM {$namesTable} current_name
                    WHERE current_name.place_id = c.id AND current_name.name = :previous_name
                ) OR c.code = :previous_code)
        ");
        $stmtContext->execute([
            'parent_pack' => $packId,
            'parent_language' => $displayLanguage,
            'parent_default_language' => $defaultLanguage,
            'grandparent_pack' => $packId,
            'grandparent_language' => $displayLanguage,
            'grandparent_default_language' => $defaultLanguage,
            'current_pack' => $packId,
            'previous_name' => $prev,
            'previous_code' => $prev,
        ]);
        while ($r = $stmtContext->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($r['parent_name'])) {
                $pName = (string)$r['parent_name'];
                if (!isset($all_suggestions[$pName])) {
                    $all_suggestions[$pName] = ['type' => $r['parent_type'], 'score' => 1000];
                }
            }
            if (!empty($r['grandparent_name'])) {
                $gpName = (string)$r['grandparent_name'];
                if (!isset($all_suggestions[$gpName])) {
                    $all_suggestions[$gpName] = ['type' => $r['grandparent_type'], 'score' => 900];
                }
            }
        }
    }

    // Filter out context suggestions if they match the previous word exactly (Redundancy fix)
    if ($prev !== '') {
        foreach ($all_suggestions as $name => $data) {
            if (mb_strtolower($name) === mb_strtolower($prev)) {
                unset($all_suggestions[$name]);
            }
        }
    }

    // Filter out context suggestions if user started typing something that doesn't match
    if ($query !== '') {
        foreach ($all_suggestions as $name => $data) {
            if (stripos($name, $query) === false) {
                unset($all_suggestions[$name]);
            }
        }
    }

    if (strlen($query) >= 1) {
        $pLike = "{$query}%";
        $cLike = "%{$query}%";

        if ($hasPackPlaces) {
            $sqlPrefix = "
                SELECT h.type_key, COALESCE(display_name.name, default_name.name, '') AS name, h.level
                FROM {$placesTable} h
                LEFT JOIN {$namesTable} display_name
                    ON display_name.place_id = h.id AND display_name.language = :prefix_language
                LEFT JOIN {$namesTable} default_name
                    ON default_name.place_id = h.id AND default_name.language = :prefix_default_language
                WHERE h.pack_id = :prefix_pack
                  AND (EXISTS (
                        SELECT 1 FROM {$namesTable} searchable_name
                        WHERE searchable_name.place_id = h.id AND searchable_name.name LIKE :prefix_name
                    ) OR h.code LIKE :prefix_code)
                ORDER BY h.level ASC
                LIMIT 35
            ";
            $prefixParams = [
                'prefix_language' => $displayLanguage,
                'prefix_default_language' => $defaultLanguage,
                'prefix_pack' => $packId,
                'prefix_name' => $pLike,
                'prefix_code' => $pLike,
            ];
            $stmt = $packPdo->prepare($sqlPrefix);
            $stmt->execute($prefixParams);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $name = trim((string)$r['name']);
                if ($name !== '' && !isset($all_suggestions[$name])) {
                    $all_suggestions[$name] = ['type' => $r['type_key'], 'score' => 0];
                }
            }

            if (mb_strlen($query) >= 2) {
                $sqlContains = "
                    SELECT h.type_key, COALESCE(display_name.name, default_name.name, '') AS name, h.level
                    FROM {$placesTable} h
                    LEFT JOIN {$namesTable} display_name
                        ON display_name.place_id = h.id AND display_name.language = :contains_language
                    LEFT JOIN {$namesTable} default_name
                        ON default_name.place_id = h.id AND default_name.language = :contains_default_language
                    WHERE h.pack_id = :contains_pack
                      AND (EXISTS (
                            SELECT 1 FROM {$namesTable} searchable_name
                            WHERE searchable_name.place_id = h.id AND searchable_name.name LIKE :contains_name
                        ) OR h.code LIKE :contains_code)
                      AND NOT EXISTS (
                            SELECT 1 FROM {$namesTable} prefix_name
                            WHERE prefix_name.place_id = h.id AND prefix_name.name LIKE :contains_prefix
                        )
                      AND (h.code IS NULL OR h.code NOT LIKE :contains_code_prefix)
                    ORDER BY h.level ASC
                    LIMIT 20
                ";
                $containsParams = [
                    'contains_language' => $displayLanguage,
                    'contains_default_language' => $defaultLanguage,
                    'contains_pack' => $packId,
                    'contains_name' => $cLike,
                    'contains_code' => $cLike,
                    'contains_prefix' => $pLike,
                    'contains_code_prefix' => $pLike,
                ];
                $stmtContains = $packPdo->prepare($sqlContains);
                $stmtContains->execute($containsParams);
                while ($r = $stmtContains->fetch(PDO::FETCH_ASSOC)) {
                    $name = trim((string)$r['name']);
                    if ($name !== '' && !isset($all_suggestions[$name])) {
                        $all_suggestions[$name] = ['type' => $r['type_key'], 'score' => 0];
                    }
                }
            }
        }

        $sql_user = "
            SELECT * FROM (
                SELECT 'custom' as type, name, usage_count, is_pinned 
                FROM zimrx_user_address 
                WHERE doctor_id = :prefix_doc AND is_hidden = 0 AND name LIKE :user_prefix
                ORDER BY is_pinned DESC, usage_count DESC 
                LIMIT 20
            )
            UNION ALL
            SELECT * FROM (
                SELECT 'custom' as type, name, usage_count, is_pinned 
                FROM zimrx_user_address 
                WHERE doctor_id = :contains_doc AND is_hidden = 0 AND name LIKE :user_contains AND name NOT LIKE :user_prefix_exclude
                ORDER BY is_pinned DESC, usage_count DESC 
                LIMIT 10
            )
        ";
        $stmt_user = $pdo_user->prepare($sql_user);
        $stmt_user->execute([
            'prefix_doc' => $doctorId,
            'user_prefix' => $pLike,
            'contains_doc' => $doctorId,
            'user_contains' => $cLike,
            'user_prefix_exclude' => $pLike,
        ]);
        while ($r = $stmt_user->fetch(PDO::FETCH_ASSOC)) {
            $name = $r['name'];
            if (!isset($all_suggestions[$name])) {
                $customBonus = ((int)($r['is_pinned'] ?? 0) * 10000) + min(1000, (int)($r['usage_count'] ?? 0) * 50);
                $all_suggestions[$name] = ['type' => $r['type'], 'score' => $customBonus];
            }
        }
    }

    // Tiered scoring and relevance ranking
    foreach ($all_suggestions as $name => &$data) {
        $score = (int)($data['score'] ?? 0);
        $type = $data['type'];
        $nameLower = mb_strtolower($name);
        $queryLower = mb_strtolower($query);

        if ($queryLower !== '') {
            if ($nameLower === $queryLower) {
                $score += 100000; // Exact match
            } elseif (str_starts_with($nameLower, $queryLower . ' ')) {
                $score += 80000; // Starts with exact query word
            } elseif (str_starts_with($nameLower, $queryLower)) {
                $score += 50000; // Starts with query prefix
            } elseif (preg_match('/(?:^|[\s,\-\/\(])' . preg_quote($queryLower, '/') . '/i', $nameLower)) {
                $score += 25000; // Word boundary match
            } else {
                $score += 5000; // Substring match
            }
            // Length penalty: concise names rank higher than long phrases
            $score -= min(500, mb_strlen($name) * 5);
        }

        // Add points based on segment index priority
        if ($segment === 0) { // Segment 1: Custom > Union/Post Office > Upazila/Thana
            if ($type == 'custom') $score += 400;
            elseif ($type == 'union' || $type == 'union_t' || $type == 'postoffice') $score += 300;
            elseif ($type == 'upazila' || $type == 'thana') $score += 200;
            else $score += 100;
        } elseif ($segment === 1) { // Segment 2: Upazila/Thana > Union/Post Office > District
            if ($type == 'upazila' || $type == 'thana') $score += 400;
            elseif ($type == 'union' || $type == 'union_t' || $type == 'postoffice') $score += 300;
            elseif ($type == 'district') $score += 200;
            else $score += 100;
        } else { // Segment 3+: District > Upazila/Thana > Union/Post Office
            if ($type == 'district') $score += 400;
            elseif ($type == 'upazila' || $type == 'thana') $score += 300;
            elseif ($type == 'union' || $type == 'union_t' || $type == 'postoffice') $score += 200;
            else $score += 100;
        }

        $data['score'] = $score;
        $data['name'] = $name;
    }

    // Sort descending by score
    usort($all_suggestions, function($a, $b) { return $b['score'] <=> $a['score']; });

    // Output only top 12 unique names
    $final = [];
    foreach ($all_suggestions as $s) {
        $final[] = $s['name'];
        if (count($final) >= 12) break;
    }

    echo json_encode($final);

} catch (Throwable $e) {
    error_log('[ZimRx] search_address error: ' . $e->getMessage());
    echo json_encode([]);
}
