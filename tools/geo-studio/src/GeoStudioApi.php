<?php
/**
 * ZimRx Geo Studio - AJAX API Controller
 * Dispatches and responds to all client-side requests with standardized JSON responses.
 * Strict type checking and defensive error handling.
 */

declare(strict_types=1);

namespace ZimRx\GeoStudio;

use Throwable;

class GeoStudioApi
{
    private CountryCatalog $catalog;
    private string $hierarchiesDir;
    private string $cacheDir;

    public function __construct(string $hierarchiesDir, ?string $cacheDir = null)
    {
        $this->hierarchiesDir = $hierarchiesDir;
        $this->cacheDir = $cacheDir ?? (dirname($hierarchiesDir) . DIRECTORY_SEPARATOR . 'cache');
        $this->catalog = new CountryCatalog($hierarchiesDir);
    }

    public function handle(string $action): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            switch ($action) {
                case 'list_countries':
                    $countries = $this->catalog->listCountries();
                    echo json_encode(['success' => true, 'countries' => $countries], JSON_UNESCAPED_UNICODE);
                    exit;

                case 'load_country':
                    $code = trim((string)($_GET['code'] ?? 'BD'));
                    $data = $this->catalog->loadCountry($code);
                    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
                    exit;

                case 'save_country':
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        http_response_code(405);
                        echo json_encode(['success' => false, 'error' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    $raw = file_get_contents('php://input') ?: '';
                    $payload = json_decode($raw, true);
                    if (!is_array($payload) || !isset($payload['code']) || !isset($payload['data'])) {
                        echo json_encode(['success' => false, 'error' => 'Invalid JSON payload structure.'], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    $code = (string)$payload['code'];
                    $contributor = (string)($payload['contributor'] ?? 'Alif Farzan Zim');
                    $res = $this->catalog->saveCountry($code, $payload['data'], $contributor);
                    echo json_encode($res, JSON_UNESCAPED_UNICODE);
                    exit;

                case 'create_blank_country':
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        http_response_code(405);
                        echo json_encode(['success' => false, 'error' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    $params = $_POST;
                    if (!empty($params['depth_levels']) && is_string($params['depth_levels'])) {
                        $params['depth_levels'] = json_decode($params['depth_levels'], true);
                    }
                    $res = $this->catalog->createBlankCountry($params);
                    echo json_encode($res, JSON_UNESCAPED_UNICODE);
                    exit;

                case 'compile_country':
                    $code = CountryCatalog::sanitizeCode((string)($_GET['code'] ?? 'BD'));
                    $res = compileAddressHierarchy($code, $this->hierarchiesDir);
                    echo json_encode(['success' => true, 'result' => $res], JSON_UNESCAPED_UNICODE);
                    exit;

                case 'generate_sql_dump':
                    $code = CountryCatalog::sanitizeCode((string)($_GET['code'] ?? 'BD'));
                    $res = generateAddressHierarchySqlDump($code, $this->hierarchiesDir);
                    echo json_encode([
                        'success' => true,
                        'country' => $res['country'],
                        'total_places' => $res['total_places'],
                        'filepath' => $res['filepath'],
                        'manifest_filepath' => $res['manifest_filepath'],
                        'filesize' => file_exists($res['filepath']) ? filesize($res['filepath']) : 0,
                        'message' => "Successfully generated SQL dump ({$res['total_places']} places) in application/systemdata/seeds/zimrx_address_hierarchy_" . strtolower($res['country']) . ".sql",
                    ], JSON_UNESCAPED_UNICODE);
                    exit;

                case 'download_sql_dump':
                    $code = CountryCatalog::sanitizeCode((string)($_GET['code'] ?? 'BD'));
                    $res = generateAddressHierarchySqlDump($code, $this->hierarchiesDir);
                    header('Content-Type: application/sql; charset=utf-8');
                    header('Content-Disposition: attachment; filename="zimrx_address_hierarchy_' . strtolower($code) . '.sql"');
                    echo $res['sql'];
                    exit;

                case 'download_json':
                    $code = CountryCatalog::sanitizeCode((string)($_GET['code'] ?? ''));
                    $filePath = $this->catalog->getCountryFilePath($code);
                    if (!is_file($filePath)) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'File not found.'], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    header('Content-Type: application/json; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $code . '.json"');
                    readfile($filePath);
                    exit;

                case 'import_geonames':
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        http_response_code(405);
                        echo json_encode(['success' => false, 'error' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    $code = CountryCatalog::sanitizeCode((string)($_POST['code'] ?? ''));
                    $name = trim((string)($_POST['name'] ?? $code));
                    $contributor = trim((string)($_POST['contributor'] ?? 'Alif Farzan Zim'));

                    $rawContent = '';
                    if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                        if ($ext === 'zip') {
                            $rawContent = GeoNamesImporter::extractZipContent($_FILES['file']['tmp_name']);
                        } else {
                            $rawContent = file_get_contents($_FILES['file']['tmp_name']) ?: '';
                        }
                    } elseif (!empty($_POST['json_data'])) {
                        $rawContent = trim((string)$_POST['json_data']);
                    } else {
                        $rawContent = GeoNamesImporter::fetchLiveZip($code);
                    }

                    $res = GeoNamesImporter::parseAndSaveDataset($rawContent, $code, $name, $contributor, $this->hierarchiesDir);

                    // Auto-compile into SQLite database
                    try {
                        compileAddressHierarchy($code, $this->hierarchiesDir);
                    } catch (Throwable $e) {}

                    echo json_encode($res, JSON_UNESCAPED_UNICODE);
                    exit;

                case 'import_wikidata':
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        http_response_code(405);
                        echo json_encode(['success' => false, 'error' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    $code = CountryCatalog::sanitizeCode((string)($_POST['code'] ?? ''));
                    $name = trim((string)($_POST['name'] ?? $code));
                    $contributor = trim((string)($_POST['contributor'] ?? 'Alif Farzan Zim'));
                    $maxLevel = isset($_POST['max_level']) ? (int)$_POST['max_level'] : 0;
                    $selectedLevels = [];
                    if (!empty($_POST['selected_levels'])) {
                        if (is_array($_POST['selected_levels'])) {
                            $selectedLevels = array_map('intval', $_POST['selected_levels']);
                        } else {
                            $selectedLevels = array_map('intval', explode(',', (string)$_POST['selected_levels']));
                        }
                        $selectedLevels = array_values(array_filter($selectedLevels, fn($d) => $d > 0));
                        sort($selectedLevels);
                    }
                    if (empty($selectedLevels) && $maxLevel > 0) {
                        $selectedLevels = range(1, $maxLevel);
                    }

                    $rawUploadedJson = null;
                    if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                        $rawUploadedJson = file_get_contents($_FILES['file']['tmp_name']) ?: null;
                    }

                    $res = WikidataImporter::fetchAndSaveDataset($code, $name, $contributor, $this->hierarchiesDir, $rawUploadedJson, $maxLevel, $selectedLevels);

                    // Auto-compile into SQLite database
                    try {
                        compileAddressHierarchy($code, $this->hierarchiesDir);
                    } catch (Throwable $e) {}

                    echo json_encode($res, JSON_UNESCAPED_UNICODE);
                    exit;

                case 'get_wikidata_levels':
                    $code = CountryCatalog::sanitizeCode((string)($_GET['code'] ?? ''));
                    $res = WikidataImporter::discoverCountryLevels($code);
                    echo json_encode($res, JSON_UNESCAPED_UNICODE);
                    exit;

                case 'get_offline_countries':
                    $res = GeoNamesImporter::getOfflineCountryCatalog();
                    echo json_encode($res, JSON_UNESCAPED_UNICODE);
                    exit;

                case 'fetch_geonames_countries':
                    $force = isset($_GET['refresh']) && $_GET['refresh'] === '1';
                    $res = GeoNamesImporter::fetchLiveCountryCatalog($force, $this->cacheDir);
                    echo json_encode($res, JSON_UNESCAPED_UNICODE);
                    exit;

                case 'fetch_wikidata_countries':
                    $force = isset($_GET['refresh']) && $_GET['refresh'] === '1';
                    $res = WikidataImporter::fetchLiveCountryCatalog($force, $this->cacheDir);
                    echo json_encode($res, JSON_UNESCAPED_UNICODE);
                    exit;

                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => "Unknown action '{$action}'."], JSON_UNESCAPED_UNICODE);
                    exit;
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
