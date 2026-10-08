<?php
/**
 * ZimRx Geo Studio - Country Catalog Repository
 * Manages country pack JSON datasets, manifests, statistics, and metadata.
 * Zero external dependencies. 100% offline-ready.
 */

declare(strict_types=1);

namespace ZimRx\GeoStudio;

use RuntimeException;
use InvalidArgumentException;

class CountryCatalog
{
    private string $countriesDir;

    public function __construct(string $countriesDir)
    {
        $this->countriesDir = rtrim($countriesDir, DIRECTORY_SEPARATOR);
        if (!is_dir($this->countriesDir)) {
            mkdir($this->countriesDir, 0755, true);
        }
    }

    /**
     * Sanitizes and validates a country or regional pack identifier.
     * Prevents directory traversal attacks.
     */
    public static function sanitizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        // Common ISO aliases (e.g. UK -> GB)
        $aliases = [
            'UK' => 'GB',
            'EL' => 'GR',
        ];
        if (isset($aliases[$code])) {
            $code = $aliases[$code];
        }

        if (!preg_match('/^[A-Z0-9_-]{2,16}$/', $code)) {
            throw new InvalidArgumentException("Invalid pack code '{$code}'. Must be 2-16 characters (letters, numbers, hyphens, underscores).");
        }

