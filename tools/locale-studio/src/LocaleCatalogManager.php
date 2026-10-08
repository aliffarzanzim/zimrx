<?php
declare(strict_types=1);

namespace ZimRx\LocaleStudio;

use RuntimeException;

class LocaleCatalogManager
{
    private string $localesDir;

    public function __construct(?string $localesDir = null)
    {
        $this->localesDir = $localesDir ?? (dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'locales');
        if (!is_dir($this->localesDir)) {
            mkdir($this->localesDir, 0755, true);
        }
    }

    public function getLocalesDir(): string
    {
        return $this->localesDir;
    }

    /**
     * Sanitizes language code (e.g. 'en', 'bn', 'es-mx', 'zh-cn').
     */
    public static function sanitizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        return preg_replace('/[^a-z0-9_-]/', '', $code) ?: 'en';
    }

    /**
     * Sanitizes catalog file name (e.g. 'instructions.json' or 'instructions').
     * Prevents directory traversal attacks.
     */
    public static function sanitizeCatalogName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\.json$/i', '', $name);
        $name = preg_replace('/[^a-z0-9_]/', '_', $name);
        return trim($name, '_') ?: 'custom_catalog';
    }

    /**
     * Atomically writes content to file using exclusive locking and tempfile renaming.
     * Prevents race conditions, partial writes, and 0-byte file truncations.
     */
    public static function atomicWrite(string $filePath, string $content): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException("Failed to create directory: {$dir}");
            }
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

    /**
     * Lists all available languages with rich metadata and catalog statistics.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listLanguages(): array
    {
        $languages = [];
        $dirs = glob($this->localesDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];

        // Determine all unique catalog JSON files across all languages
        $allCatalogs = $this->discoverAllCatalogFiles();

        foreach ($dirs as $dir) {
            $code = basename($dir);
            if (!preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})?$/i', $code)) {
                continue;
            }

            $manifest = $this->loadManifest($code);
            $localCatalogs = $this->getCatalogsForLanguage($code);

            // Compute statistics
            $totalCatalogs = count($allCatalogs);
            $presentCatalogs = count($localCatalogs);
            $completeCount = 0;
            $verifiedCount = 0;
            $totalKeys = 0;
            $translatedKeys = 0;

            $statusMap = $manifest['catalogs_status'] ?? [];

            foreach ($allCatalogs as $catFile) {
                $status = $statusMap[$catFile] ?? [];
                if (!empty($status['complete'])) {
                    $completeCount++;
                }
                if (!empty($status['verified'])) {
                    $verifiedCount++;
                }

                $filePath = $dir . DIRECTORY_SEPARATOR . $catFile;
                if (is_file($filePath)) {
                    $rawContent = file_get_contents($filePath);
                    if ($rawContent !== false) {
                        $data = json_decode($rawContent, true);
                        if (is_array($data)) {
                            $countStats = $this->countItemStats($data);
                            $totalKeys += $countStats['total'];
                            $translatedKeys += $countStats['translated'];
                        }
                    }
                }
            }

            $completionPct = $totalKeys > 0 ? (int)round(($translatedKeys / $totalKeys) * 100) : 0;
            if ($totalKeys === 0 && $presentCatalogs > 0) {
                $completionPct = 100;
            }

            $languages[] = [
                'code' => $code,
                'name' => (string)($manifest['name'] ?? strtoupper($code)),
                'native_name' => (string)($manifest['native_name'] ?? $manifest['name'] ?? strtoupper($code)),
                'direction' => (string)($manifest['direction'] ?? 'ltr'),
                'version' => (string)($manifest['version'] ?? '1.0.0'),
                'release_date' => (string)($manifest['release_date'] ?? ''),
                'last_updated' => (string)($manifest['last_updated'] ?? ''),
                'author' => (string)($manifest['author'] ?? ''),
                'contributors' => (array)($manifest['contributors'] ?? []),
                'total_catalogs' => $totalCatalogs,
                'present_catalogs' => $presentCatalogs,
                'complete_catalogs' => $completeCount,
                'verified_catalogs' => $verifiedCount,
                'total_keys' => $totalKeys,
                'translated_keys' => $translatedKeys,
                'completion_pct' => $completionPct,
                'catalogs_status' => $statusMap,
            ];
        }

        // Always prioritize 'en' first, then alphabetical
        usort($languages, function($a, $b) {
            if ($a['code'] === 'en') return -1;
            if ($b['code'] === 'en') return 1;
            return strcmp($a['name'], $b['name']);
        });

        return $languages;
    }

    /**
     * Loads the manifest of a language pack.
     *
     * @return array<string, mixed>
     */
    public function loadManifest(string $code): array
    {
        $code = self::sanitizeCode($code);
        $manifestPath = $this->localesDir . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'manifest.json';
        if (is_file($manifestPath)) {
            $raw = file_get_contents($manifestPath);
            if ($raw !== false) {
                $data = json_decode($raw, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        return [
            'code' => $code,
            'name' => strtoupper($code),
            'native_name' => strtoupper($code),
            'direction' => 'ltr',
            'version' => '1.0.0',
            'release_date' => date('Y-m-d'),
            'last_updated' => date('Y-m-d'),
            'author' => 'ZimRx Contributor',
            'contributors' => ['ZimRx Contributor'],
            'catalogs_status' => [],
        ];
    }

    /**
     * Saves the manifest of a language pack atomically.
     *
     * @param array<string, mixed> $data
     */
    public function saveManifest(string $code, array $data): bool
    {
        $code = self::sanitizeCode($code);
        $dir = $this->localesDir . DIRECTORY_SEPARATOR . $code;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $existing = $this->loadManifest($code);
        $merged = array_merge($existing, $data);
        $merged['code'] = $code;
        $merged['last_updated'] = date('Y-m-d');

        $filePath = $dir . DIRECTORY_SEPARATOR . 'manifest.json';
        $json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException("Failed to encode manifest JSON for '{$code}'.");
        }

        self::atomicWrite($filePath, $json);
        return true;
    }

    /**
     * Discovers all unique catalog JSON files (excluding manifest.json) across all languages.
     *
     * @return array<int, string>
     */
    public function discoverAllCatalogFiles(): array
    {
        $catalogs = [];
        $files = glob($this->localesDir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*.json') ?: [];

        foreach ($files as $file) {
            $base = basename($file);
            if ($base === 'manifest.json') {
                continue;
            }
            if (!in_array($base, $catalogs, true)) {
                $catalogs[] = $base;
            }
        }

        // Standard priority sort order
        $order = [
            'instructions.json',
            'first_launch.json',
            'help_guidelines.json',
            'advices.json',
            'doses.json',
            'durations.json',
        ];

        usort($catalogs, function($a, $b) use ($order) {
            $posA = array_search($a, $order, true);
            $posB = array_search($b, $order, true);
            if ($posA !== false && $posB !== false) return $posA <=> $posB;
            if ($posA !== false) return -1;
            if ($posB !== false) return 1;
            return strcmp($a, $b);
        });

        return array_values($catalogs);
    }

    /**
     * Gets all catalog files present for a single language.
     *
     * @return array<int, string>
     */
    public function getCatalogsForLanguage(string $code): array
    {
        $code = self::sanitizeCode($code);
        $dir = $this->localesDir . DIRECTORY_SEPARATOR . $code;
        if (!is_dir($dir)) {
            return [];
        }

        $catalogs = [];
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            $base = basename($file);
            if ($base !== 'manifest.json') {
                $catalogs[] = $base;
            }
        }

        return $catalogs;
    }

    /**
     * Loads a catalog file for a language, returning its parsed array.
     *
     * @return array<string, mixed>
     */
    public function loadCatalog(string $code, string $catalogFile): array
    {
        $code = self::sanitizeCode($code);
        $cleanCatalog = self::sanitizeCatalogName($catalogFile) . '.json';
        $filePath = $this->localesDir . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . $cleanCatalog;

        if (!is_file($filePath)) {
            return [];
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Saves a catalog file for a language atomically.
     *
     * @param array<string, mixed> $data
     */
    public function saveCatalog(
        string $code,
        string $catalogFile,
        array $data,
        ?bool $isComplete = null,
        ?bool $isVerified = null,
        ?string $contributor = null
    ): array {
        $code = self::sanitizeCode($code);
        $cleanCatalog = self::sanitizeCatalogName($catalogFile) . '.json';

        $dir = $this->localesDir . DIRECTORY_SEPARATOR . $code;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = $dir . DIRECTORY_SEPARATOR . $cleanCatalog;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException("Failed to encode JSON for catalog '{$cleanCatalog}'.");
        }

        self::atomicWrite($filePath, $json);

        // Update manifest status
        $manifest = $this->loadManifest($code);
        if (!isset($manifest['catalogs_status']) || !is_array($manifest['catalogs_status'])) {
            $manifest['catalogs_status'] = [];
        }

        $currentStatus = $manifest['catalogs_status'][$cleanCatalog] ?? [];
        if ($isComplete !== null) {
            $currentStatus['complete'] = $isComplete;
        }
        if ($isVerified !== null) {
            $currentStatus['verified'] = $isVerified;
            if ($isVerified && !empty($contributor)) {
                $currentStatus['verified_by'] = $contributor;
            }
        }
        $currentStatus['updated_at'] = date('Y-m-d');
        $manifest['catalogs_status'][$cleanCatalog] = $currentStatus;

        if (!empty($contributor)) {
            $contributors = (array)($manifest['contributors'] ?? []);
            if (!in_array($contributor, $contributors, true)) {
                $contributors[] = $contributor;
            }
            $manifest['contributors'] = array_values(array_unique($contributors));
        }

        $this->saveManifest($code, $manifest);

        return [
            'success' => true,
            'file' => $cleanCatalog,
            'bytes' => strlen($json),
            'status' => $currentStatus,
            'message' => "Successfully saved {$cleanCatalog} for language {$code}.",
        ];
    }

    /**
     * Updates only the complete/verified status of a catalog inside manifest.json.
     */
    public function updateCatalogStatus(
        string $code,
        string $catalogFile,
        ?bool $isComplete,
        ?bool $isVerified,
        ?string $contributor = null
    ): array {
        $code = self::sanitizeCode($code);
        $cleanCatalog = self::sanitizeCatalogName($catalogFile) . '.json';

        $manifest = $this->loadManifest($code);
        if (!isset($manifest['catalogs_status']) || !is_array($manifest['catalogs_status'])) {
            $manifest['catalogs_status'] = [];
        }

        $currentStatus = $manifest['catalogs_status'][$cleanCatalog] ?? [];
        if ($isComplete !== null) {
            $currentStatus['complete'] = $isComplete;
        }
        if ($isVerified !== null) {
            $currentStatus['verified'] = $isVerified;
            if ($isVerified && !empty($contributor)) {
                $currentStatus['verified_by'] = $contributor;
            }
        }
        $currentStatus['updated_at'] = date('Y-m-d');
        $manifest['catalogs_status'][$cleanCatalog] = $currentStatus;

        if (!empty($contributor)) {
            $contributors = (array)($manifest['contributors'] ?? []);
            if (!in_array($contributor, $contributors, true)) {
                $contributors[] = $contributor;
            }
            $manifest['contributors'] = array_values(array_unique($contributors));
        }

        $this->saveManifest($code, $manifest);

        return [
            'success' => true,
            'status' => $currentStatus,
            'message' => "Updated status for {$cleanCatalog}.",
        ];
    }

    /**
     * Creates a new language pack, optionally copying catalog templates from a reference language.
     *
     * @param array<string, mixed> $meta
     */
    public function createLanguage(string $code, array $meta, string $cloneFrom = 'en'): array
    {
        $code = self::sanitizeCode($code);
        $dir = $this->localesDir . DIRECTORY_SEPARATOR . $code;

        if (is_dir($dir)) {
            throw new RuntimeException("Language pack '{$code}' already exists.");
        }

        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create directory for '{$code}'.");
        }

        $manifest = [
            'code' => $code,
            'name' => trim((string)($meta['name'] ?? strtoupper($code))),
            'native_name' => trim((string)($meta['native_name'] ?? $meta['name'] ?? strtoupper($code))),
            'direction' => trim((string)($meta['direction'] ?? 'ltr')),
            'version' => '1.0.0',
            'release_date' => date('Y-m-d'),
            'last_updated' => date('Y-m-d'),
            'author' => trim((string)($meta['author'] ?? 'ZimRx Contributor')),
            'contributors' => [trim((string)($meta['author'] ?? 'ZimRx Contributor'))],
            'catalogs_status' => [],
        ];

        // Scaffold catalog files from reference language if available
        $cloneDir = $this->localesDir . DIRECTORY_SEPARATOR . self::sanitizeCode($cloneFrom);
        if (is_dir($cloneDir)) {
            foreach (glob($cloneDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
                $base = basename($file);
                if ($base === 'manifest.json') continue;

                $rawContent = file_get_contents($file);
                if ($rawContent === false) continue;
                $refData = json_decode($rawContent, true);
                if (is_array($refData)) {
                    // Create empty translation skeleton preserving keys & alias fields
                    $skeleton = $this->createTranslationSkeleton($refData);
                    $destPath = $dir . DIRECTORY_SEPARATOR . $base;
                    $encoded = json_encode($skeleton, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($encoded === false) {
                        throw new RuntimeException("Failed to encode translation skeleton for '{$base}'.");
                    }
                    self::atomicWrite($destPath, $encoded);
                    $manifest['catalogs_status'][$base] = [
                        'complete' => false,
                        'verified' => false,
                        'updated_at' => date('Y-m-d'),
                    ];
                }
            }
        }

        $this->saveManifest($code, $manifest);

        return [
            'success' => true,
            'code' => $code,
            'message' => "Successfully initialized language pack for {$manifest['name']} ({$code}).",
        ];
    }

    /**
     * Creates a new JSON catalog file across all languages or in the reference language.
     */
    public function createCatalogFile(string $catalogName, string $initialLang = 'en'): array
    {
        $cleanName = self::sanitizeCatalogName($catalogName) . '.json';
        $createdIn = [];

        // Add to reference language and any existing language that doesn't have it
        $allLangs = $this->listLanguages();
        foreach ($allLangs as $lang) {
            $dir = $this->localesDir . DIRECTORY_SEPARATOR . $lang['code'];
            $file = $dir . DIRECTORY_SEPARATOR . $cleanName;
            if (!is_file($file)) {
                self::atomicWrite($file, "{\n}\n");
                $createdIn[] = $lang['code'];

                // Update manifest
                $manifest = $this->loadManifest($lang['code']);
                if (!isset($manifest['catalogs_status'])) {
                    $manifest['catalogs_status'] = [];
                }
                $manifest['catalogs_status'][$cleanName] = [
                    'complete' => false,
                    'verified' => false,
                    'updated_at' => date('Y-m-d'),
                ];
                $this->saveManifest($lang['code'], $manifest);
            }
        }

        return [
            'success' => true,
            'catalog_file' => $cleanName,
            'created_in' => $createdIn,
            'message' => "Successfully created catalog '{$cleanName}' in " . count($createdIn) . " languages.",
        ];
    }

    /**
     * Validates all language packs and catalogs.
     *
     * @return array<string, mixed>
     */
    public function validateAllLocales(): array
    {
        $languages = $this->listLanguages();
        $allCatalogs = $this->discoverAllCatalogFiles();
        $refLang = 'en';

        $report = [
            'total_languages' => count($languages),
            'total_catalogs' => count($allCatalogs),
            'languages' => [],
            'has_errors' => false,
        ];

        foreach ($languages as $lang) {
            $code = $lang['code'];
            $langReport = [
                'code' => $code,
                'name' => $lang['name'],
                'errors' => [],
                'warnings' => [],
                'catalogs' => [],
            ];

            // Manifest check
            $manifestPath = $this->localesDir . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'manifest.json';
            if (!is_file($manifestPath)) {
                $langReport['errors'][] = "Missing manifest.json";
                $report['has_errors'] = true;
            }

            foreach ($allCatalogs as $catFile) {
                $filePath = $this->localesDir . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . $catFile;
                if (!is_file($filePath)) {
                    $langReport['warnings'][] = "Catalog {$catFile} is missing in {$code}";
                    continue;
                }

                $raw = file_get_contents($filePath);
                if ($raw === false) {
                    $langReport['errors'][] = "Unreadable catalog file {$catFile}";
                    $report['has_errors'] = true;
                    continue;
                }
                $json = json_decode($raw, true);
                if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
                    $langReport['errors'][] = "Syntax error in {$catFile}: " . json_last_error_msg();
                    $report['has_errors'] = true;
                    continue;
                }

                $stats = $this->countItemStats($json);
                $status = $lang['catalogs_status'][$catFile] ?? [];

                $langReport['catalogs'][$catFile] = [
                    'total' => $stats['total'],
                    'translated' => $stats['translated'],
                    'pct' => $stats['total'] > 0 ? (int)round(($stats['translated'] / $stats['total']) * 100) : 100,
                    'complete' => !empty($status['complete']),
                    'verified' => !empty($status['verified']),
                ];
            }

            $report['languages'][$code] = $langReport;
        }

        return $report;
    }

    /**
     * Helper to count total and translated items in arbitrary JSON catalogs.
     *
     * @return array{total: int, translated: int}
     */
    private function countItemStats(mixed $data): array
    {
        $total = 0;
        $translated = 0;

        if (!is_array($data)) {
            return ['total' => 0, 'translated' => 0];
        }

        foreach ($data as $val) {
            if (is_string($val)) {
                $total++;
                if (trim($val) !== '') $translated++;
            } elseif (is_array($val)) {
                if (isset($val['text']) && is_string($val['text'])) {
                    $total++;
                    if (trim($val['text']) !== '') $translated++;
                } else {
                    $sub = $this->countItemStats($val);
                    $total += $sub['total'];
                    $translated += $sub['translated'];
                }
            }
        }

        return ['total' => $total, 'translated' => $translated];
    }

    /**
     * Recursively creates an empty translation skeleton from reference data.
     */
    private function createTranslationSkeleton(mixed $data): mixed
    {
        if (is_string($data)) {
            return '';
        }

        if (is_array($data)) {
            if (isset($data['text'])) {
                $copy = $data;
                $copy['text'] = '';
                if (isset($copy['alias'])) {
                    $copy['alias'] = '';
                }
                return $copy;
            }

            $out = [];
            foreach ($data as $k => $v) {
                $out[$k] = $this->createTranslationSkeleton($v);
            }
            return $out;
        }

        return $data;
    }
}
