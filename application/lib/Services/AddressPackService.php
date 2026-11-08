<?php
declare(strict_types=1);

namespace ZimRx\Services;

use PDO;
use RuntimeException;

require_once __DIR__ . '/AddressPackImporter.php';

final class AddressPackService
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $seedPacks = null;

    /** @return array<string, array<string, mixed>> Indexed by pack ID. */
    public static function getAvailablePacks(): array
    {
        if (self::$seedPacks !== null) {
            return self::$seedPacks;
        }

        $baseDir = defined('ZIMRX_BASE_DIR') ? ZIMRX_BASE_DIR : dirname(__DIR__, 2);
        $seedsDir = $baseDir . '/systemdata/seeds';
        $packs = [];
        foreach (AddressPackImporter::catalog($seedsDir) as $seed) {
            $manifest = $seed['manifest'];
            $packId = $seed['pack_id'];
            if (isset($packs[$packId])) {
                throw new RuntimeException('Address pack IDs must be unique.');
            }

            $packs[$packId] = [
                'pack_id' => $packId,
                'pack_type' => $manifest['pack_type'],
                'code_label' => $manifest['code_label'],
                'default_language' => $manifest['default_lang'],
                'languages' => $manifest['languages'],
                'root_names' => $manifest['root_names'],
                'levels' => $seed['levels'],
                'version' => $manifest['version'],
                'release_date' => $manifest['release_date'],
                'last_updated' => $manifest['last_updated'],
                'author' => $manifest['author'],
                'contributors' => $manifest['contributors'],
                'license' => $manifest['license'],
                'sources' => $manifest['sources'],
                'notes' => $manifest['notes'],
            ];
        }

        self::$seedPacks = $packs;
        return self::$seedPacks;
    }

    /** @return array<string, array<string, mixed>> Indexed by ISO country code. */
    public static function getNationalPacksByCountry(): array
    {
        $packsByCountry = [];
        foreach (self::getAvailablePacks() as $pack) {
            if (($pack['pack_type'] ?? null) !== 'national') {
                continue;
            }

            $countryCode = strtoupper(trim((string)($pack['pack_id'] ?? '')));
            if (preg_match('/^[A-Z]{2}$/D', $countryCode) !== 1) {
                throw new RuntimeException('A national address pack ID must be its two-letter country code.');
            }

            $existing = $packsByCountry[$countryCode] ?? null;
            if ($existing === null || strcmp((string)($pack['last_updated'] ?? ''), (string)($existing['last_updated'] ?? '')) > 0) {
                $packsByCountry[$countryCode] = $pack;
            }
        }

        return $packsByCountry;
    }

    /** @return array<string, mixed>|null */
    public static function getNationalPackForCountry(string $countryCode): ?array
    {
        $code = strtoupper(trim($countryCode));
        return self::getNationalPacksByCountry()[$code] ?? null;
    }

    public static function hasNationalPackForCountry(string $countryCode): bool
    {
        return self::getNationalPackForCountry($countryCode) !== null;
    }

    /** @return array<string, mixed>|null */
    public static function getConfiguredPack(PDO $staticPdo, PDO $userPdo): ?array
    {
        $setting = $userPdo->prepare(
            "SELECT config_value FROM zimrx_app_config WHERE config_key = 'address_pack_id' LIMIT 1"
        );
        $setting->execute();
        $packId = trim((string)($setting->fetchColumn() ?: ''));
        if ($packId === '') {
            return null;
        }

        $sourceSetting = $userPdo->prepare(
            "SELECT config_value FROM zimrx_app_config WHERE config_key = 'address_pack_source' LIMIT 1"
        );
        $sourceSetting->execute();
        $source = trim((string)($sourceSetting->fetchColumn() ?: 'system'));
        if (!in_array($source, ['system', 'user'], true)) {
            return null;
        }
        $tables = self::tableNames($source);
        $packPdo = $source === 'user' ? $userPdo : $staticPdo;
        $packQuery = $packPdo->prepare(
            "SELECT pack_id, pack_type, code_label, default_language,
                    languages_json, root_names_json, levels_json
             FROM {$tables['packs']} WHERE pack_id = :pack_id LIMIT 1"
        );
        $packQuery->execute(['pack_id' => $packId]);
        $pack = $packQuery->fetch(PDO::FETCH_ASSOC);
        if (!is_array($pack)) {
            return null;
        }

        try {
            $pack['languages'] = json_decode((string)$pack['languages_json'], true, 512, JSON_THROW_ON_ERROR);
            $pack['root_names'] = json_decode((string)$pack['root_names_json'], true, 512, JSON_THROW_ON_ERROR);
            $pack['levels'] = json_decode((string)$pack['levels_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new RuntimeException('The configured address pack contains invalid JSON metadata.', 0, $error);
        }
        unset($pack['languages_json'], $pack['root_names_json'], $pack['levels_json']);

        if (!is_array($pack['languages']) || !is_array($pack['root_names']) || !is_array($pack['levels'])) {
            throw new RuntimeException('The configured address pack metadata has an invalid shape.');
        }

        $pack['source'] = $source;
        $languageSetting = $userPdo->prepare(
            "SELECT config_value FROM zimrx_app_config WHERE config_key = 'address_language' LIMIT 1"
        );
        $languageSetting->execute();
        $requestedLanguage = trim((string)($languageSetting->fetchColumn() ?: ''));
        $pack['display_language'] = self::resolveLanguage($pack, $requestedLanguage);
        return $pack;
    }

    /** @return array{packs:string,places:string,names:string} */
    public static function tableNames(string $source): array
    {
        return match ($source) {
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
            default => throw new RuntimeException('Address pack source is invalid.'),
        };
    }

    /** @param array<string, mixed> $pack */
    public static function resolveLanguage(array $pack, string $requestedLanguage): string
    {
        $languages = is_array($pack['languages'] ?? null) ? $pack['languages'] : [];
        $requested = strtolower(trim($requestedLanguage));
        if ($requested !== '' && in_array($requested, $languages, true)) {
            return $requested;
        }

        $default = strtolower(trim((string)($pack['default_language'] ?? '')));
        if ($default !== '' && in_array($default, $languages, true)) {
            return $default;
        }

        return (string)($languages[0] ?? 'en');
    }
}