        return $code;
    }

    public function getCountryFilePath(string $code): string
    {
        $cleanCode = self::sanitizeCode($code);
        return $this->countriesDir . DIRECTORY_SEPARATOR . $cleanCode . '.json';
    }

    public function countryExists(string $code): bool
    {
        return is_file($this->getCountryFilePath($code));
    }

    /**
     * Lists all available country and regional packs with metadata and stats.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listCountries(): array
    {
        $files = glob($this->countriesDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $list = [];

        foreach ($files as $f) {
            $code = strtoupper(pathinfo($f, PATHINFO_FILENAME));
            if (str_ends_with($code, '_CACHE') || str_starts_with($code, '.')) {
                continue;
            }
            $raw = @file_get_contents($f);
            if (!$raw) continue;
            $json = @json_decode($raw, true);
            if (!is_array($json)) continue;

            $m = $json['manifest'] ?? [];
            $name = $m['country_name'] ?? $code;
            $version = $m['version'] ?? '1.0.0';
            $author = $m['author'] ?? 'Alif Farzan Zim';
            $contributors = $m['contributors'] ?? [$author];
            $releaseDate = $m['release_date'] ?? $m['last_updated'] ?? date('Y-m-d');
            $lastUpdated = $m['last_updated'] ?? date('Y-m-d');
            $license = $m['license'] ?? 'CC-BY-4.0';

            $totalPlaces = 0;
            $postcodeCount = 0;
            $typeCounts = [];

            $walk = function($node) use (&$walk, &$totalPlaces, &$postcodeCount, &$typeCounts) {
                $totalPlaces++;
                $t = $node['type'] ?? 'place';
                if (is_array($t)) {
                    foreach ($t as $k) {
                        $lk = strtolower(trim((string)$k));
                        if ($lk !== '') $typeCounts[$lk] = ($typeCounts[$lk] ?? 0) + 1;
                    }
                } elseif (is_string($t)) {
                    $lk = strtolower(trim($t));
                    if ($lk !== '') $typeCounts[$lk] = ($typeCounts[$lk] ?? 0) + 1;
                }
                if (!empty($node['postcode']) || !empty($node['code'])) {
                    $postcodeCount++;
                }
                $subPlaces = $node['places'] ?? $node['children'] ?? [];
                foreach ($subPlaces as $child) {
                    $walk($child);
                }
            };

            foreach ($json['places'] ?? [] as $rootNode) {
                $walk($rootNode);
            }

            arsort($typeCounts);
            $topTypes = array_slice($typeCounts, 0, 5, true);

            $packType = $m['pack_type'] ?? (strlen($code) === 2 ? 'national' : 'custom');

            $list[] = [
                'code' => $code,
                'name' => $name,
                'pack_type' => $packType,
                'code_label' => $m['code_label'] ?? null,
                'version' => $version,
                'author' => $author,
                'contributors' => array_values(array_unique(array_filter((array)$contributors))),
                'release_date' => $releaseDate,
                'last_updated' => $lastUpdated,
                'license' => $license,
                'total_places' => $totalPlaces,
                'postcode_count' => $postcodeCount,
                'top_types' => $topTypes,
                'filesize' => @filesize($f) ?: 0,
            ];
        }

        usort($list, fn($a, $b) => strcmp((string)$a['name'], (string)$b['name']));
        return $list;
    }

    /**
     * Loads a specific country dataset.
     */
    public function loadCountry(string $code): array
    {
        $filePath = $this->getCountryFilePath($code);
        if (!is_file($filePath)) {
            throw new RuntimeException("Country pack '{$code}' not found.");
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new RuntimeException("Unable to read country file '{$filePath}'.");
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException("Malformed JSON syntax in '{$code}.json'.");
        }

        return $data;
    }

    /**
     * Saves changes to a country dataset, updating metadata and timestamps.
     */
    public function saveCountry(string $code, array $data, string $contributor = 'Alif Farzan Zim'): array
    {
        $filePath = $this->getCountryFilePath($code);
        $cleanCode = self::sanitizeCode($code);

        if (!isset($data['manifest']) || !is_array($data['manifest'])) {
            $data['manifest'] = [];
        }

        $today = date('Y-m-d');
        $data['manifest']['last_updated'] = $today;

        $contributor = trim($contributor) !== '' ? trim($contributor) : 'Alif Farzan Zim';
        if (empty($data['manifest']['author'])) {
            $data['manifest']['author'] = $contributor;
        }

        $contributors = $data['manifest']['contributors'] ?? [];
        if (!is_array($contributors)) {
            $contributors = [$contributors];
        }
        if (!in_array($contributor, $contributors, true)) {
            $contributors[] = $contributor;
        }
        $data['manifest']['contributors'] = array_values(array_unique(array_filter($contributors)));

        if (!isset($data['places']) || !is_array($data['places'])) {
            throw new InvalidArgumentException("Country dataset must contain a 'places' array.");
        }

        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException("Failed to encode JSON for '{$cleanCode}'.");
        }

        self::atomicWrite($filePath, $encoded);

        return [
            'success' => true,
            'message' => "Successfully saved {$cleanCode}.json (Updated: {$today})",
            'last_updated' => $today,
            'contributors' => $data['manifest']['contributors'],
        ];
    }

    /**
     * Creates a new blank regional pack with interactive depth tiers.
     */
    public function createBlankCountry(array $params): array
    {
        $code = self::sanitizeCode((string)($params['code'] ?? ''));
        $filePath = $this->getCountryFilePath($code);

        $packType = trim((string)($params['pack_type'] ?? ''));
        if ($packType !== 'national' && $packType !== 'custom') {
            $packType = (strlen($code) === 2) ? 'national' : 'custom';
        }

        $countryName = trim((string)($params['name'] ?? $code));
        $defaultLang = trim((string)($params['default_lang'] ?? 'en')) ?: 'en';
        $localLang = trim((string)($params['local_lang'] ?? 'en')) ?: 'en';
        $contributor = trim((string)($params['contributor'] ?? 'Alif Farzan Zim')) ?: 'Alif Farzan Zim';

        $codeLabel = trim((string)($params['code_label'] ?? ''));
        if ($codeLabel === '') {
            $codeLabel = ($packType === 'custom') ? 'Block Code' : 'Postal Code';
        }

        $license = trim((string)($params['license'] ?? 'CC-BY-4.0')) ?: 'CC-BY-4.0';
        $notes = trim((string)($params['notes'] ?? "Geographic hierarchy for {$countryName}."));

        $levels = [];
        if (!empty($params['depth_levels']) && is_array($params['depth_levels'])) {
            foreach ($params['depth_levels'] as $idx => $lvl) {
                $depthNum = (int)($lvl['depth'] ?? ($idx + 1));
                $rawTypes = $lvl['types'] ?? [];
                $types = [];
                if (is_array($rawTypes)) {
                    foreach ($rawTypes as $t) {
                        $key = strtolower(trim(preg_replace('/[^a-z0-9_]+/i', '_', (string)($t['key'] ?? ''))));
                        $nameEn = trim((string)($t['name_en'] ?? ''));
                        $nameLoc = trim((string)($t['name_loc'] ?? ''));
                        if ($nameEn === '' && $key !== '') $nameEn = ucfirst(str_replace('_', ' ', $key));
                        if ($key === '' && $nameEn !== '') $key = strtolower(trim(preg_replace('/[^a-z0-9_]+/i', '_', $nameEn)));
                        if ($key === '') continue;
                        if ($nameLoc === '') $nameLoc = $nameEn;
                        $types[] = [
                            'key' => $key,
                            'name_en' => $nameEn,
                            'name_loc' => $nameLoc,
                        ];
                    }
                }
                if (empty($types)) {
                    $types[] = [
                        'key' => "level_{$depthNum}",
                        'name_en' => "Level {$depthNum}",
                        'name_loc' => "Level {$depthNum}",
                    ];
                }
                $levels[] = [
                    'depth' => $depthNum,
                    'types' => $types,
                ];
            }
        }

        if (empty($levels)) {
            $levels = [
                ['depth' => 1, 'types' => [['key' => ($packType === 'custom') ? 'zone' : 'state', 'name_en' => ($packType === 'custom') ? 'Zone / Camp' : 'State / Province', 'name_loc' => ($packType === 'custom') ? 'Zone / Camp' : 'State / Province']]],
                ['depth' => 2, 'types' => [['key' => ($packType === 'custom') ? 'block' : 'county', 'name_en' => ($packType === 'custom') ? 'Block / Sector' : 'County / District', 'name_loc' => ($packType === 'custom') ? 'Block / Sector' : 'County / District']]],
                ['depth' => 3, 'types' => [
                    ['key' => ($packType === 'custom') ? 'unit' : 'city', 'name_en' => ($packType === 'custom') ? 'Unit / Shelter' : 'City / Locality', 'name_loc' => ($packType === 'custom') ? 'Unit / Shelter' : 'City / Locality'],
                    ['key' => ($packType === 'custom') ? 'facility' : 'postoffice', 'name_en' => ($packType === 'custom') ? 'Health Facility' : 'Post Office', 'name_loc' => ($packType === 'custom') ? 'Health Facility' : 'Post Office']
                ]],
            ];
        }

        $dataset = [
            'manifest' => [
                'pack_id' => $code,
                'pack_type' => $packType,
                'code_label' => $codeLabel,
                'country_code' => $code,
                'country_name' => $countryName,
                'default_lang' => $defaultLang,
                'local_lang' => $localLang,
                'version' => '1.0.0',
                'release_date' => date('Y-m-d'),
                'last_updated' => date('Y-m-d'),
                'author' => $contributor,
                'contributors' => [$contributor],
                'license' => $license,
                'notes' => $notes,
            ],
            'hierarchy' => [
                'model' => 'recursive_polymorphic',
                'child_key' => 'places',
                'levels' => $levels,
            ],
            'places' => [],
        ];

        $encoded = json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException("Failed to encode JSON for new pack '{$code}'.");
        }
        self::atomicWrite($filePath, $encoded);

        return [
            'success' => true,
            'country_code' => $code,
            'message' => "Created blank pack for {$countryName} ({$code}).",
        ];
    }

    /**
     * Atomically writes data to file via tempfile and exclusive lock.
     * Prevents file corruption, race conditions, and 0-byte truncations on crash/interruption.
     */
    public static function atomicWrite(string $filePath, string $content): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tmpFile = $filePath . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmpFile, $content, LOCK_EX) === false) {
            throw new RuntimeException("Failed to write to temporary file: {$tmpFile}");
        }

        if (!rename($tmpFile, $filePath)) {
            @unlink($tmpFile);
            throw new RuntimeException("Failed to atomically replace target file: {$filePath}");
        }
    }
}
