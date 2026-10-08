<?php
/**
 * Geo Studio - Universal Country Address Hierarchy Compiler
 * Pure native PHP script using PDO SQLite. Zero external dependencies.
 * 
 * Usage via CLI:
 *   php tools/geo-studio/compile_address_hierarchy.php BD
 *   php tools/geo-studio/compile_address_hierarchy.php sql BD
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

/**
 * Compiles a country JSON file into zimrx_address_hierarchy table.
 *
 * @param string $countryCode ISO2 country code (e.g. 'BD')
 * @param string|null $hierarchiesDir Directory containing {COUNTRY}.json
 * @param string|null $dbPath Path to zimrx_static.db
 * @return array{success: bool, country: string, stats: array<string, int>, message: string}
 */
function compileAddressHierarchy(string $countryCode, ?string $hierarchiesDir = null, ?string $dbPath = null): array
{
    $countryCode = strtoupper(trim($countryCode));
    $toolsGeoDir = __DIR__;
    $baseDir = dirname($toolsGeoDir, 2);

    $hierarchiesDir = $hierarchiesDir ?? ($toolsGeoDir . DIRECTORY_SEPARATOR . 'hierarchies');
    $dbPath = $dbPath ?? ($baseDir . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'systemdata' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'zimrx_static.db');

    $jsonFile = $hierarchiesDir . DIRECTORY_SEPARATOR . $countryCode . '.json';
    if (!is_file($jsonFile)) {
        throw new RuntimeException("Country file not found: {$jsonFile}");
    }

    $raw = file_get_contents($jsonFile);
    if ($raw === false) {
        throw new RuntimeException("Unable to read file: {$jsonFile}");
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException("Invalid JSON syntax in {$jsonFile}");
    }

    $manifest = $data['manifest'] ?? [];
    $targetCountry = strtoupper(trim((string)($manifest['country_code'] ?? $countryCode)));
    $places = $data['places'] ?? [];

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure unified table schema
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS zimrx_address_hierarchy (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            country TEXT NOT NULL,
            parent_id INTEGER NULL,
            level INTEGER NOT NULL,
            place_type TEXT NOT NULL,
            name_en TEXT NOT NULL,
            name_loc TEXT NULL,
            postcode TEXT NULL,
            FOREIGN KEY(parent_id) REFERENCES zimrx_address_hierarchy(id)
        );
        CREATE INDEX IF NOT EXISTS idx_address_country_search ON zimrx_address_hierarchy(country, name_en COLLATE NOCASE, name_loc);
        CREATE INDEX IF NOT EXISTS idx_address_parent ON zimrx_address_hierarchy(parent_id);
        CREATE INDEX IF NOT EXISTS idx_address_level_type ON zimrx_address_hierarchy(country, level, place_type);
    ");

    $insertStmt = $pdo->prepare("
        INSERT INTO zimrx_address_hierarchy 
        (country, parent_id, level, place_type, name_en, name_loc, postcode)
        VALUES (:country, :parent_id, :level, :place_type, :name_en, :name_loc, :postcode)
    ");

    $stats = ['total' => 0, 'max_depth' => 1];

    $compileNode = function (array $node, ?int $parentId, int $level) use (&$compileNode, $pdo, $insertStmt, $targetCountry, &$stats): void {
        $rawType = $node['type'] ?? 'place';
        if (is_array($rawType)) {
            $placeType = implode(',', array_map('trim', $rawType));
        } else {
            $placeType = trim((string)$rawType);
        }

        $nameEn = trim((string)($node['en'] ?? ''));
        $nameLoc = isset($node['loc']) ? trim((string)$node['loc']) : null;
        $rawCode = $node['code'] ?? $node['postcode'] ?? null;
        $postcode = ($rawCode !== null && trim((string)$rawCode) !== '') ? trim((string)$rawCode) : null;

        $insertStmt->execute([
            'country'    => $targetCountry,
            'parent_id'  => $parentId,
            'level'      => $level,
            'place_type' => $placeType,
            'name_en'    => $nameEn,
            'name_loc'   => $nameLoc !== '' ? $nameLoc : null,
            'postcode'   => $postcode,
        ]);

        $nodeId = (int)$pdo->lastInsertId();

        $stats['total']++;
        $stats['max_depth'] = max($stats['max_depth'], $level);
        $stats[$placeType] = ($stats[$placeType] ?? 0) + 1;

        // Fully flexible children resolution
        $children = [];
        if (isset($node['places']) && is_array($node['places'])) {
            $children = array_merge($children, $node['places']);
        }
        if (isset($node['children']) && is_array($node['children'])) {
            $children = array_merge($children, $node['children']);
        }
        foreach (['upazilas', 'thanas', 'unions', 'postoffices', 'villages', 'areas'] as $subkey) {
            if (isset($node[$subkey]) && is_array($node[$subkey])) {
                $children = array_merge($children, $node[$subkey]);
            }
        }

        foreach ($children as $child) {
            if (is_array($child)) {
                $compileNode($child, $nodeId, $level + 1);
            }
        }
    };

    $pdo->exec("BEGIN IMMEDIATE;");
    try {
        $delStmt = $pdo->prepare("DELETE FROM zimrx_address_hierarchy WHERE country = :c");
        $delStmt->execute(['c' => $targetCountry]);

        foreach ($places as $rootNode) {
            if (is_array($rootNode)) {
                $compileNode($rootNode, null, 1);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'success' => true,
        'country' => $targetCountry,
        'stats' => $stats,
        'message' => "Successfully compiled {$stats['total']} places for {$targetCountry} (Max depth: {$stats['max_depth']})",
    ];
}

/**
 * Escapes a string for safe SQL literals.
 */
function sqlQuoteStr(string $val): string
{
    return "'" . str_replace("'", "''", $val) . "'";
}

/**
 * Generates plain-text SQL seed dump for a country pack, along with companion JSON manifest.
 *
 * @param string $countryCode ISO2 country code (e.g. 'BD')
 * @param string|null $hierarchiesDir Directory containing {COUNTRY}.json
 * @param string|null $outPath Output path for .sql file
 * @return array{success: bool, country: string, total_places: int, filepath: string, manifest_filepath: string, sql: string}
 */
function generateAddressHierarchySqlDump(string $countryCode, ?string $hierarchiesDir = null, ?string $outPath = null): array
{
    $countryCode = strtoupper(trim($countryCode));
    $toolsGeoDir = __DIR__;
    $baseDir = dirname($toolsGeoDir, 2);

    $hierarchiesDir = $hierarchiesDir ?? ($toolsGeoDir . DIRECTORY_SEPARATOR . 'hierarchies');
    $outPath = $outPath ?? ($baseDir . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'systemdata' . DIRECTORY_SEPARATOR . 'seeds' . DIRECTORY_SEPARATOR . 'zimrx_address_hierarchy_' . strtolower($countryCode) . '.sql');

    $jsonFile = $hierarchiesDir . DIRECTORY_SEPARATOR . $countryCode . '.json';
    if (!is_file($jsonFile)) {
        throw new RuntimeException("Country file not found: {$jsonFile}");
    }

    $raw = file_get_contents($jsonFile);
    if ($raw === false) {
        throw new RuntimeException("Unable to read file: {$jsonFile}");
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException("Invalid JSON syntax in {$jsonFile}");
    }

    $manifest = $data['manifest'] ?? [];
    $targetCountry = strtoupper(trim((string)($manifest['country_code'] ?? $countryCode)));
    $countryName = trim((string)($manifest['country_name'] ?? $targetCountry));
    $author = trim((string)($manifest['author'] ?? 'ZimRx Geo Studio'));
    $license = trim((string)($manifest['license'] ?? 'CC0-1.0'));
    $version = trim((string)($manifest['version'] ?? '1.0.0'));
    $releaseDate = trim((string)($manifest['release_date'] ?? date('Y-m-d')));
    $lastUpdated = trim((string)($manifest['last_updated'] ?? date('Y-m-d')));
    $contributors = $manifest['contributors'] ?? ['Alif Farzan Zim'];
    if (!is_array($contributors)) {
        $contributors = [$contributors];
    }
    $places = $data['places'] ?? [];

    $rows = [];
    $nextLocalId = 1;
    $maxDepth = 1;
    $levelTypes = [];

    $flattenNode = function (array $node, ?int $parentLocalId, int $level) use (&$flattenNode, &$rows, &$nextLocalId, &$maxDepth, &$levelTypes, $targetCountry): void {
        $rawType = $node['type'] ?? 'place';
        if (is_array($rawType)) {
            $placeType = implode(',', array_map('trim', $rawType));
        } else {
            $placeType = trim((string)$rawType);
        }

        $nameEn = trim((string)($node['en'] ?? ''));
        $nameLoc = isset($node['loc']) ? trim((string)$node['loc']) : null;
        $rawCode = $node['code'] ?? $node['postcode'] ?? null;
        $postcode = ($rawCode !== null && trim((string)$rawCode) !== '') ? trim((string)$rawCode) : null;

        $currentId = $nextLocalId++;
        $maxDepth = max($maxDepth, $level);
        if ($placeType !== '') {
            $levelTypes[$level][$placeType] = true;
        }

        $rows[] = [
            'local_id' => $currentId,
            'parent_local_id' => $parentLocalId,
            'country' => $targetCountry,
            'level' => $level,
            'place_type' => $placeType,
            'name_en' => $nameEn,
            'name_loc' => $nameLoc !== '' ? $nameLoc : null,
            'postcode' => $postcode !== '' ? $postcode : null,
        ];

        $children = [];
        if (isset($node['places']) && is_array($node['places'])) {
            $children = array_merge($children, $node['places']);
        }
        if (isset($node['children']) && is_array($node['children'])) {
            $children = array_merge($children, $node['children']);
        }
        foreach (['upazilas', 'thanas', 'unions', 'postoffices', 'villages', 'areas'] as $subkey) {
            if (isset($node[$subkey]) && is_array($node[$subkey])) {
                $children = array_merge($children, $node[$subkey]);
            }
        }

        foreach ($children as $child) {
            if (is_array($child)) {
                $flattenNode($child, $currentId, $level + 1);
            }
        }
    };

    foreach ($places as $rootNode) {
        if (is_array($rootNode)) {
            $flattenNode($rootNode, null, 1);
        }
    }

    $totalPlaces = count($rows);

    // Resolve level_names for companion manifest
    $levelNames = [];
    if (!empty($data['hierarchy']['levels']) && is_array($data['hierarchy']['levels'])) {
        foreach ($data['hierarchy']['levels'] as $lvlConfig) {
            $types = $lvlConfig['types'] ?? [];
            if (!empty($types) && is_array($types) && isset($types[0]['key'])) {
                $levelNames[] = (string)$types[0]['key'];
            }
        }
    }
    if (empty($levelNames)) {
        for ($d = 1; $d <= $maxDepth; $d++) {
            if (!empty($levelTypes[$d])) {
                $typesAtDepth = array_keys($levelTypes[$d]);
                $levelNames[] = count($typesAtDepth) === 1 ? $typesAtDepth[0] : implode('/', $typesAtDepth);
            } else {
                $levelNames[] = "level_{$d}";
            }
        }
    }

    $sql = "-- ==========================================================================\n";
    $sql .= "-- ZimRx Static Places Seed: {$countryName} ({$targetCountry})\n";
    $sql .= "-- Generated by ZimRx Geo Studio\n";
    $sql .= "-- Date: {$lastUpdated} | Total Places: {$totalPlaces}\n";
    $sql .= "-- Author: {$author} | License: {$license}\n";
    $sql .= "-- ==========================================================================\n\n";

    $sql .= "CREATE TABLE IF NOT EXISTS zimrx_address_hierarchy (\n";
    $sql .= "    id INTEGER PRIMARY KEY AUTOINCREMENT,\n";
    $sql .= "    country TEXT NOT NULL,\n";
    $sql .= "    parent_id INTEGER NULL,\n";
    $sql .= "    level INTEGER NOT NULL,\n";
    $sql .= "    place_type TEXT NOT NULL,\n";
    $sql .= "    name_en TEXT NOT NULL,\n";
    $sql .= "    name_loc TEXT NULL,\n";
    $sql .= "    postcode TEXT NULL,\n";
    $sql .= "    FOREIGN KEY(parent_id) REFERENCES zimrx_address_hierarchy(id)\n";
    $sql .= ");\n\n";

    $sql .= "CREATE INDEX IF NOT EXISTS idx_address_country_search ON zimrx_address_hierarchy(country, name_en COLLATE NOCASE, name_loc);\n";
    $sql .= "CREATE INDEX IF NOT EXISTS idx_address_parent ON zimrx_address_hierarchy(parent_id);\n";
    $sql .= "CREATE INDEX IF NOT EXISTS idx_address_level_type ON zimrx_address_hierarchy(country, level, place_type);\n\n";

    $sql .= "BEGIN TRANSACTION;\n\n";
    $sql .= "DELETE FROM zimrx_address_hierarchy WHERE country = " . sqlQuoteStr($targetCountry) . ";\n\n";

    $sql .= "CREATE TEMP TABLE IF NOT EXISTS _tmp_places_seed (\n";
    $sql .= "    local_id INTEGER PRIMARY KEY,\n";
    $sql .= "    parent_local_id INTEGER NULL,\n";
    $sql .= "    country TEXT NOT NULL,\n";
    $sql .= "    level INTEGER NOT NULL,\n";
    $sql .= "    place_type TEXT NOT NULL,\n";
    $sql .= "    name_en TEXT NOT NULL,\n";
    $sql .= "    name_loc TEXT NULL,\n";
    $sql .= "    postcode TEXT NULL\n";
    $sql .= ");\n";
    $sql .= "DELETE FROM _tmp_places_seed;\n\n";

    $chunks = array_chunk($rows, 200);
    foreach ($chunks as $chunk) {
        $sql .= "INSERT INTO _tmp_places_seed (local_id, parent_local_id, country, level, place_type, name_en, name_loc, postcode) VALUES\n";
        $valLines = [];
        foreach ($chunk as $r) {
            $pId = $r['parent_local_id'] === null ? 'NULL' : (string)$r['parent_local_id'];
            $loc = $r['name_loc'] === null ? 'NULL' : sqlQuoteStr($r['name_loc']);
            $pc = $r['postcode'] === null ? 'NULL' : sqlQuoteStr($r['postcode']);
            $valLines[] = "  (" . (int)$r['local_id'] . ", {$pId}, " . sqlQuoteStr($r['country']) . ", " . (int)$r['level'] . ", " . sqlQuoteStr($r['place_type']) . ", " . sqlQuoteStr($r['name_en']) . ", {$loc}, {$pc})";
        }
        $sql .= implode(",\n", $valLines) . ";\n";
    }

    $sql .= "\nCREATE TEMP TABLE IF NOT EXISTS _base_offset AS SELECT COALESCE(MAX(id), 0) AS offset FROM zimrx_address_hierarchy;\n";
    $sql .= "DELETE FROM _base_offset;\n";
    $sql .= "INSERT INTO _base_offset (offset) SELECT COALESCE(MAX(id), 0) FROM zimrx_address_hierarchy;\n\n";

    $sql .= "INSERT INTO zimrx_address_hierarchy (country, parent_id, level, place_type, name_en, name_loc, postcode)\n";
    $sql .= "SELECT\n";
    $sql .= "    t.country,\n";
    $sql .= "    CASE WHEN t.parent_local_id IS NULL THEN NULL ELSE t.parent_local_id + b.offset END,\n";
    $sql .= "    t.level,\n";
    $sql .= "    t.place_type,\n";
    $sql .= "    t.name_en,\n";
    $sql .= "    t.name_loc,\n";
    $sql .= "    t.postcode\n";
    $sql .= "FROM _tmp_places_seed t\n";
    $sql .= "CROSS JOIN _base_offset b\n";
    $sql .= "ORDER BY t.local_id ASC;\n\n";

    $sql .= "DROP TABLE _tmp_places_seed;\n";
    $sql .= "DROP TABLE _base_offset;\n";
    $sql .= "COMMIT;\n";

    $manifestPath = '';
    if ($outPath !== null) {
        $outDir = dirname($outPath);
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }
        $tmpDump = $outPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmpDump, $sql, LOCK_EX) === false) {
            throw new RuntimeException("Failed to write temporary SQL dump file: {$tmpDump}");
        }
        if (!rename($tmpDump, $outPath)) {
            @unlink($tmpDump);
            throw new RuntimeException("Failed to atomically replace SQL dump file: {$outPath}");
        }

        // Companion JSON Manifest
        $manifestPath = preg_replace('/\.sql$/i', '.json', $outPath);
        $manifestPayload = [
            'country_code' => $targetCountry,
            'seed_file'    => basename($outPath),
            'places_count' => $totalPlaces,
            'levels'       => $maxDepth,
            'level_names'  => $levelNames,
            'version'      => $version,
            'release_date' => $releaseDate,
            'last_updated' => $lastUpdated,
            'author'       => $author,
            'license'      => $license,
            'contributors' => $contributors,
        ];

        $manifestJson = json_encode($manifestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        $tmpManifest = $manifestPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmpManifest, $manifestJson, LOCK_EX) === false) {
            throw new RuntimeException("Failed to write temporary manifest file: {$tmpManifest}");
        }
        if (!rename($tmpManifest, $manifestPath)) {
            @unlink($tmpManifest);
            throw new RuntimeException("Failed to atomically replace manifest file: {$manifestPath}");
        }
    }

    return [
        'success'           => true,
        'country'           => $targetCountry,
        'total_places'      => $totalPlaces,
        'filepath'          => $outPath,
        'manifest_filepath' => $manifestPath,
        'sql'               => $sql,
    ];
}

