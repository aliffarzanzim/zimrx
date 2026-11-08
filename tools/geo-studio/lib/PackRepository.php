<?php
declare(strict_types=1);

namespace GeoStudio;

use RuntimeException;
use InvalidArgumentException;

require_once __DIR__ . '/ResourceLimits.php';
require_once __DIR__ . '/PackLanguageRules.php';
require_once __DIR__ . '/PackValidator.php';
require_once __DIR__ . '/AtomicFileWriter.php';

final class PackWriteConflict extends RuntimeException {}

final class PackRepository
{
    private const PLACE_FIELDS = ['names', 'type', 'code', 'places'];
    private const MANIFEST_FIELDS = [
        'pack_id', 'pack_type', 'code_label', 'root_names', 'default_lang', 'languages',
        'version', 'release_date', 'last_updated', 'author', 'contributors', 'license', 'sources', 'notes',
    ];
    private const DATASET_FIELDS = ['manifest', 'hierarchy', 'places'];

    private string $packsDir;

    public function __construct(string $packsDir)
    {
        $this->packsDir = rtrim($packsDir, DIRECTORY_SEPARATOR);
        if (!is_dir($this->packsDir)
            && !mkdir($this->packsDir, 0755, true)
            && !is_dir($this->packsDir)) {
            throw new RuntimeException('Unable to create the address pack seed directory.');
        }
    }

    /**
     * Sanitizes and validates a pack identifier.
     * Prevents directory traversal attacks.
     */
    public static function sanitizePackId(string $packId): string
    {
        $packId = strtoupper(trim($packId));
        if (!preg_match('/^[A-Z0-9_-]{2,32}$/', $packId)) {
            throw new InvalidArgumentException("Invalid pack ID '{$packId}'. Use 2-32 letters, numbers, hyphens, or underscores.");
        }

        return $packId;
    }

    private static function orderNameMap(mixed $names, string $defaultLanguage): mixed
    {
        if (!is_array($names) || array_is_list($names) || $defaultLanguage === '') {
            return $names;
        }
        $ordered = [];
        if (array_key_exists($defaultLanguage, $names)) {
            $ordered[$defaultLanguage] = $names[$defaultLanguage];
        }
        foreach ($names as $language => $name) {
            if ($language !== $defaultLanguage) {
                $ordered[$language] = $name;
            }
        }
        return $ordered;
    }

    public static function rootName(array $manifest, ?string $language = null, string $fallback = ''): string
    {
        $names = $manifest['root_names'] ?? [];
        if (!is_array($names)) {
            return $fallback;
        }

        $defaultLanguage = strtolower(trim((string)($manifest['default_lang'] ?? 'en'))) ?: 'en';
        $preferredLanguage = strtolower(trim($language ?? $defaultLanguage)) ?: $defaultLanguage;
        $languageOrder = array_unique([
            $preferredLanguage,
            $defaultLanguage,
            'en',
            ...array_keys($names),
        ]);

        foreach ($languageOrder as $code) {
            $name = $names[$code] ?? null;
            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return $fallback;
    }

    private static function orderPlaceFields(array $place, string $defaultLanguage): array
    {
        $ordered = [];
        foreach (self::PLACE_FIELDS as $field) {
            if (array_key_exists($field, $place)) {
                if ($field === 'names') {
                    $ordered[$field] = self::orderNameMap($place[$field], $defaultLanguage);
                } elseif ($field === 'places') {
                    $ordered[$field] = array_map(
                        static fn(array $child): array => self::orderPlaceFields($child, $defaultLanguage),
                        $place[$field]
                    );
                } else {
                    $ordered[$field] = $place[$field];
                }
            }
        }
        return $ordered;
    }

    public static function canonicalizeDataset(array $data): array
    {
        PackValidator::validatePack($data);
        $defaultLanguage = strtolower(trim((string)($data['manifest']['default_lang'] ?? '')));
        if (isset($data['places']) && is_array($data['places'])) {
            $data['places'] = array_map(
                static fn(array $place): array => self::orderPlaceFields($place, $defaultLanguage),
                $data['places']
            );
        }

        if (isset($data['hierarchy']['levels']) && is_array($data['hierarchy']['levels'])) {
            foreach ($data['hierarchy']['levels'] as &$level) {
                if (!isset($level['types']) || !is_array($level['types'])) {
                    continue;
                }
                foreach ($level['types'] as &$type) {
                    if (array_key_exists('names', $type)) {
                        $type['names'] = self::orderNameMap($type['names'], $defaultLanguage);
                    }
                }
                unset($type);
            }
            unset($level);
        }

        if (isset($data['manifest']) && is_array($data['manifest'])) {
            $manifest = [];
            foreach (self::MANIFEST_FIELDS as $field) {
                if (array_key_exists($field, $data['manifest'])) {
                    $manifest[$field] = $data['manifest'][$field];
                }
            }
            if (isset($manifest['languages']) && is_array($manifest['languages']) && $defaultLanguage !== '') {
                $languages = $manifest['languages'];
                if (in_array($defaultLanguage, $languages, true)) {
                    $manifest['languages'] = [$defaultLanguage, ...array_values(array_filter(
                        $languages,
                        static fn(string $language): bool => $language !== $defaultLanguage
                    ))];
                }
            }
            if (array_key_exists('root_names', $manifest)) {
                $manifest['root_names'] = self::orderNameMap($manifest['root_names'], $defaultLanguage);
            }
            $data['manifest'] = $manifest;
        }

        $ordered = [];
        foreach (self::DATASET_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $ordered[$field] = $data[$field];
            }
        }
        return $ordered;
    }

