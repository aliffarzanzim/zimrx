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

    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $segment = isset($_GET['segment']) ? (int)$_GET['segment'] : 0;
    $prev = isset($_GET['prev']) ? trim($_GET['prev']) : '';

    // Resolve practice country
    $countryCode = 'INT';
    $hasStaticPlaces = false;
    try {
        $stC = $pdo_user->query("SELECT config_value FROM zimrx_app_config WHERE config_key = 'practice_country' LIMIT 1");
        $valC = $stC ? $stC->fetchColumn() : null;
        if ($valC) {
            $candidateCountry = strtoupper(trim((string)$valC));
            // Check if static places exist for selected country
            $stCheck = $pdo_static->prepare("SELECT 1 FROM zimrx_address_hierarchy WHERE country = :c LIMIT 1");
            $stCheck->execute(['c' => $candidateCountry]);
            if ($stCheck->fetch()) {
                $countryCode = $candidateCountry;
                $hasStaticPlaces = true;
            } else {
                $countryCode = $candidateCountry;
                $hasStaticPlaces = false;
            }
        }
    } catch (Throwable) {}

    // Resolve clinical / local language preference
    $reqLang = strtolower(trim((string)($_GET['lang'] ?? '')));
    if ($reqLang === '') {
        try {
            $stL = $pdo_user->query("SELECT config_value FROM zimrx_app_config WHERE config_key = 'clinical_lang' LIMIT 1");
            $valL = $stL ? $stL->fetchColumn() : null;
            if ($valL) {
                $reqLang = strtolower(trim((string)$valL));
            }
        } catch (Throwable) {}
    }
    $isBengaliTyped = (bool)preg_match('/[\x{0980}-\x{09FF}]/u', $query . $prev);
    $preferLocal = ($reqLang === 'bn') || $isBengaliTyped;

    $all_suggestions = [];

    // Contextual suggestions based on preceding address segments
    if ($hasStaticPlaces && $prev !== '') {
        $stmtContext = $pdo_static->prepare("
            SELECT 
                p.name_en AS parent_en, p.name_loc AS parent_loc, p.place_type AS parent_type,
                gp.name_en AS gp_en, gp.name_loc AS gp_loc, gp.place_type AS gp_type
            FROM zimrx_address_hierarchy c
            LEFT JOIN zimrx_address_hierarchy p ON c.parent_id = p.id
            LEFT JOIN zimrx_address_hierarchy gp ON p.parent_id = gp.id
            WHERE c.country = :country AND (c.name_en = :p OR c.name_loc = :p OR c.postcode = :p)
        ");
        $stmtContext->execute(['country' => $countryCode, 'p' => $prev]);
        while ($r = $stmtContext->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($r['parent_en'])) {
                $pName = ($preferLocal && !empty($r['parent_loc'])) ? $r['parent_loc'] : $r['parent_en'];
                if (!isset($all_suggestions[$pName])) {
                    $all_suggestions[$pName] = ['type' => $r['parent_type'], 'score' => 1000];
                }
            }
            if (!empty($r['gp_en'])) {
                $gpName = ($preferLocal && !empty($r['gp_loc'])) ? $r['gp_loc'] : $r['gp_en'];
                if (!isset($all_suggestions[$gpName])) {
                    $all_suggestions[$gpName] = ['type' => $r['gp_type'], 'score' => 900];
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

    // Load doctor's preferred districts filter if set
    $doctorId = (int)($_SESSION['doctor_id'] ?? 1);
    $preferredDistricts = [];
    $stmtPref = $pdo_user->prepare("
        SELECT setting_value FROM zimrx_interface_settings 
        WHERE (doctor_id = :doc OR doctor_id = 1) AND setting_scope = 'prescription' AND setting_key = 'preferred_districts' 
        LIMIT 1
    ");
    $stmtPref->execute(['doc' => $doctorId]);
    $prefRow = $stmtPref->fetch(PDO::FETCH_ASSOC);
    if ($prefRow && !empty($prefRow['setting_value'])) {
        $decoded = json_decode($prefRow['setting_value'], true);
        if (is_array($decoded) && !empty($decoded)) {
            $preferredDistricts = array_values(array_unique(array_filter(array_map('trim', $decoded))));
        }
    }

    // Build district SQL filter clause for unified places table
    $distFilterSql = "";
    if (!empty($preferredDistricts)) {
        $quotedDistList = implode("','", array_map(fn($d) => str_replace("'", "''", $d), $preferredDistricts));
        $distFilterSql = "AND (
            (place_type = 'district' AND name_en IN ('{$quotedDistList}'))
            OR parent_id IN (SELECT id FROM zimrx_address_hierarchy WHERE country = :country AND place_type = 'district' AND name_en IN ('{$quotedDistList}'))
            OR parent_id IN (
                SELECT id FROM zimrx_address_hierarchy WHERE parent_id IN (
                    SELECT id FROM zimrx_address_hierarchy WHERE country = :country AND place_type = 'district' AND name_en IN ('{$quotedDistList}')
                )
            )
        )";
    }

    // Text search matching prefix and substring
    if ($hasStaticPlaces && strlen($query) >= 1) {
        $pLike = "{$query}%";
        $cLike = "%{$query}%";

        // Prefix query across all places in country
        $sqlPrefix = "
            SELECT place_type, name_en, name_loc, postcode, level
            FROM zimrx_address_hierarchy
            WHERE country = :country
              AND (name_en LIKE :p OR name_loc LIKE :p OR postcode LIKE :p)
              {$distFilterSql}
            ORDER BY level ASC
            LIMIT 35
        ";
        $stmt = $pdo_static->prepare($sqlPrefix);
        $stmt->execute(['country' => $countryCode, 'p' => $pLike]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = ($preferLocal && !empty($r['name_loc'])) ? $r['name_loc'] : $r['name_en'];
            if (!isset($all_suggestions[$name])) {
                $all_suggestions[$name] = ['type' => $r['place_type'], 'score' => 0];
            }
        }

        // Substring match for queries with 2 or more characters
        if (mb_strlen($query) >= 2) {
            $sqlContains = "
                SELECT place_type, name_en, name_loc, postcode, level
                FROM zimrx_address_hierarchy
                WHERE country = :country
                  AND (name_en LIKE :c OR name_loc LIKE :c OR postcode LIKE :c)
                  AND name_en NOT LIKE :p AND (name_loc IS NULL OR name_loc NOT LIKE :p)
                  {$distFilterSql}
                ORDER BY level ASC
                LIMIT 20
            ";
            $stmtContains = $pdo_static->prepare($sqlContains);
            $stmtContains->execute(['country' => $countryCode, 'c' => $cLike, 'p' => $pLike]);
            while ($r = $stmtContains->fetch(PDO::FETCH_ASSOC)) {
                $name = ($preferLocal && !empty($r['name_loc'])) ? $r['name_loc'] : $r['name_en'];
                if (!isset($all_suggestions[$name])) {
                    $all_suggestions[$name] = ['type' => $r['place_type'], 'score' => 0];
                }
            }
        }

        // Search custom user address database (userdata)
        $doctor_id = (int)($_SESSION['doctor_id'] ?? 1);
        $sql_user = "
            SELECT * FROM (
                SELECT 'custom' as type, name, usage_count, is_pinned 
                FROM zimrx_user_address 
                WHERE (doctor_id = :doc OR doctor_id = 1) AND is_hidden = 0 AND name LIKE :p 
                ORDER BY is_pinned DESC, usage_count DESC 
                LIMIT 20
            )
            UNION ALL
            SELECT * FROM (
                SELECT 'custom' as type, name, usage_count, is_pinned 
                FROM zimrx_user_address 
                WHERE (doctor_id = :doc OR doctor_id = 1) AND is_hidden = 0 AND name LIKE :c AND name NOT LIKE :p 
                ORDER BY is_pinned DESC, usage_count DESC 
                LIMIT 10
            )
        ";
        $stmt_user = $pdo_user->prepare($sql_user);
        $stmt_user->execute(['doc' => $doctor_id, 'p' => $pLike, 'c' => $cLike]);
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
