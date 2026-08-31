<?php
$rx_popup_mode = isset($_GET['rx_popup']) && $_GET['rx_popup'] === '1';
$initialDrugDetail = null;
$initialSidebarState = null;

function drug_db_numeric_strength($s) {
    $s = (string)$s;
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

function drug_db_clean_variant_key($strength, $form) {
    $sClean = preg_replace('/\s+/', '', strtolower((string)$strength));
    $fClean = preg_replace('/\s+/', '', strtolower((string)$form));
    $sClean = str_replace(['mg/5ml', 'mg/5'], ['mgi', 'mgi'], $sClean);
    return $sClean . '_' . $fClean;
}

function drug_db_clinical_row(PDO $pdo, string $genericId) {
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
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function drug_db_variants_data(PDO $pdo, string $genericId, string $currentManufacturer = '') {
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
    $allBrands = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('zimrx_user_drug_filter_system_rows')) {
        $allBrands = zimrx_user_drug_filter_system_rows($allBrands);
    }

    $variants = [];
    foreach ($allBrands as $brand) {
        $key = drug_db_clean_variant_key($brand['strength'], $brand['form_new']);
        if (!isset($variants[$key])) {
            $variants[$key] = [
                'id' => $brand['id'],
                'strength' => $brand['strength'],
                'form' => $brand['form'],
                'form_new' => $brand['form_new'],
                'type_srt' => (int)$brand['type_srt'],
                'num_strength' => drug_db_numeric_strength($brand['strength']),
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

function drug_db_initial_detail() {
    $brandId = isset($_GET['brand_id']) ? trim((string)$_GET['brand_id']) : '';
    if ($brandId === '') {
        return null;
    }

    try {
        require_once __DIR__ . '/db.php';
        require_once __DIR__ . '/api/drug_catalog_lib.php';

        $pdo = DbConnections::systemDb();
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $brandInfo = drug_catalog_fetch_brand($pdo, $brandId);
        if (!$brandInfo) {
            return null;
        }

        $clinical = drug_db_clinical_row($pdo, (string)$brandInfo['generic_id']);
        return [
            'brand' => $brandInfo,
            'clinical' => $clinical,
            'preg_desc' => $clinical['pregnancy_modern_category'] ?? '',
            'variants' => drug_db_variants_data($pdo, (string)$brandInfo['generic_id'], (string)$brandInfo['company_id'])
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function drug_db_initial_normalized_search_sql(string $column): string {
    return "LOWER(TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE($column, ''), '.', ' '), '/', ' '), '-', ' '), '(', ' '), ')', ' '), '+', ' '), ',', ' ')))";
}

function drug_db_initial_sidebar_state() {
    $mode = trim((string)($_GET['mode'] ?? 'brand'));
    if (!in_array($mode, ['brand', 'generic', 'indication', 'class', 'docs', 'new', 'edit', 'delete'], true)) {
        $mode = 'brand';
    }

    if ($mode === 'docs' || $mode === 'new' || $mode === 'edit' || $mode === 'delete') {
        return ['mode' => $mode, 'query' => '', 'results' => []];
    }

    $queryParam = $mode . '_search';
    $query = trim((string)($_GET[$queryParam] ?? ''));
    if ($mode === 'brand' && $query === '') {
        $query = trim((string)($_GET['brand_search'] ?? ''));
    }
    try {
        require_once __DIR__ . '/db.php';
        require_once __DIR__ . '/api/drug_catalog_lib.php';

        $pdo = DbConnections::systemDb();
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if ($mode === 'brand') {
            $results = drug_catalog_search_brands($pdo, $query, 200, 400);
            $brandId = trim((string)($_GET['brand_id'] ?? ''));
            if ($brandId !== '') {
                $hasSelected = false;
                foreach ($results as $row) {
                    if ((string)($row['id'] ?? $row['brand_id'] ?? '') === $brandId) {
                        $hasSelected = true;
                        break;
                    }
                }
                if (!$hasSelected) {
                    $selectedBrand = drug_catalog_fetch_brand($pdo, $brandId);
                    if ($selectedBrand) {
                        array_unshift($results, $selectedBrand);
                    }
                }
            }
            return [
                'mode' => 'brand',
                'query' => $query,
                'results' => $results,
            ];
        }

        $normalizedQuery = strtolower(preg_replace('/\s+/', ' ', $query));
        $qStart = $normalizedQuery . '%';
        $qWordLike = '% ' . $normalizedQuery . '%';

        if ($mode === 'generic') {
            $genericSearch = drug_db_initial_normalized_search_sql('g.generic_name');
            $stmt = $pdo->prepare(
                "SELECT
                    g.generic_name AS generic,
                    g.generic_id,
                    " . drug_catalog_class_expr('g.generic_id') . " AS cls,
                    g.pregnancy_category AS preg_cat,
                    COUNT(p.brand_id) AS brand_count
                 FROM drug_generic g
                 JOIN drug_prescribe p ON CAST(p.generic_id AS TEXT) = CAST(g.generic_id AS TEXT)
                 WHERE (:q = '' OR $genericSearch LIKE :qStart OR $genericSearch LIKE :qWordLike)
                 GROUP BY g.generic_id, g.generic_name, g.pregnancy_category
                 ORDER BY g.generic_name ASC
                 LIMIT 30"
            );
            $stmt->execute(['q' => $query, 'qStart' => $qStart, 'qWordLike' => $qWordLike]);
            return ['mode' => 'generic', 'query' => $query, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
        }

        if ($mode === 'class') {
            $classSearch = drug_db_initial_normalized_search_sql('class_name');
            $stmt = $pdo->prepare(
                "SELECT class_name AS cls
                 FROM drug_therapeutic_class
                 WHERE (:q = '' OR $classSearch LIKE :qStart OR $classSearch LIKE :qWordLike)
                 ORDER BY class_name ASC"
            );
            $stmt->execute(['q' => $query, 'qStart' => $qStart, 'qWordLike' => $qWordLike]);
            return ['mode' => 'class', 'query' => $query, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
        }

        if ($mode === 'indication') {
            $indicationSearch = drug_db_initial_normalized_search_sql('indication_name');
            $stmt = $pdo->prepare(
                "SELECT indication_name, indication_id
                 FROM drug_indication
                 WHERE (:q = '' OR $indicationSearch LIKE :qStart OR $indicationSearch LIKE :qWordLike)
                 ORDER BY indication_name ASC
                 LIMIT 30"
            );
            $stmt->execute(['q' => $query, 'qStart' => $qStart, 'qWordLike' => $qWordLike]);
            return ['mode' => 'indication', 'query' => $query, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
        }

        return [
            'mode' => $mode,
            'query' => $query,
            'results' => [],
        ];
    } catch (Throwable $e) {
        return null;
    }
}

if ($rx_popup_mode) {
    require_once 'auth.php';
    $page_title = 'Drug View';
    $globalCssVersion = filemtime(__DIR__ . '/assets/css/global.css');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/global.css?v=<?= $globalCssVersion ?>">
    <link rel="stylesheet" href="assets/css/drug_db/base_sidebar.css?v=<?= filemtime(__DIR__ . '/assets/css/drug_db/base_sidebar.css') ?>">
    <link rel="stylesheet" href="assets/css/drug_db/detail.css?v=<?= filemtime(__DIR__ . '/assets/css/drug_db/detail.css') ?>">
    <link rel="stylesheet" href="assets/css/drug_db/modals_tables.css?v=<?= filemtime(__DIR__ . '/assets/css/drug_db/modals_tables.css') ?>">
    <link rel="stylesheet" href="assets/css/drug_db/middle_sidebar_nav.css?v=<?= filemtime(__DIR__ . '/assets/css/drug_db/middle_sidebar_nav.css') ?>">
</head>
<body class="rx-popup-mode">
<?php
} else {
    $extra_css = [
        'assets/css/drug_db/base_sidebar.css',
        'assets/css/drug_db/detail.css',
        'assets/css/drug_db/modals_tables.css',
        'assets/css/drug_db/middle_sidebar_nav.css',
    ];
    require_once 'header.php';
}

$initialDrugDetail = drug_db_initial_detail();
$initialSidebarState = drug_db_initial_sidebar_state();
require __DIR__ . '/modules/drug_db_layout.php';
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script>
window.ZIMRX_INITIAL_DRUG_DETAIL = <?= json_encode($initialDrugDetail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null' ?>;
window.ZIMRX_INITIAL_SIDEBAR_STATE = <?= json_encode($initialSidebarState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null' ?>;
</script>
<script src="assets/js/dosage_form_icons.js"></script>
<script src="assets/js/drug_db/state.js"></script>
<script src="assets/js/drug_db/utils.js"></script>
<script src="assets/js/drug_db/sidebar.js"></script>
<script src="assets/js/drug_db/generic_brands.js"></script>
<script src="assets/js/drug_db/drug_detail.js"></script>
<?php include 'footer.php'; ?>
