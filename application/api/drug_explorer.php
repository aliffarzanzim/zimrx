<?php
define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/drug_catalog_lib.php';

header('Content-Type: application/json');

try {
    $pdo = DbConnections::systemDb();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $type = isset($_GET['type']) ? $_GET['type'] : 'brand';
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $id = isset($_GET['id']) ? trim($_GET['id']) : '';

    function getNumericStrength($s) {
        if (strpos($s, '/') !== false) {
            $parts = explode('/', $s);
            $val = (float)preg_replace('/[^\d\.]/', '', $parts[0]);
        } else {
            $val = (float)preg_replace('/[^\d\.]/', '', $s);
        }
        $lowerS = strtolower($s);
        if (strpos($lowerS, 'gm') !== false && strpos($lowerS, 'mg') === false) {
            $val *= 1000;
        }
        return $val;
    }

    function getCleanKey($s, $f) {
        $s_clean = preg_replace('/\s+/', '', strtolower((string)$s));
        $f_clean = preg_replace('/\s+/', '', strtolower((string)$f));
        $s_clean = str_replace(['mg/5ml', 'mg/5'], ['mgi', 'mgi'], $s_clean);
        return $s_clean . '_' . $f_clean;
    }

    function normalizedSearchSql(string $column): string {
        return "LOWER(TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE($column, ''), '.', ' '), '/', ' '), '-', ' '), '(', ' '), ')', ' '), '+', ' '), ',', ' ')))";
    }

    function clinicalRow(PDO $pdo, string $genericId): ?array {
        $stmt = $pdo->prepare(
            "SELECT
                *,
                mode_of_action_summary AS mode_of_action,
                pregnancy_category_and_lactation_note AS pregnancy_category_note
             FROM drug_generic
             WHERE CAST(generic_id AS TEXT) = CAST(:gid AS TEXT)
             LIMIT 1"
        );
        $stmt->execute(['gid' => $genericId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    function getModeOfActionMap(PDO $pdo, array $genericIds): array {
        $map = [];
        foreach ($genericIds as $genericId) {
            $clinical = clinicalRow($pdo, (string)$genericId);
            $map[$genericId] = $clinical ? trim((string)($clinical['mode_of_action'] ?? '')) : '';
        }
        return $map;
    }

    function fetchBrandsForGenericIds(PDO $pdo, array $genericIds): array {
        $genericIds = array_values(array_filter(array_map('strval', $genericIds), fn($v) => trim($v) !== ''));
        if (!$genericIds) {
            return [];
        }

        $placeholders = drug_catalog_placeholder_list($genericIds);
        $stmt = $pdo->prepare(
            "SELECT " . drug_catalog_select_sql() . "
             " . drug_catalog_from_sql() . "
             WHERE CAST(p.generic_id AS TEXT) IN ({$placeholders})
             ORDER BY CAST(p.manufacturer_preference AS INTEGER) ASC,
                      CAST(p.form_order AS INTEGER) ASC,
                      p.brand_name ASC,
                      p.prescribe_brand_short ASC"
        );
        $stmt->execute($genericIds);
        return zimrx_user_drug_filter_system_rows($stmt->fetchAll());
    }

    function getBrandsForClass(PDO $pdo, string $classId): array {
        return fetchBrandsForGenericIds($pdo, drug_catalog_fetch_generic_ids_for_class($pdo, $classId));
    }

    function getVariantsData(PDO $pdo, string $genericId, string $currentManufacturer = ''): array {
        $stmt = $pdo->prepare(
            "SELECT
                p.brand_id AS id,
                p.generic_id AS generic_id,
                p.manufacturer_id AS company_id,
                COALESCE(NULLIF(p.manufacturer_name, ''), p.manufacturer_id) AS manufacturer,
                p.form_order AS type_srt,
                p.form AS form,
                p.std_form AS form_new,
                p.strength AS strength
             FROM drug_prescribe p
             WHERE p.generic_id = :gid"
        );
        $stmt->execute(['gid' => $genericId]);
        $allBrands = zimrx_user_drug_filter_system_rows($stmt->fetchAll());

        $variants = [];
        foreach ($allBrands as $brand) {
            $key = getCleanKey($brand['strength'], $brand['form_new']);
            if (!isset($variants[$key])) {
                $variants[$key] = [
                    'id' => $brand['id'],
                    'strength' => $brand['strength'],
                    'form' => $brand['form'],
                    'form_new' => $brand['form_new'],
                    'type_srt' => (int)$brand['type_srt'],
                    'num_strength' => getNumericStrength($brand['strength']),
                    'has_same_company' => ($brand['company_id'] === $currentManufacturer || $brand['manufacturer'] === $currentManufacturer),
                    'raw_form' => strtolower($brand['form_new'])
                ];
            } elseif ($brand['company_id'] === $currentManufacturer || $brand['manufacturer'] === $currentManufacturer) {
                $variants[$key]['id'] = $brand['id'];
                $variants[$key]['has_same_company'] = true;
            }
        }

        $results = array_values($variants);
        usort($results, function ($a, $b) {
            $pA = (int)$a['type_srt'];
            $pB = (int)$b['type_srt'];
            if ($pA !== $pB) return $pA - $pB;
            if ($a['num_strength'] !== $b['num_strength']) return ($a['num_strength'] < $b['num_strength']) ? -1 : 1;
            return 0;
        });
        return $results;
    }

    $normalizedQuery = strtolower(preg_replace('/\s+/', ' ', trim($query)));
    $qWordLike = "% $normalizedQuery%";
    $qStart = "$normalizedQuery%";

    switch ($type) {
        case 'brand':
            $results = drug_catalog_search_brands($pdo, $query, 50, 120);
            break;

        case 'generic':
            $genericSearch = normalizedSearchSql('g.generic_name');
            $stmt = $pdo->prepare(
                "SELECT
                    g.generic_name AS generic,
                    g.generic_id,
                    " . drug_catalog_class_expr('g.generic_id') . " AS cls,
                    g.pregnancy_category AS preg_cat,
                    COUNT(p.brand_id) AS brand_count
                 FROM drug_generic g
                 JOIN drug_prescribe p ON CAST(p.generic_id AS TEXT) = CAST(g.generic_id AS TEXT)
                 WHERE (:q = '' OR $genericSearch LIKE :qStart
                    OR $genericSearch LIKE :qWordLike
                 )
                 GROUP BY g.generic_id, g.generic_name, g.pregnancy_category
                 ORDER BY g.generic_name ASC
                 LIMIT 30"
            );
            $stmt->execute(['q' => $query, 'qStart' => $qStart, 'qWordLike' => $qWordLike]);
            $results = $stmt->fetchAll();
            break;

        case 'class':
            $classSearch = normalizedSearchSql('class_name');
            $stmt = $pdo->prepare(
                "SELECT class_name AS cls
                 FROM drug_therapeutic_class
                 WHERE (:q = '' OR $classSearch LIKE :qStart OR $classSearch LIKE :qWordLike)
                 ORDER BY class_name ASC"
            );
            $stmt->execute(['q' => $query, 'qStart' => $qStart, 'qWordLike' => $qWordLike]);
            $results = $stmt->fetchAll();
            break;

        case 'class_brands':
            $results = getBrandsForClass($pdo, $id);
            break;

        case 'class_generics':
            $genericIds = drug_catalog_fetch_generic_ids_for_class($pdo, $id);
            if (!$genericIds) {
                $results = [];
                break;
            }

            $placeholders = drug_catalog_placeholder_list($genericIds);
            $stmt = $pdo->prepare(
                "SELECT
                    g.generic_id AS id,
                    g.generic_name AS name,
                    COUNT(p.brand_id) AS brand_count
                 FROM drug_generic g
                 JOIN drug_prescribe p ON CAST(p.generic_id AS TEXT) = CAST(g.generic_id AS TEXT)
                 WHERE CAST(g.generic_id AS TEXT) IN ({$placeholders})
                 GROUP BY g.generic_id, g.generic_name
                 ORDER BY g.generic_name ASC"
            );
            $stmt->execute($genericIds);
            $results = $stmt->fetchAll();
            break;

        case 'class_moa_table':
            $brands = getBrandsForClass($pdo, $id);
            $genericRows = [];
            foreach ($brands as $brand) {
                $genericId = $brand['generic_id'];
                if (!isset($genericRows[$genericId])) {
                    $genericRows[$genericId] = [
                        'id' => $genericId,
                        'name' => $brand['generic'],
                        'brand_count' => 0,
                        'brand_preview' => [],
                        'brand_preview_seen' => []
                    ];
                }
                $genericRows[$genericId]['brand_count']++;
                $brandKey = strtolower(trim((string)$brand['brand_name']));
                if ($brandKey !== '' && !isset($genericRows[$genericId]['brand_preview_seen'][$brandKey]) && count($genericRows[$genericId]['brand_preview']) < 4) {
                    $genericRows[$genericId]['brand_preview_seen'][$brandKey] = true;
                    $genericRows[$genericId]['brand_preview'][] = $brand['brand_name'];
                }
            }

            $modeOfActionMap = getModeOfActionMap($pdo, array_keys($genericRows));
            $results = [];
            foreach ($genericRows as $row) {
                $results[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'brand_count' => $row['brand_count'],
                    'brand_preview' => implode(', ', $row['brand_preview']),
                    'mode_of_action' => $modeOfActionMap[$row['id']] ?? ''
                ];
            }
            usort($results, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            break;

        case 'indication':
            $indicationSearch = normalizedSearchSql('indication_name');
            $stmt = $pdo->prepare(
                "SELECT indication_name, indication_id
                 FROM drug_indication
                 WHERE $indicationSearch LIKE :qStart
                    OR $indicationSearch LIKE :qWordLike
                 ORDER BY indication_name ASC
                 LIMIT 30"
            );
            $stmt->execute(['qStart' => $qStart, 'qWordLike' => $qWordLike]);
            $results = $stmt->fetchAll();
            break;

        case 'indication_generics':
            $genericIds = drug_catalog_fetch_generic_ids_for_indication($pdo, $id);
            if (!$genericIds) {
                $results = [];
                break;
            }

            $placeholders = drug_catalog_placeholder_list($genericIds);
            $stmt = $pdo->prepare(
                "SELECT
                    g.generic_name AS name,
                    g.generic_id AS id,
                    COUNT(p.brand_id) AS brand_count
                 FROM drug_generic g
                 JOIN drug_prescribe p ON CAST(p.generic_id AS TEXT) = CAST(g.generic_id AS TEXT)
                 WHERE CAST(g.generic_id AS TEXT) IN ({$placeholders})
                 GROUP BY g.generic_id, g.generic_name
                 ORDER BY g.generic_name ASC"
            );
            $stmt->execute($genericIds);
            $results = $stmt->fetchAll();
            break;

        case 'get_variants':
            $results = getVariantsData($pdo, $id, isset($_GET['man']) ? $_GET['man'] : '');
            break;

        case 'generic_brands':
            $stmt = $pdo->prepare(
                "SELECT " . drug_catalog_select_sql() . "
                 " . drug_catalog_from_sql() . "
                 WHERE CAST(p.generic_id AS TEXT) = CAST(:gid AS TEXT)
                 ORDER BY CAST(p.form_order AS INTEGER) ASC,
                          CAST(p.manufacturer_preference AS INTEGER) ASC,
                          p.brand_name ASC"
            );
            $stmt->execute(['gid' => $id]);
            $brands = zimrx_user_drug_filter_system_rows($stmt->fetchAll());

            $forms = [];
            foreach ($brands as $b) {
                if (!in_array($b['form_new'], $forms, true)) {
                    $forms[] = $b['form_new'];
                }
            }
            sort($forms);
            $results = ['brands' => $brands, 'forms' => $forms];
            break;

        case 'get_alternatives':
            $formNew = isset($_GET['form_new']) ? $_GET['form_new'] : '';
            $stmt = $pdo->prepare(
                "SELECT " . drug_catalog_select_sql() . "
                 " . drug_catalog_from_sql() . "
                 WHERE CAST(p.generic_id AS TEXT) = CAST(:gid AS TEXT)
                   AND p.std_form = :form
                 ORDER BY CAST(p.manufacturer_preference AS INTEGER) ASC,
                          p.prescribe_brand_short ASC"
            );
            $stmt->execute(['gid' => $id, 'form' => $formNew]);
            $results = zimrx_user_drug_filter_system_rows($stmt->fetchAll());
            break;

        case 'details':
            $brandInfo = drug_catalog_fetch_brand($pdo, $id);
            if (!$brandInfo) {
                echo json_encode(['error' => 'Drug not found']);
                exit;
            }

            $clinical = clinicalRow($pdo, (string)$brandInfo['generic_id']);
            $pregDesc = $clinical['pregnancy_modern_category'] ?? '';
            $variants = getVariantsData($pdo, (string)$brandInfo['generic_id'], (string)$brandInfo['company_id']);

            $results = [
                'brand' => $brandInfo,
                'clinical' => $clinical,
                'preg_desc' => $pregDesc,
                'variants' => $variants
            ];
            break;

        default:
            $results = ['error' => 'Invalid type'];
            break;
    }

    echo json_encode($results, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
