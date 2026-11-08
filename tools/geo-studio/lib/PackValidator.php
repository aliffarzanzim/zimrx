<?php
declare(strict_types=1);

namespace GeoStudio;

use InvalidArgumentException;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'ResourceLimits.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'PackLanguageRules.php';

final class PackValidator
{
    public const MAX_DEPTH = 64;
    public const MAX_JSON_DEPTH = (self::MAX_DEPTH * 3) + 16;
    private const MAX_TYPES_PER_LEVEL = 512;

    private const DATASET_FIELDS = ['manifest', 'hierarchy', 'places'];
    private const MANIFEST_FIELDS = [
        'pack_id', 'pack_type', 'code_label', 'root_names', 'default_lang', 'languages',
        'version', 'release_date', 'last_updated', 'author', 'contributors', 'license', 'sources', 'notes',
    ];

    public static function assertSize(int $bytes): void
    {
        $limit = ResourceLimits::packBytes();
        if ($bytes < 0 || $bytes > $limit) {
            throw new InvalidArgumentException("Pack exceeds the configured {$limit} byte limit.");
        }
    }

    private static function validate(array $data): void
    {
        if (!isset($data['manifest']) || !is_array($data['manifest']) || array_is_list($data['manifest'])) {
            throw new InvalidArgumentException('Pack manifest must be an object.');
        }
        $manifest = $data['manifest'];
        self::assertOnlyKeys($manifest, self::MANIFEST_FIELDS, 'Manifest');
        foreach (['pack_id', 'pack_type', 'code_label', 'author', 'license', 'version', 'release_date', 'last_updated', 'default_lang', 'notes'] as $field) {
            if (array_key_exists($field, $manifest) && !is_string($manifest[$field])) {
                throw new InvalidArgumentException("Manifest field '{$field}' must be a string.");
            }
        }
        foreach (['contributors', 'sources'] as $field) {
            if (array_key_exists($field, $manifest)) {
                $values = $manifest[$field];
                if (!is_array($values) || !array_is_list($values)) {
                    throw new InvalidArgumentException("Manifest field '{$field}' must be a list of strings.");
                }
                foreach ($values as $value) {
                    if (!is_string($value)) {
                        throw new InvalidArgumentException("Manifest field '{$field}' must contain only strings.");
                    }
                }
            }
        }
        if (!isset($manifest['languages']) || !is_array($manifest['languages'])
            || !array_is_list($manifest['languages'])) {
            throw new InvalidArgumentException('Manifest languages must be a list of language codes.');
        }
        $languages = PackLanguageRules::normalizeLanguageList($manifest['languages']);
        if ($languages !== $manifest['languages']) {
            throw new InvalidArgumentException('Manifest languages must use unique lowercase language codes.');
        }
        $rawDefaultLanguage = $manifest['default_lang'] ?? null;
        $defaultLanguage = is_string($rawDefaultLanguage) ? strtolower(trim($rawDefaultLanguage)) : '';
        if ($rawDefaultLanguage !== $defaultLanguage) {
            throw new InvalidArgumentException('Manifest default_lang must use a lowercase language code.');
        }
        if (!in_array($defaultLanguage, $languages, true)) {
            throw new InvalidArgumentException('Manifest default_lang must be included in manifest.languages.');
        }
        $typeDepths = [];
        if (array_key_exists('root_names', $manifest)) {
            $rootNames = $manifest['root_names'];
            if (!is_array($rootNames) || array_is_list($rootNames)) {
                throw new InvalidArgumentException('Manifest root_names must be a language-to-name object.');
            }
            try {
                PackLanguageRules::normalizeNameMap($rootNames, $languages, true, $defaultLanguage, true);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException('Manifest root_names must contain valid pack labels.', 0, $e);
            }
        }
        if (!isset($data['places']) || !is_array($data['places']) || !array_is_list($data['places'])) {
            throw new InvalidArgumentException("Pack must contain a 'places' array.");
        }
        if (isset($data['hierarchy'])) {
            $hierarchy = $data['hierarchy'];
            if (!is_array($hierarchy) || array_is_list($hierarchy)) {
                throw new InvalidArgumentException('Pack hierarchy must be an object.');
            }
            self::assertOnlyKeys($hierarchy, ['levels'], 'Hierarchy');
            if (isset($hierarchy['levels'])) {
                if (!is_array($hierarchy['levels']) || !array_is_list($hierarchy['levels'])
                    || $hierarchy['levels'] === []
                    || count($hierarchy['levels']) > self::MAX_DEPTH) {
                    throw new InvalidArgumentException('Hierarchy level definitions must be a list of 1 to 64 levels.');
                }
                foreach ($hierarchy['levels'] as $index => $level) {
                    if (!is_array($level) || !isset($level['depth']) || !is_int($level['depth'])
                        || $level['depth'] !== $index + 1
                        || !isset($level['types']) || !is_array($level['types']) || $level['types'] === []
                        || !array_is_list($level['types']) || count($level['types']) > self::MAX_TYPES_PER_LEVEL) {
                        throw new InvalidArgumentException('Hierarchy levels must be numbered consecutively and define a list of at most 512 types.');
                    }
                    self::assertOnlyKeys($level, ['depth', 'types'], 'Hierarchy level');
                    foreach ($level['types'] as $type) {
                        if (!is_array($type) || !isset($type['key'], $type['names'])
                            || !is_string($type['key']) || !preg_match('/^[a-z0-9_]{1,64}$/', $type['key'])
                            || isset($typeDepths[$type['key']])
                            || !is_array($type['names'])) {
                            throw new InvalidArgumentException('Hierarchy type keys must be unique lowercase identifiers with valid names.');
                        }
                        self::assertOnlyKeys($type, ['key', 'names'], 'Hierarchy type');
                        try {
                            PackLanguageRules::normalizeNameMap($type['names'], $languages, true, $defaultLanguage);
                        } catch (InvalidArgumentException $e) {
                            throw new InvalidArgumentException('Hierarchy type definitions have invalid names or keys.', 0, $e);
                        }
                        $typeDepths[$type['key']] = $index + 1;
                    }
                }
            }
        }

        $nodeCount = 0;
        $nodeLimit = ResourceLimits::places();
        $levelCount = count($data['hierarchy']['levels'] ?? []);
        $validateNode = function (mixed $node, int $depth) use (&$validateNode, &$nodeCount, $nodeLimit, $languages, $defaultLanguage, $typeDepths, $levelCount): void {
            if (!is_array($node) || array_is_list($node) || $depth > self::MAX_DEPTH) {
                throw new InvalidArgumentException('Pack hierarchy contains an invalid node or exceeds 64 levels.');
            }
            if (++$nodeCount > $nodeLimit) {
                throw new InvalidArgumentException("Pack hierarchy exceeds the configured {$nodeLimit} place limit.");
            }
            self::assertOnlyKeys($node, ['names', 'type', 'code', 'places'], 'Place');
            if (!isset($node['names']) || !is_array($node['names'])) {
                throw new InvalidArgumentException('Every place must contain a names object.');
            }
            try {
                PackLanguageRules::normalizeNameMap($node['names'], $languages, true, $defaultLanguage);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException('Every place needs valid names including the default language.', 0, $e);
            }
            if (array_key_exists('code', $node) && $node['code'] !== null && !is_string($node['code'])) {
                throw new InvalidArgumentException('Place codes must be strings or null.');
            }
            if (is_string($node['code'] ?? null) && preg_match('/[\x00-\x1F\x7F]/', $node['code']) === 1) {
                throw new InvalidArgumentException('Place codes must not contain control characters.');
            }
            if (!array_key_exists('type', $node)) {
                throw new InvalidArgumentException('Every place must declare its type.');
            }
            $types = is_array($node['type']) ? $node['type'] : [$node['type']];
            if ($types === [] || !array_is_list($types)) {
                throw new InvalidArgumentException('Place types must be a name or a list of names.');
            }
            $seenPlaceTypes = [];
            foreach ($types as $type) {
                if (!is_string($type) || !isset($typeDepths[$type])) {
                    throw new InvalidArgumentException('Every place type must have a hierarchy definition.');
                }
                if ($typeDepths[$type] !== $depth || $depth > $levelCount) {
                    throw new InvalidArgumentException('Every place type must be defined at the place hierarchy depth.');
                }
                if (isset($seenPlaceTypes[$type])) throw new InvalidArgumentException('Place types must not be repeated.');
                $seenPlaceTypes[$type] = true;
            }

            if (!isset($node['places']) || !is_array($node['places']) || !array_is_list($node['places'])) {
                throw new InvalidArgumentException("Every place must contain a 'places' array.");
            }
            foreach ($node['places'] as $child) {
                $validateNode($child, $depth + 1);
            }
        };

        foreach ($data['places'] as $place) {
            $validateNode($place, 1);
        }
    }