    public static function encodePack(array $data): string
    {
        $data = self::canonicalizeDataset($data);
        try {
            $encoded = json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $error) {
            throw new RuntimeException('Failed to encode the pack as JSON.', 0, $error);
        }
        PackValidator::assertSize(strlen($encoded));
        return $encoded;
    }

    private function packFilePath(string $packId): string
    {
        $cleanPackId = self::sanitizePackId($packId);
        return $this->packsDir . DIRECTORY_SEPARATOR . 'zimrx_address_pack_' . strtolower($cleanPackId) . '.json';
    }

    /**
     * @return array{
     *     packs: list<array<string, mixed>>,
     *     issues: list<array{file: string, reason: string}>
     * }
     */
    public function getPackCatalog(): array
    {
        $files = glob($this->packsDir . DIRECTORY_SEPARATOR . 'zimrx_address_pack_*.json') ?: [];
        $list = [];
        $issues = [];
        $packLimit = ResourceLimits::packBytes();

        foreach ($files as $f) {
            $filename = basename($f);
            if (preg_match('/^zimrx_address_pack_([a-z0-9_-]{2,32})\.json$/D', $filename, $matches) !== 1) {
                $issues[] = ['file' => $filename, 'reason' => 'Filename does not contain a valid pack ID.'];
                continue;
            }
            $packId = strtoupper($matches[1]);
            $size = @filesize($f);
            if ($size === false) {
                $issues[] = ['file' => $filename, 'reason' => 'Unable to read the file size.'];
                continue;
            }
            if ($size > $packLimit) {
                $issues[] = ['file' => $filename, 'reason' => "File exceeds the {$packLimit} byte pack limit."];
                continue;
            }
            $raw = @file_get_contents($f, false, null, 0, $packLimit + 1);
            if ($raw === false) {
                $issues[] = ['file' => $filename, 'reason' => 'Unable to read the file.'];
                continue;
            }
            if ($raw === '') {
                $issues[] = ['file' => $filename, 'reason' => 'File is empty.'];
                continue;
            }
            if (strlen($raw) > $packLimit) {
                $issues[] = ['file' => $filename, 'reason' => "File exceeds the {$packLimit} byte pack limit."];
                continue;
            }
            try {
                $json = json_decode($raw, true, PackValidator::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
            } catch (\JsonException $error) {
                $issues[] = ['file' => $filename, 'reason' => 'Invalid JSON: ' . $error->getMessage()];
                continue;
            }
            if (!is_array($json)) {
                $issues[] = ['file' => $filename, 'reason' => 'The JSON root must be an object.'];
                continue;
            }
            try {
                PackValidator::validatePack($json);
            } catch (\InvalidArgumentException $error) {
                $issues[] = ['file' => $filename, 'reason' => $error->getMessage()];
                continue;
            }

            if (($json['manifest']['pack_id'] ?? null) !== $packId) {
                $issues[] = ['file' => $filename, 'reason' => 'Manifest pack_id does not match the filename.'];
                continue;
            }

            $m = $json['manifest'];
            $defaultLanguage = $m['default_lang'];
            $name = self::rootName($m, $defaultLanguage, $packId);
            $author = $m['author'];
            $contributors = $m['contributors'];
            $typeLabels = [];
            foreach ($json['hierarchy']['levels'] as $level) {
                foreach ($level['types'] as $type) {
                    $typeLabels[$type['key']] = $type['names'][$defaultLanguage];
                }
            }

            $totalPlaces = 0;
            $codeCount = 0;
            $typeCounts = [];

            $walk = function($node) use (&$walk, &$totalPlaces, &$codeCount, &$typeCounts) {
                $totalPlaces++;
                $t = $node['type'];
                if (is_array($t)) {
                    foreach ($t as $k) {
                        $lk = strtolower(trim((string)$k));
                        if ($lk !== '') $typeCounts[$lk] = ($typeCounts[$lk] ?? 0) + 1;
                    }
                } elseif (is_string($t)) {
                    $lk = strtolower(trim($t));
                    if ($lk !== '') $typeCounts[$lk] = ($typeCounts[$lk] ?? 0) + 1;
                }
                if (isset($node['code']) && $node['code'] !== '') {
                    $codeCount++;
                }
                foreach ($node['places'] as $child) {
                    $walk($child);
                }
            };

            foreach ($json['places'] ?? [] as $rootNode) {
                $walk($rootNode);
            }
            arsort($typeCounts);
            $topTypes = array_slice($typeCounts, 0, 5, true);

            $packType = $m['pack_type'];

            $list[] = [
                'pack_id' => $packId,
                'name' => $name,
                'pack_type' => $packType,
                'code_label' => $m['code_label'],
                'version' => $m['version'],
                'author' => $author,
                'contributors' => array_values(array_unique(array_filter((array)$contributors))),
                'release_date' => $m['release_date'],
                'last_updated' => $m['last_updated'],
                'license' => $m['license'],
                'total_places' => $totalPlaces,
                'code_count' => $codeCount,
                'top_types' => $topTypes,
                'type_labels' => $typeLabels,
                'filesize' => $size,
                'revision' => hash('sha256', $raw),
            ];
        }

        usort($list, fn($a, $b) => strcmp((string)$a['name'], (string)$b['name']));
        return ['packs' => $list, 'issues' => $issues];
    }

    /**
     * Loads a specific pack dataset.
     */
    public function loadPackSnapshot(string $packId): array
    {
        $pack = $this->readPack($packId);
        return ['data' => $pack['data'], 'revision' => hash('sha256', $pack['raw'])];
    }

    private function readPack(string $packId): array
    {
        $packId = self::sanitizePackId($packId);
        $filePath = $this->packFilePath($packId);
        if (!is_file($filePath)) {
            throw new RuntimeException("Pack '{$packId}' not found.");
        }

        $size = filesize($filePath);
        PackValidator::assertSize($size === false ? -1 : $size);
        $raw = file_get_contents($filePath, false, null, 0, ResourceLimits::packBytes() + 1);
        if ($raw === false) {
            throw new RuntimeException("Unable to read pack file '{$filePath}'.");
        }

        PackValidator::assertSize(strlen($raw));
        try {
            $data = json_decode($raw, true, PackValidator::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException("Malformed JSON syntax in '{$packId}.json'.", 0, $e);
        }
        if (!is_array($data)) {
            throw new RuntimeException("Malformed JSON syntax in '{$packId}.json'.");
        }

        $data = self::canonicalizeDataset($data);
        if ($data['manifest']['pack_id'] !== $packId) {
            throw new InvalidArgumentException('Pack manifest pack_id does not match the selected pack.');
        }

        return ['data' => $data, 'raw' => $raw];
    }

    /**
     * Saves changes to a pack dataset, updating metadata and timestamps.
     */
    public function savePack(string $packId, array $data, string $expectedRevision, string $contributor = ''): array
    {
        $filePath = $this->packFilePath($packId);
        $cleanPackId = self::sanitizePackId($packId);

        $manifest = $data['manifest'] ?? null;
        if (!is_array($manifest) || !isset($manifest['pack_id']) || !is_string($manifest['pack_id'])
            || self::sanitizePackId($manifest['pack_id']) !== $cleanPackId) {
            throw new InvalidArgumentException('Pack manifest pack_id does not match the selected pack.');
        }
        PackValidator::validatePack($data);

        $today = date('Y-m-d');
        $data['manifest']['last_updated'] = $today;

        $contributor = trim($contributor);
        if (empty($data['manifest']['author']) && $contributor !== '') {
            $data['manifest']['author'] = $contributor;
        }

        $contributors = $data['manifest']['contributors'];
        if ($contributor !== '' && !in_array($contributor, $contributors, true)) {
            $contributors[] = $contributor;
        }
        $data['manifest']['contributors'] = array_values(array_unique(array_filter($contributors)));

        $encoded = self::encodePack($data);

        self::withWriteLock($filePath, static function () use ($filePath, $expectedRevision, $encoded): void {
            $revision = is_file($filePath) ? hash_file('sha256', $filePath) : false;
            if (!preg_match('/^[a-f0-9]{64}$/', $expectedRevision) || !is_string($revision) || !hash_equals($revision, $expectedRevision)) {
                throw new PackWriteConflict('This pack changed on disk. Reload it before saving to avoid replacing another edit.');
            }
            AtomicFileWriter::write($filePath, [$encoded]);
        });

        return [
            'success' => true,
            'message' => "Successfully saved zimrx_address_pack_" . strtolower($cleanPackId) . ".json (Updated: {$today})",
            'last_updated' => $today,
            'revision' => hash('sha256', $encoded),
            'contributors' => $data['manifest']['contributors'],
        ];
    }

    public function deletePack(string $packId, string $expectedRevision): array
    {
        $cleanPackId = self::sanitizePackId($packId);
        $filePath = $this->packFilePath($cleanPackId);
        self::withWriteLock($filePath, static function () use ($filePath, $expectedRevision): void {
            if (!is_file($filePath)) {
                throw new InvalidArgumentException('Pack not found.');
            }
            $revision = hash_file('sha256', $filePath);
            if (!preg_match('/^[a-f0-9]{64}$/', $expectedRevision)
                || !is_string($revision)
                || !hash_equals($revision, $expectedRevision)) {
                throw new PackWriteConflict('This pack changed on disk. Reload the pack list before deleting it.');
            }
            if (!unlink($filePath)) {
                throw new RuntimeException('Geo Studio could not delete the pack seed.');
            }
        });

        return [
            'success' => true,
            'pack_id' => $cleanPackId,
            'message' => "Deleted zimrx_address_pack_" . strtolower($cleanPackId) . ".json.",
        ];
    }

    private static function buildBlankDataset(array $params, string $packId): array
    {
        $packType = $params['pack_type'] ?? null;
        if (!is_string($packType) || !in_array($packType, ['national', 'custom'], true)) {
            throw new InvalidArgumentException('Blank pack type is required.');
        }

        $defaultLang = $params['default_lang'] ?? null;
        if (!is_string($defaultLang) || trim($defaultLang) === '') {
            throw new InvalidArgumentException('A default language is required.');
        }
        $languages = PackLanguageRules::normalizeLanguageList($params['languages'] ?? null);
        $defaultLang = strtolower(trim($defaultLang));
        if (!in_array($defaultLang, $languages, true)) {
            throw new InvalidArgumentException('Default language must be included in the pack languages.');
        }
        $languages = [$defaultLang, ...array_values(array_filter($languages, static fn(string $language): bool => $language !== $defaultLang))];
        $suppliedRootNames = PackLanguageRules::normalizeNameMap($params['root_names'] ?? null, $languages, true, $defaultLang, true);
        $rootNames = [$defaultLang => $suppliedRootNames[$defaultLang]];
        foreach ($suppliedRootNames as $language => $name) {
            if ($language !== $defaultLang) {
                $rootNames[$language] = $name;
            }
        }
        $rootName = $rootNames[$defaultLang];
        if ($packType === 'national' && preg_match('/^[A-Z]{2}$/D', $packId) !== 1) {
            throw new InvalidArgumentException('A national pack ID must be its two-letter country code.');
        }
        $contributor = trim((string)($params['contributor'] ?? ''));

        $codeLabel = trim((string)($params['code_label'] ?? ''));
        if ($codeLabel === '') {
            $codeLabel = $packType === 'custom' ? 'Block Code' : 'Postal Code';
        }

        $license = trim((string)($params['license'] ?? 'CC-BY-4.0')) ?: 'CC-BY-4.0';
        $notes = trim((string)($params['notes'] ?? "Geographic hierarchy pack for {$rootName}."));

        $rawLevels = $params['depth_levels'] ?? null;
        if (!is_array($rawLevels) || !array_is_list($rawLevels) || $rawLevels === []
            || count($rawLevels) > PackValidator::MAX_DEPTH) {
            throw new InvalidArgumentException('Blank packs need between 1 and 64 hierarchy levels.');
        }
        $levels = [];
        $seenKeys = [];
        foreach ($rawLevels as $index => $level) {
            if (!is_array($level) || array_is_list($level)
                || array_diff(array_keys($level), ['depth', 'types']) !== []
                || ($level['depth'] ?? null) !== $index + 1
                || !is_array($level['types'] ?? null) || !array_is_list($level['types'])
                || $level['types'] === [] || count($level['types']) > 512) {
                throw new InvalidArgumentException('Hierarchy levels must be ordered and contain at least one type.');
            }

            $types = [];
            foreach ($level['types'] as $type) {
                if (!is_array($type) || array_is_list($type)
                    || array_diff(array_keys($type), ['key', 'names']) !== []
                    || !is_string($type['key'] ?? null)
                    || !preg_match('/^[a-z0-9_]{1,64}$/', $type['key'])
                    || isset($seenKeys[$type['key']])
                    || !is_array($type['names'] ?? null)) {
                    throw new InvalidArgumentException('Hierarchy type definitions need unique keys and language names.');
                }
                $seenKeys[$type['key']] = true;
                $types[] = [
                    'key' => $type['key'],
                    'names' => PackLanguageRules::normalizeNameMap($type['names'], $languages, true, $defaultLang),
                ];
            }
            $levels[] = ['depth' => $index + 1, 'types' => $types];
        }

        return [
            'manifest' => [
                'pack_id' => $packId,
                'pack_type' => $packType,
                'code_label' => $codeLabel,
                'root_names' => $rootNames,
                'default_lang' => $defaultLang,
                'languages' => $languages,
                'version' => '1.0.0',
                'release_date' => date('Y-m-d'),
                'last_updated' => date('Y-m-d'),
                'author' => $contributor,
                'contributors' => $contributor === '' ? [] : [$contributor],
                'license' => $license,
                'sources' => [],
                'notes' => $notes,
            ],
            'hierarchy' => ['levels' => $levels],
            'places' => [],
        ];
    }

    /** Creates and persists a blank pack with the submitted hierarchy definition. */
    public function createBlankPack(array $params): array
    {
        $packId = self::sanitizePackId((string)($params['pack_id'] ?? ''));
        $filePath = $this->packFilePath($packId);
        $params['pack_id'] = $packId;
        $dataset = self::buildBlankDataset($params, $packId);
        $rootName = self::rootName($dataset['manifest'], null, $packId);

        $encoded = self::encodePack($dataset);
        self::withWriteLock($filePath, static function () use ($filePath, $params, $encoded): void {
            $exists = is_file($filePath);
            $confirmed = ($params['overwrite_confirmed'] ?? '0') === '1';
            if ($exists !== $confirmed) {
                throw new PackWriteConflict($exists
                    ? 'This pack already exists. Confirm replacement before proceeding.'
                    : 'The pack changed after confirmation. Reload the pack list and try again.');
            }
            if ($confirmed) {
                $expectedRevision = $params['overwrite_revision'] ?? '';
                $currentRevision = hash_file('sha256', $filePath);
                if (!is_string($expectedRevision)
                    || !preg_match('/^[a-f0-9]{64}$/', $expectedRevision)
                    || !is_string($currentRevision)
                    || !hash_equals($currentRevision, $expectedRevision)) {
                    throw new PackWriteConflict('This pack changed after confirmation. Reload the pack list before replacing it.');
                }
            }
            AtomicFileWriter::write($filePath, [$encoded]);
        });

        return [
            'success' => true,
            'pack_id' => $packId,
            'message' => "Created blank pack for {$rootName} ({$packId}).",
        ];
    }

    private static function withWriteLock(string $packPath, callable $operation): mixed
    {
        $lock = fopen($packPath . '.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Unable to open pack write lock.');
        }

        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                throw new PackWriteConflict('This pack is being updated. Try again when the operation finishes.');
            }
            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

}
