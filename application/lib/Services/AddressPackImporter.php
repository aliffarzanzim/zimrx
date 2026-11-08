<?php
declare(strict_types=1);

namespace ZimRx\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class AddressPackImporter
{
    public const MAX_PACK_BYTES = 4194304;
    private const MAX_PLACES = 20000;
    private const MAX_DEPTH = 64;
    public const MAX_JSON_DEPTH = 208;

    private const PACK_FIELDS = ['manifest', 'hierarchy', 'places'];
    private const MANIFEST_FIELDS = [
        'pack_id', 'pack_type', 'code_label', 'root_names', 'default_lang', 'languages',
        'version', 'release_date', 'last_updated', 'author', 'contributors', 'license', 'sources', 'notes',
    ];

    /** @return list<array{pack_id:string,path:string,filename:string,hash:string,manifest:array,levels:array}> */
    public static function catalog(string $seedsDirectory): array
    {
        $paths = glob(rtrim($seedsDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'zimrx_address_pack_*.json') ?: [];
        sort($paths, SORT_STRING);
        $catalog = [];
        $seenPackIds = [];

        foreach ($paths as $path) {
            if (preg_match('/^zimrx_address_pack_([a-z0-9_-]{2,32})\.json$/D', basename($path), $matches) !== 1) {
                throw new RuntimeException('An address pack seed has an invalid filename.');
            }
            $packId = strtoupper($matches[1]);
            if (isset($seenPackIds[$packId])) {
                throw new RuntimeException('Address pack IDs must be unique across seed files.');
            }
            $seenPackIds[$packId] = true;
            $catalog[] = self::catalogEntry($path, $packId);
        }

        return $catalog;
    }

    public static function catalogDigest(array $catalog): string
    {
        $context = hash_init('sha256');
        foreach ($catalog as $seed) {
            hash_update($context, $seed['filename'] . "\0" . $seed['hash'] . "\n");
        }
        return hash_final($context);
    }

    public static function catalogEntry(string $path, string $expectedPackId): array
    {
        $loaded = self::readPack($path, $expectedPackId);
        $manifest = $loaded['data']['manifest'];
        $entry = [
            'pack_id' => $expectedPackId,
            'path' => $path,
            'filename' => basename($path),
            'hash' => hash('sha256', $loaded['raw']),
            'manifest' => $manifest,
            'levels' => self::databaseLevels($loaded['data']['hierarchy']['levels']),
        ];
        unset($loaded);
        return $entry;
    }

    public static function validatePackData(array $data, string $expectedPackId): void
    {
        self::validatePack($data, $expectedPackId);
    }

    public static function import(PDO $pdo, array $catalog, string $target = 'system'): array
    {
        $tables = match ($target) {
            'system' => [
                'packs' => 'zimrx_address_packs',
                'places' => 'zimrx_address_places',
                'names' => 'zimrx_address_place_names',
            ],
            'user' => [
                'packs' => 'zimrx_user_address_packs',
                'places' => 'zimrx_user_address_places',
                'names' => 'zimrx_user_address_place_names',
            ],
            default => throw new InvalidArgumentException('Unknown address pack database target.'),
        };
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        $packsTable = $tables['packs'];
        $placesTable = $tables['places'];
        $namesTable = $tables['names'];
        $insertPack = $pdo->prepare(<<<SQL
INSERT INTO {$packsTable} (
    pack_id, pack_type, code_label, default_language,
    languages_json, root_names_json, levels_json, version, release_date,
    last_updated, author, contributors_json, license, sources_json, notes
) VALUES (
    :pack_id, :pack_type, :code_label, :default_language,
    :languages_json, :root_names_json, :levels_json, :version, :release_date,
    :last_updated, :author, :contributors_json, :license, :sources_json, :notes
)
SQL);
        $deletePack = $pdo->prepare("DELETE FROM {$packsTable} WHERE pack_id = :pack_id");
        $insertPlace = $pdo->prepare(<<<SQL
INSERT INTO {$placesTable} (pack_id, parent_id, level, type_key, code)
VALUES (:pack_id, :parent_id, :level, :type_key, :code)
SQL);
        $insertName = $pdo->prepare(
            "INSERT INTO {$namesTable} (place_id, language, name) VALUES (:place_id, :language, :name)"
        );

        $counts = ['packs' => 0, 'places' => 0, 'statements' => 0];
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            foreach ($catalog as $seed) {
                $loaded = self::readPack($seed['path'], $seed['pack_id']);
                if (!hash_equals($seed['hash'], hash('sha256', $loaded['raw']))) {
                    throw new RuntimeException('An address pack changed while the database was being built.');
                }
                $data = $loaded['data'];
                $manifest = $data['manifest'];
                $packId = $seed['pack_id'];

                $deletePack->execute(['pack_id' => $packId]);
                $counts['statements']++;
                $insertPack->execute([
                    'pack_id' => $packId,
                    'pack_type' => $manifest['pack_type'],
                    'code_label' => $manifest['code_label'],
                    'default_language' => $manifest['default_lang'],
                    'languages_json' => self::encode($manifest['languages']),
                    'root_names_json' => self::encode($manifest['root_names']),
                    'levels_json' => self::encode($seed['levels']),
                    'version' => $manifest['version'],
                    'release_date' => $manifest['release_date'],
                    'last_updated' => $manifest['last_updated'],
                    'author' => $manifest['author'],
                    'contributors_json' => self::encode($manifest['contributors']),
                    'license' => $manifest['license'],
                    'sources_json' => self::encode($manifest['sources']),
                    'notes' => $manifest['notes'],
                ]);
                $counts['statements']++;

                $walk = static function (array $nodes, ?int $parentId, int $level) use (
                    &$walk,
                    $pdo,
                    $insertPlace,
                    $insertName,
                    $packId,
                    &$counts
                ): void {
                    foreach ($nodes as $node) {
                        $types = is_array($node['type']) ? $node['type'] : [$node['type']];
                        $typeKey = implode(',', array_map('trim', $types));
                        $code = isset($node['code']) ? trim($node['code']) : '';
                        $insertPlace->execute([
                            'pack_id' => $packId,
                            'parent_id' => $parentId,
                            'level' => $level,
                            'type_key' => $typeKey,
                            'code' => $code === '' ? null : $code,
                        ]);
                        $placeId = (int)$pdo->lastInsertId();
                        $counts['places']++;
                        $counts['statements']++;

                        foreach ($node['names'] as $language => $name) {
                            $insertName->execute([
                                'place_id' => $placeId,
                                'language' => $language,
                                'name' => trim($name),
                            ]);
                            $counts['statements']++;
                        }
                        $walk($node['places'], $placeId, $level + 1);
                    }
                };
                $walk($data['places'], null, 1);
                unset($loaded, $data, $manifest, $walk);
                $counts['packs']++;
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

        return $counts + ['digest' => self::catalogDigest($catalog)];
    }

    private static function readPack(string $path, string $expectedPackId): array
    {
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > self::MAX_PACK_BYTES) {
            throw new RuntimeException('An address pack seed exceeds the supported file size.');
        }
        $raw = file_get_contents($path);
        if ($raw === false || strlen($raw) > self::MAX_PACK_BYTES) {
            throw new RuntimeException('An address pack seed could not be read.');
        }
        try {
            $data = json_decode($raw, true, self::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new RuntimeException('An address pack seed contains invalid JSON.', 0, $error);
        }
        if (!is_array($data)) {
            throw new RuntimeException('An address pack seed must contain a JSON object.');
        }
        self::validatePack($data, $expectedPackId);
        return ['raw' => $raw, 'data' => $data];
    }

    private static function validatePack(array $data, string $expectedPackId): void
    {
        self::assertOnlyKeys($data, self::PACK_FIELDS, 'Pack');
        $manifest = $data['manifest'] ?? null;
        if (!is_array($manifest) || $manifest === [] || array_is_list($manifest)) {
            throw new RuntimeException('An address pack must have a manifest object.');
        }
        self::assertOnlyKeys($manifest, self::MANIFEST_FIELDS, 'Pack manifest');
        $required = [
            'pack_id', 'pack_type', 'code_label', 'root_names', 'default_lang', 'languages',
            'version', 'release_date', 'last_updated', 'author', 'contributors', 'license', 'sources', 'notes',
        ];
        foreach ($required as $field) {
            if (!array_key_exists($field, $manifest)) {
                throw new RuntimeException("Address pack manifest is missing '{$field}'.");
            }
        }
        foreach (['pack_id', 'pack_type', 'code_label', 'default_lang', 'version', 'release_date', 'last_updated', 'author', 'license', 'notes'] as $field) {
            if (!is_string($manifest[$field])) {
                throw new RuntimeException("Address pack manifest field '{$field}' must be text.");
            }
        }
        if ($manifest['pack_id'] !== $expectedPackId
            || preg_match('/^[A-Z0-9_-]{2,32}$/D', $manifest['pack_id']) !== 1) {
            throw new RuntimeException('An address pack ID does not match its filename.');
        }
        if (!in_array($manifest['pack_type'], ['national', 'custom'], true)
            || trim($manifest['code_label']) === '') {
            throw new RuntimeException('Address pack type and code label are invalid.');
        }
        if ($manifest['pack_type'] === 'national' && preg_match('/^[A-Z]{2}$/D', $manifest['pack_id']) !== 1) {
            throw new RuntimeException('National address pack IDs must be their two-letter country code.');
        }
        foreach (['contributors', 'sources'] as $field) {
            if (!is_array($manifest[$field]) || !array_is_list($manifest[$field])) {
                throw new RuntimeException("Address pack manifest '{$field}' must be a list.");
            }
            foreach ($manifest[$field] as $value) {
                if (!is_string($value)) {
                    throw new RuntimeException("Address pack manifest '{$field}' must contain text values.");
                }
            }
        }

        $languages = $manifest['languages'];
        $defaultLanguage = $manifest['default_lang'];
        if (!is_array($languages) || !array_is_list($languages) || $languages === [] || count($languages) > 64) {
            throw new RuntimeException('Address pack languages must be a list of 1 to 64 codes.');
        }
        $seenLanguages = [];
        foreach ($languages as $language) {
            if (!is_string($language) || strlen($language) > 35
                || preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/D', $language) !== 1
                || isset($seenLanguages[$language])) {
                throw new RuntimeException('Address pack language codes must be unique lowercase codes.');
            }
            $seenLanguages[$language] = true;
        }
        if (!isset($seenLanguages[$defaultLanguage])) {
            throw new RuntimeException('The address pack default language must be supported by the pack.');
        }
        self::validateNameMap($manifest['root_names'], $seenLanguages, $defaultLanguage, true);

        $hierarchy = $data['hierarchy'] ?? null;
        if (!is_array($hierarchy) || $hierarchy === [] || array_is_list($hierarchy)) {
            throw new RuntimeException('Address pack hierarchy must be an object.');
        }
        self::assertOnlyKeys($hierarchy, ['levels'], 'Pack hierarchy');
        $levels = $hierarchy['levels'] ?? null;
        if (!is_array($levels) || !array_is_list($levels) || $levels === [] || count($levels) > self::MAX_DEPTH) {
            throw new RuntimeException('Address pack hierarchy must define 1 to 64 levels.');
        }
        $typeKeys = [];
        foreach ($levels as $index => $level) {
            if (!is_array($level) || array_is_list($level)
                || ($level['depth'] ?? null) !== $index + 1
                || !is_array($level['types'] ?? null) || !array_is_list($level['types'])
                || $level['types'] === [] || count($level['types']) > 512) {
                throw new RuntimeException('Address pack levels must be consecutive and define place types.');
            }
            self::assertOnlyKeys($level, ['depth', 'types'], 'Hierarchy level');
            foreach ($level['types'] as $type) {
                if (!is_array($type) || array_is_list($type) || !is_string($type['key'] ?? null)
                    || preg_match('/^[a-z0-9_]{1,64}$/D', $type['key']) !== 1 || isset($typeKeys[$type['key']])) {
                    throw new RuntimeException('Address pack place type keys must be unique lowercase identifiers.');
                }
                self::assertOnlyKeys($type, ['key', 'names'], 'Place type');
                self::validateNameMap($type['names'] ?? null, $seenLanguages, $defaultLanguage);
                $typeKeys[$type['key']] = true;
            }
        }

        $places = $data['places'] ?? null;
        if (!is_array($places) || !array_is_list($places)) {
            throw new RuntimeException('Address pack places must be a list.');
        }
        $placeCount = 0;
        $validatePlace = static function (mixed $node, int $depth) use (&$validatePlace, &$placeCount, $typeKeys, $seenLanguages, $defaultLanguage): void {
            if (!is_array($node) || array_is_list($node) || $depth > self::MAX_DEPTH
                || ++$placeCount > self::MAX_PLACES) {
                throw new RuntimeException('Address pack contains an invalid place or exceeds its size limits.');
            }
            self::assertOnlyKeys($node, ['names', 'type', 'code', 'places'], 'Place');
            self::validateNameMap($node['names'] ?? null, $seenLanguages, $defaultLanguage);
            if (!array_key_exists('type', $node)) {
                throw new RuntimeException('Every address place must declare a type.');
            }
            $types = is_array($node['type']) ? $node['type'] : [$node['type']];
            if ($types === [] || !array_is_list($types)) {
                throw new RuntimeException('Place types must be a name or a list of names.');
            }
            $seenPlaceTypes = [];
            foreach ($types as $type) {
                if (!is_string($type) || !isset($typeKeys[$type]) || isset($seenPlaceTypes[$type])) {
                    throw new RuntimeException('Every place type must have one unique hierarchy definition.');
                }
                $seenPlaceTypes[$type] = true;
            }
            if (array_key_exists('code', $node) && $node['code'] !== null && !is_string($node['code'])) {
                throw new RuntimeException('Place codes must be text or null.');
            }
            if (preg_match('/[\x00-\x1F\x7F]/', (string)($node['code'] ?? '')) === 1) {
                throw new RuntimeException('Place codes must not contain control characters.');
            }
            if (!is_array($node['places'] ?? null) || !array_is_list($node['places'])) {
                throw new RuntimeException('Every address place must contain a child places list.');
            }
            foreach ($node['places'] as $child) {
                $validatePlace($child, $depth + 1);
            }
        };
        foreach ($places as $place) {
            $validatePlace($place, 1);
        }
    }

    private static function validateNameMap(mixed $names, array $languages, string $defaultLanguage, bool $allLanguages = false): void
    {
        if (!is_array($names) || $names === [] || array_is_list($names)) {
            throw new RuntimeException('Localized pack names must be a language-to-text object.');
        }
        foreach ($names as $language => $name) {
            if (!is_string($language) || !isset($languages[$language]) || !is_string($name)
                || trim($name) === '' || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
                throw new RuntimeException('Localized pack names must use supported languages and non-empty text.');
            }
        }
        if (!isset($names[$defaultLanguage]) || ($allLanguages && array_diff(array_keys($languages), array_keys($names)) !== [])) {
            throw new RuntimeException('Localized pack names must include the required languages.');
        }
    }

    public static function databaseLevels(array $levels): array
    {
        return array_map(static fn(array $level): array => [
            'depth' => $level['depth'],
            'types' => array_map(static fn(array $type): array => [
                'key' => $type['key'],
                'labels' => $type['names'],
            ], $level['types']),
        ], $levels);
    }

    private static function encode(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $error) {
            throw new RuntimeException('Address pack metadata could not be encoded for the database.', 0, $error);
        }
    }

    private static function assertOnlyKeys(array $value, array $allowed, string $context): void
    {
        foreach (array_keys($value) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                throw new RuntimeException("{$context} contains unsupported field '{$key}'.");
            }
        }
    }
}