// If invoked directly from CLI
if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $actionArg = isset($argv[1]) ? strtolower(trim($argv[1])) : 'compile';
    $countryArg = isset($argv[2]) ? strtoupper(trim($argv[2])) : (isset($argv[1]) && strlen($argv[1]) === 2 ? strtoupper(trim($argv[1])) : 'BD');
    if ($actionArg === 'sql' || $actionArg === 'dump') {
        try {
            echo "Generating SQL dump and companion manifest for {$countryArg}...\n";
            $res = generateAddressHierarchySqlDump($countryArg);
            echo "Generated SQL dump ({$res['total_places']} places) at: {$res['filepath']}\n";
            echo "Generated companion manifest at: {$res['manifest_filepath']}\n";
        } catch (Throwable $e) {
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            exit(1);
        }
    } else {
        try {
            echo "Compiling address hierarchy for {$countryArg}...\n";
            $result = compileAddressHierarchy($countryArg);
            echo $result['message'] . ":\n";
            $stats = $result['stats'];
            ksort($stats);
            foreach ($stats as $k => $v) {
                if (!in_array($k, ['total', 'max_depth'], true)) {
                    echo "  {$k}: {$v}\n";
                }
            }
        } catch (Throwable $e) {
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            exit(1);
        }
    }
}
