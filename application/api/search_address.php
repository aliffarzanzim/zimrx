<?php
define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

try {
    $pdo_static = DbConnections::staticDb();
    $pdo_user = DbConnections::userdata();

    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $segment = isset($_GET['segment']) ? (int)$_GET['segment'] : 0;
    $prev = isset($_GET['prev']) ? trim($_GET['prev']) : '';

    $all_suggestions = [];

    // ==========================================
    // PHASE 1: CONTEXT AWARENESS (Previous Word)
    // ==========================================
    if ($prev !== '') {
        // Find if prev is a Union (Get its Upazila and District)
        $stmt = $pdo_static->prepare("
            SELECT un.name as union_name, u.name as upa_name, d.name as dist_name 
            FROM zimrx_static_address_unions un 
            JOIN zimrx_static_address_upazillas u ON un.upazila_id = u.id 
            JOIN zimrx_static_address_districts d ON u.district_id = d.id 
            WHERE un.name = :p OR un.bn_name = :p
        ");
        $stmt->execute(['p' => $prev]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($all_suggestions[$r['upa_name']])) $all_suggestions[$r['upa_name']] = ['type' => 'upazila', 'score' => 1000];
            if (!isset($all_suggestions[$r['dist_name']])) $all_suggestions[$r['dist_name']] = ['type' => 'district', 'score' => 900];
        }

        // Find if prev is a Post Office (Get its Thana and District)
        $stmtPo = $pdo_static->prepare("
            SELECT po.name as postoffice_name, t.name as thana_name, d.name as dist_name
            FROM zimrx_static_address_postoffice po
            JOIN zimrx_static_address_thana t ON po.thana_id = t.id
            JOIN zimrx_static_address_districts d ON t.district_id = d.id
            WHERE po.name = :p OR po.bn_name = :p OR po.postcode = :p
        ");
        $stmtPo->execute(['p' => $prev]);
        while ($r = $stmtPo->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($all_suggestions[$r['thana_name']])) $all_suggestions[$r['thana_name']] = ['type' => 'thana', 'score' => 1000];
            if (!isset($all_suggestions[$r['dist_name']])) $all_suggestions[$r['dist_name']] = ['type' => 'district', 'score' => 900];
        }

        // Find if prev is an Upazila (Get its District)
        $stmt2 = $pdo_static->prepare("
            SELECT d.name as dist_name 
            FROM zimrx_static_address_upazillas u 
            JOIN zimrx_static_address_districts d ON u.district_id = d.id 
            WHERE u.name = :p OR u.bn_name = :p
        ");
        $stmt2->execute(['p' => $prev]);
        while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($all_suggestions[$r['dist_name']])) $all_suggestions[$r['dist_name']] = ['type' => 'district', 'score' => 1000];
        }

        // Find if prev is a Thana (Get its District)
        $stmtThana = $pdo_static->prepare("
            SELECT d.name as dist_name
            FROM zimrx_static_address_thana t
            JOIN zimrx_static_address_districts d ON t.district_id = d.id
            WHERE t.name = :p OR t.bn_name = :p
        ");
        $stmtThana->execute(['p' => $prev]);
        while ($r = $stmtThana->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($all_suggestions[$r['dist_name']])) $all_suggestions[$r['dist_name']] = ['type' => 'district', 'score' => 1000];
        }
    }

    // Filter out context suggestions if they match the previous word exactly (Redundancy fix)
    if ($prev !== '') {
        foreach ($all_suggestions as $name => $data) {
            if (strtolower($name) === strtolower($prev)) {
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

    // Build district SQL filter clause for static queries
    $distFilterSql = "";
    $distSubquery = "";
    $upaSubquery = "";
    if (!empty($preferredDistricts)) {
        $quotedDistList = implode("','", array_map(fn($d) => str_replace("'", "''", $d), $preferredDistricts));
        $distFilterSql = "AND name IN ('{$quotedDistList}')";
        $distSubquery = "AND district_id IN (SELECT id FROM zimrx_static_address_districts WHERE name IN ('{$quotedDistList}'))";
        $upaSubquery = "AND upazila_id IN (SELECT id FROM zimrx_static_address_upazillas WHERE district_id IN (SELECT id FROM zimrx_static_address_districts WHERE name IN ('{$quotedDistList}')))";
    }

    // ==========================================
    // PHASE 2: STANDARD SEARCH (PREFIX-FIRST + CONTAINS)
    // ==========================================
    if (strlen($query) >= 1) {
        $pLike = "{$query}%";
        $cLike = "%{$query}%";
        
        // 1. Prefix query across all address entities (subqueries ensure equal representation)
        $sql_prefix = "
            SELECT * FROM (SELECT 'district' as type, name FROM zimrx_static_address_districts WHERE (name LIKE :p OR bn_name LIKE :p) {$distFilterSql} LIMIT 25)
            UNION ALL
            SELECT * FROM (SELECT 'upazila' as type, name FROM zimrx_static_address_upazillas WHERE (name LIKE :p OR bn_name LIKE :p) {$distSubquery} LIMIT 25)
            UNION ALL
            SELECT * FROM (SELECT 'union_t' as type, name FROM zimrx_static_address_unions WHERE (name LIKE :p OR bn_name LIKE :p) {$upaSubquery} LIMIT 35)
            UNION ALL
            SELECT * FROM (SELECT 'thana' as type, name FROM zimrx_static_address_thana WHERE (name LIKE :p OR bn_name LIKE :p) {$distSubquery} LIMIT 25)
            UNION ALL
            SELECT * FROM (SELECT 'postoffice' as type, name FROM zimrx_static_address_postoffice WHERE (name LIKE :p OR bn_name LIKE :p OR postcode LIKE :p) {$distSubquery} LIMIT 25)
        ";
        $stmt = $pdo_static->prepare($sql_prefix);
        $stmt->execute(['p' => $pLike]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = $r['name'];
            if (!isset($all_suggestions[$name])) {
                $all_suggestions[$name] = ['type' => $r['type'], 'score' => 0];
            }
        }

        // 2. Contains query (if query has >= 2 chars) to match inside compound names
        if (strlen($query) >= 2) {
            $sql_contains = "
                SELECT * FROM (SELECT 'district' as type, name FROM zimrx_static_address_districts WHERE (name LIKE :c OR bn_name LIKE :c) AND name NOT LIKE :p {$distFilterSql} LIMIT 15)
                UNION ALL
                SELECT * FROM (SELECT 'upazila' as type, name FROM zimrx_static_address_upazillas WHERE (name LIKE :c OR bn_name LIKE :c) AND name NOT LIKE :p {$distSubquery} LIMIT 15)
                UNION ALL
                SELECT * FROM (SELECT 'union_t' as type, name FROM zimrx_static_address_unions WHERE (name LIKE :c OR bn_name LIKE :c) AND name NOT LIKE :p {$upaSubquery} LIMIT 20)
                UNION ALL
                SELECT * FROM (SELECT 'thana' as type, name FROM zimrx_static_address_thana WHERE (name LIKE :c OR bn_name LIKE :c) AND name NOT LIKE :p {$distSubquery} LIMIT 15)
                UNION ALL
                SELECT * FROM (SELECT 'postoffice' as type, name FROM zimrx_static_address_postoffice WHERE (name LIKE :c OR bn_name LIKE :c OR postcode LIKE :c) AND name NOT LIKE :p {$distSubquery} LIMIT 15)
            ";
            $stmt = $pdo_static->prepare($sql_contains);
            $stmt->execute(['c' => $cLike, 'p' => $pLike]);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $name = $r['name'];
                if (!isset($all_suggestions[$name])) {
                    $all_suggestions[$name] = ['type' => $r['type'], 'score' => 0];
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
        $stmt = $pdo_user->prepare($sql_user);
        $stmt->execute(['doc' => $doctor_id, 'p' => $pLike, 'c' => $cLike]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = $r['name'];
            if (!isset($all_suggestions[$name])) {
                $customBonus = ((int)($r['is_pinned'] ?? 0) * 10000) + min(1000, (int)($r['usage_count'] ?? 0) * 50);
                $all_suggestions[$name] = ['type' => $r['type'], 'score' => $customBonus];
            }
        }
    }

    // ==========================================
    // PHASE 3: SMART TIERED SCORING & SORTING
    // ==========================================
    foreach ($all_suggestions as $name => &$data) {
        $score = (int)($data['score'] ?? 0);
        $type = $data['type'];
        $nameLower = strtolower($name);
        $queryLower = strtolower($query);
        
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
            $score -= min(500, strlen($name) * 5);
        }

        // Add points based on segment index priority
        if ($segment === 0) { // Segment 1: Custom > Union/Post Office > Upazila/Thana
            if ($type == 'custom') $score += 400;
            elseif ($type == 'union_t' || $type == 'postoffice') $score += 300;
            elseif ($type == 'upazila' || $type == 'thana') $score += 200;
            else $score += 100;
        } elseif ($segment === 1) { // Segment 2: Upazila/Thana > Union/Post Office > District
            if ($type == 'upazila' || $type == 'thana') $score += 400;
            elseif ($type == 'union_t' || $type == 'postoffice') $score += 300;
            elseif ($type == 'district') $score += 200;
            else $score += 100;
        } else { // Segment 3+: District > Upazila/Thana > Union/Post Office
            if ($type == 'district') $score += 400;
            elseif ($type == 'upazila' || $type == 'thana') $score += 300;
            elseif ($type == 'union_t' || $type == 'postoffice') $score += 200;
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

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
