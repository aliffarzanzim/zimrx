<?php
define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/drug_catalog_lib.php';
header('Content-Type: application/json');

try {
    $pdo = DbConnections::systemDb();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $genericOnly = isset($_GET['generic']) ? trim($_GET['generic']) : '';
    $genericIdOnly = isset($_GET['generic_id']) ? trim($_GET['generic_id']) : '';
    
    if (strlen($query) < 1 && strlen($genericOnly) < 1) {
        $sql = "
            SELECT " . drug_catalog_search_select_sql() . "
            " . drug_catalog_from_sql() . "
            ORDER BY CAST(p.manufacturer_preference AS INTEGER) ASC,
                     CAST(p.form_order AS INTEGER) ASC,
                     p.prescribe_brand_short ASC
            LIMIT 20
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        echo json_encode(zimrx_user_drug_merge_search($stmt->fetchAll(), '', 20));
        exit;
    }

    /**
     * Normalizes strength strings for comparison (removes spaces, sorts components).
     */
    function normalizeStrength($s) {
        if (!$s) return '';
        $s = strtolower(trim($s));
        $s = preg_replace('/\s+/', '', $s); // Remove all whitespace
        
        if (strpos($s, '+') !== false) {
            $parts = explode('+', $s);
            sort($parts); // Sort parts to handle "500+200" vs "200+500"
            return implode('+', $parts);
        }
        return $s;
    }

    /**
     * Normalizes dosage forms for comparison.
     */
    function normalizeForm($f) {
        if (!$f) return '';
        $f = strtolower(trim($f));
        $f = str_replace(['.', ' '], '', $f);
        // Map common variations
        $map = ['tab' => 'tablet', 'cap' => 'capsule', 'syp' => 'syrup', 'susp' => 'suspension', 'inj' => 'injection'];
        return isset($map[$f]) ? $map[$f] : $f;
    }

    if (!empty($genericOnly)) {
        $genericLimit = 80;
        $formFilter = isset($_GET['form']) ? trim($_GET['form']) : '';
        $strengthFilter = isset($_GET['strength']) ? trim($_GET['strength']) : '';
        $targetForm = normalizeForm($formFilter);
        $targetStrength = normalizeStrength($strengthFilter);

        $genericLookupSql = $genericIdOnly !== ''
            ? "CAST(p.generic_id AS TEXT) = CAST(:generic_id AS TEXT)"
            : "p.generic_name = :generic";
        $genericParams = $genericIdOnly !== ''
            ? ['generic_id' => $genericIdOnly]
            : ['generic' => $genericOnly];

        $candidateFilters = [];
        if ($targetForm !== '' && $targetForm !== 'null' && $targetForm !== 'undefined') {
            $candidateFilters[] = "(
                LOWER(REPLACE(REPLACE(COALESCE(p.form, ''), '.', ''), ' ', '')) = :target_form
                OR LOWER(REPLACE(REPLACE(COALESCE(p.std_form, ''), '.', ''), ' ', '')) = :target_form
            )";
            $genericParams['target_form'] = $targetForm;
        }

        $strengthNeedsPhpFallback = $targetStrength !== '' && $targetStrength !== 'null' && $targetStrength !== 'undefined' && strpos($targetStrength, '+') !== false;
        if ($targetStrength !== '' && $targetStrength !== 'null' && $targetStrength !== 'undefined' && !$strengthNeedsPhpFallback) {
            $candidateFilters[] = "LOWER(REPLACE(COALESCE(p.strength, ''), ' ', '')) = :target_strength";
            $genericParams['target_strength'] = $targetStrength;
        }

        $candidateSql = "
            SELECT " . drug_catalog_search_select_sql() . "
            " . drug_catalog_from_sql() . "
            WHERE {$genericLookupSql}
        ";
        if ($candidateFilters) {
            $candidateSql .= " AND " . implode(" AND ", $candidateFilters);
        }
        $candidateSql .= "
            ORDER BY CAST(p.manufacturer_preference AS INTEGER) ASC,
                     CAST(p.form_order AS INTEGER) ASC,
                     p.prescribe_brand_short ASC
            LIMIT {$genericLimit}
        ";

        $stmt = $pdo->prepare($candidateSql);
        $stmt->execute($genericParams);
        $allBrands = zimrx_user_drug_filter_system_rows($stmt->fetchAll());

        if (($targetForm !== '' || $targetStrength !== '') && !$allBrands) {
            $fallbackSql = "
                SELECT " . drug_catalog_search_select_sql() . "
                " . drug_catalog_from_sql() . "
                WHERE {$genericLookupSql}
                ORDER BY CAST(p.manufacturer_preference AS INTEGER) ASC,
                         CAST(p.form_order AS INTEGER) ASC,
                         p.prescribe_brand_short ASC
                LIMIT {$genericLimit}
            ";
            $stmt = $pdo->prepare($fallbackSql);
            $stmt->execute($genericIdOnly !== '' ? ['generic_id' => $genericIdOnly] : ['generic' => $genericOnly]);
            $allBrands = zimrx_user_drug_filter_system_rows($stmt->fetchAll());
        }

        $filtered = [];
        foreach ($allBrands as $item) {
            $matchForm = true;
            if ($targetForm && $targetForm !== 'null' && $targetForm !== 'undefined') {
                $matchForm = (normalizeForm($item['form']) === $targetForm);
            }

            $matchStrength = true;
            if ($targetStrength && $targetStrength !== 'null' && $targetStrength !== 'undefined') {
                $matchStrength = (normalizeStrength($item['strength']) === $targetStrength);
            }

            if ($matchForm && $matchStrength) {
                $filtered[] = $item;
            }
        }
        
        $userMatches = array_filter(zimrx_user_drug_search_rows($genericOnly, 30), static function (array $item) use ($genericOnly): bool {
            return strcasecmp((string)($item['generic_name'] ?? $item['generic'] ?? ''), $genericOnly) === 0;
        });
        $results = array_values(array_merge($userMatches, $filtered));
    } else {
        $results = drug_catalog_search_brands($pdo, $query, 50, 120);
    }
    
    echo json_encode($results);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
