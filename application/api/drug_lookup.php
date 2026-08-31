<?php
define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/user_drug_lib.php';
header('Content-Type: application/json');

if (!function_exists('drug_lookup_user_row')) {
    function drug_lookup_user_row(array $row): array {
        return [
            'type' => 'brand',
            'generic_id' => (string)($row['generic_id'] ?? ''),
            'generic_name' => (string)($row['generic_name'] ?? $row['generic'] ?? ''),
            'label' => (string)($row['pres_new_upper'] ?? $row['brand_name'] ?? ''),
            'brand_id' => (string)($row['brand_id'] ?? $row['id'] ?? ''),
            'brand_name' => (string)($row['brand_name'] ?? ''),
            'manufacturer' => (string)($row['manufacturer'] ?? 'Custom'),
        ];
    }
}

try {
    $pdo = DbConnections::systemDb();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    if ($query === '') {
        echo json_encode([]);
        exit;
    }

    $qLike = '%' . $query . '%';
    $qStart = $query . '%';
    $genericPrefixRows = [];
    $genericOtherRows = [];
    $brandRows = [];
    $seen = [];

    $genericStmt = $pdo->prepare(
        "SELECT
            'generic' AS type,
            CAST(generic_id AS TEXT) AS generic_id,
            generic_name,
            generic_name AS label,
            '' AS brand_id,
            '' AS brand_name,
            '' AS manufacturer
         FROM drug_generic
         WHERE generic_name LIKE :q
            OR us_generic_name LIKE :q
         ORDER BY
            CASE
                WHEN generic_name LIKE :qStart THEN 0
                WHEN us_generic_name LIKE :qStart THEN 1
                ELSE 2
            END,
            generic_name ASC
         LIMIT 12"
    );
    $genericStmt->execute(['q' => $qLike, 'qStart' => $qStart]);
    foreach ($genericStmt->fetchAll() as $row) {
        $key = 'generic:' . (string)$row['generic_id'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        if (stripos((string)$row['generic_name'], $query) === 0) {
            $genericPrefixRows[] = $row;
        } else {
            $genericOtherRows[] = $row;
        }
    }

    // FTS5 accelerated search with graceful fallback
    $cleanTokens = array_filter(preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}]+/u', ' ', $query)));
    $ftsMatchQuery = !empty($cleanTokens) ? implode(' ', array_map(fn($t) => '"' . $t . '"*', $cleanTokens)) : '';
    $brandRawRows = [];

    if ($ftsMatchQuery !== '') {
        try {
            $ftsStmt = $pdo->prepare(
                "SELECT
                    'brand' AS type,
                    CAST(p.generic_id AS TEXT) AS generic_id,
                    p.generic_name,
                    p.prescribe_brand_short AS label,
                    CAST(p.brand_id AS TEXT) AS brand_id,
                    p.brand_name,
                    COALESCE(NULLIF(p.manufacturer_name_short, ''), p.manufacturer_name, '') AS manufacturer
                 FROM fts_drug_prescribe f
                 JOIN drug_prescribe p ON p.brand_id = CAST(f.rowid AS TEXT)
                 WHERE fts_drug_prescribe MATCH :matchQuery
                 ORDER BY
                    CASE
                        WHEN p.brand_name LIKE :qStart THEN 0
                        WHEN p.prescribe_brand_short LIKE :qStart THEN 0
                        WHEN p.generic_name LIKE :qStart THEN 1
                        ELSE 2
                    END,
                    CAST(p.manufacturer_preference AS INTEGER) ASC,
                    CAST(p.form_order AS INTEGER) ASC,
                    p.brand_name ASC,
                    p.prescribe_brand_short ASC
                 LIMIT 18"
            );
            $ftsStmt->execute(['matchQuery' => $ftsMatchQuery, 'qStart' => $qStart]);
            $brandRawRows = $ftsStmt->fetchAll();
        } catch (Throwable $e) {
            $brandRawRows = [];
        }
    }

    if (empty($brandRawRows)) {
        $brandStmt = $pdo->prepare(
            "SELECT
                'brand' AS type,
                CAST(generic_id AS TEXT) AS generic_id,
                generic_name,
                prescribe_brand_short AS label,
                CAST(brand_id AS TEXT) AS brand_id,
                brand_name,
                COALESCE(NULLIF(manufacturer_name_short, ''), manufacturer_name, '') AS manufacturer
             FROM drug_prescribe
             WHERE prescribe_brand_short LIKE :q
                OR prescribe_brand_full LIKE :q
                OR brand_name LIKE :q
                OR generic_name LIKE :q
             ORDER BY
                CASE
                    WHEN brand_name LIKE :qStart THEN 0
                    WHEN prescribe_brand_short LIKE :qStart THEN 0
                    WHEN generic_name LIKE :qStart THEN 1
                    ELSE 2
                END,
                CAST(manufacturer_preference AS INTEGER) ASC,
                CAST(form_order AS INTEGER) ASC,
                brand_name ASC,
                prescribe_brand_short ASC
             LIMIT 18"
        );
        $brandStmt->execute(['q' => $qLike, 'qStart' => $qStart]);
        $brandRawRows = $brandStmt->fetchAll();
    }

    foreach (zimrx_user_drug_filter_system_rows($brandRawRows) as $row) {
        $key = 'brand:' . (string)$row['brand_id'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $brandRows[] = $row;
    }

    foreach (zimrx_user_drug_search_rows($query, 12) as $row) {
        $lookupRow = drug_lookup_user_row($row);
        $key = 'brand:' . (string)$lookupRow['brand_id'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        array_unshift($brandRows, $lookupRow);
    }

    $results = array_merge($genericPrefixRows, $brandRows, $genericOtherRows);
    echo json_encode(array_slice($results, 0, 30), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Drug lookup failed']);
}