    public static function validatePack(array $data): void
    {
        self::assertOnlyKeys($data, self::DATASET_FIELDS, 'Pack');
        self::validate($data);

        if (!isset($data['manifest']) || !is_array($data['manifest'])) {
            throw new InvalidArgumentException('Pack must contain a manifest object.');
        }
        $manifest = $data['manifest'];
        $requiredFields = [
            'pack_id', 'pack_type', 'code_label', 'root_names', 'default_lang', 'languages',
            'version', 'release_date', 'last_updated', 'author', 'contributors', 'license', 'sources', 'notes',
        ];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $manifest)) {
                throw new InvalidArgumentException("Pack manifest is missing '{$field}'.");
            }
        }
        foreach (['pack_id', 'pack_type', 'code_label', 'version', 'release_date', 'last_updated', 'author', 'license', 'notes'] as $field) {
            if (!is_string($manifest[$field])) {
                throw new InvalidArgumentException("Manifest field '{$field}' must be a string.");
            }
        }
        if ($manifest['pack_id'] === '' || !preg_match('/^[A-Z0-9_-]{2,32}$/', $manifest['pack_id'])) {
            throw new InvalidArgumentException('Manifest pack_id must be a 2-32 character uppercase identifier.');
        }
        if (!in_array($manifest['pack_type'], ['national', 'custom'], true)) {
            throw new InvalidArgumentException("Manifest pack_type must be 'national' or 'custom'.");
        }
        if ($manifest['pack_type'] === 'national' && preg_match('/^[A-Z]{2}$/D', $manifest['pack_id']) !== 1) {
            throw new InvalidArgumentException('National pack IDs must be their two-letter country code.');
        }
        if (trim($manifest['code_label']) === '') {
            throw new InvalidArgumentException('Manifest code_label must not be empty.');
        }
        if (trim($manifest['license']) === '') {
            throw new InvalidArgumentException('Manifest license must not be empty.');
        }
        if (!is_array($manifest['root_names']) || array_is_list($manifest['root_names'])) {
            throw new InvalidArgumentException('Manifest root_names must be a language-to-name object.');
        }
        if (!is_array($manifest['contributors']) || !array_is_list($manifest['contributors'])
            || !is_array($manifest['sources']) || !array_is_list($manifest['sources'])) {
            throw new InvalidArgumentException('Manifest contributors and sources must be lists.');
        }
        if (!isset($data['hierarchy']) || !is_array($data['hierarchy'])
            || !isset($data['hierarchy']['levels']) || !is_array($data['hierarchy']['levels'])
            || !array_is_list($data['hierarchy']['levels']) || $data['hierarchy']['levels'] === []) {
            throw new InvalidArgumentException('Pack must contain hierarchy levels.');
        }
    }

    private static function assertOnlyKeys(array $value, array $allowed, string $context): void
    {
        foreach (array_keys($value) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("{$context} contains unsupported field '{$key}'.");
            }
        }
    }
}
