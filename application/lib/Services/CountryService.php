<?php
declare(strict_types=1);

namespace ZimRx\Services;

// Comprehensive ISO 3166-1 country catalog with clinical regulatory bodies and humanitarian missions.
final class CountryService
{
    /** @var array{iso3_map: array<string, string>, regulatory_bodies: array<string, string>, grouped: array<string, array<string, string>>}|null */
    private static ?array $data = null;

    /**
     * @return array{iso3_map: array<string, string>, regulatory_bodies: array<string, string>, grouped: array<string, array<string, string>>}
     */
    private static function load(): array
    {
        if (self::$data === null) {
            $path = is_file(dirname(__DIR__, 2) . '/config/countries.php')
                ? dirname(__DIR__, 2) . '/config/countries.php'
                : __DIR__ . '/../../config/countries.php';
            self::$data = require $path;
        }
        return self::$data;
    }

    /**
     * @param bool $withRegulatory Whether to format with regulatory body e.g. 'Bangladesh (BMDC)'
     * @return array<string, array<string, string>>
     */
    public static function getGrouped(bool $withRegulatory = true): array
    {
        $rawGrouped = self::load()['grouped'] ?? [];
        if (!$withRegulatory) {
            return $rawGrouped;
        }

        $regs = self::load()['regulatory_bodies'] ?? [];
        $result = [];
        foreach ($rawGrouped as $groupName => $items) {
            $result[$groupName] = [];
            foreach ($items as $code => $name) {
                $reg = $regs[$code] ?? null;
                $result[$groupName][$code] = $reg !== null ? "{$name} ({$reg})" : $name;
            }
        }
        return $result;
    }

    /**
     * @param bool $withRegulatory Whether to format with regulatory body e.g. 'Bangladesh (BMDC)'
     * @return array<string, string> Flat key-value map of code => name
     */
    public static function getAll(bool $withRegulatory = false): array
    {
        $all = [];
        foreach (self::getGrouped($withRegulatory) as $group) {
            foreach ($group as $code => $name) {
                $all[$code] = $name;
            }
        }
        return $all;
    }

    /**
     * Returns clean country name without regulatory body (e.g. 'Bangladesh', 'United States')
     */
    public static function getName(string $code): string
    {
        $cleanAll = self::getAll(false);
        return $cleanAll[strtoupper(trim($code))] ?? 'Other / Worldwide';
    }

    /**
     * Returns the regulatory body abbreviation / medical council if defined (e.g. 'BMDC', 'GMC', 'NPI / DEA')
     */
    public static function getRegulatoryBody(string $code): ?string
    {
        $regs = self::load()['regulatory_bodies'] ?? [];
        return $regs[strtoupper(trim($code))] ?? null;
    }

    /**
     * Returns formatted display label with regulatory body if available (e.g. 'Bangladesh (BMDC)')
     */
    public static function getDisplayLabel(string $code): string
    {
        $c = strtoupper(trim($code));
        $name = self::getName($c);
        $reg = self::getRegulatoryBody($c);
        return $reg !== null ? "{$name} ({$reg})" : $name;
    }

    public static function getIso3(string $code): string
    {
        $map = self::load()['iso3_map'] ?? [];
        $c = strtoupper(trim($code));
        return $map[$c] ?? 'ZZZ';
    }

    /**
     * Returns recommended patient clinical language code for a country (e.g. 'BD' => 'bn'), or null if default
     */
    public static function getDefaultClinicalLanguage(string $code): ?string
    {
        $langs = self::load()['clinical_languages'] ?? [];
        return $langs[strtoupper(trim($code))] ?? null;
    }

    /**
     * Returns the full country to clinical language map
     * @return array<string, string>
     */
    public static function getCountryClinicalLanguages(): array
    {
        return self::load()['clinical_languages'] ?? [];
    }

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $formularies = null;

    /**
     * Discovers all available regional drug formulary packs from systemdata/seeds/zimrx_drugs_*.json manifests.
     * @return array<string, array<string, mixed>> Indexed by ISO country code (e.g. 'BD')
     */
    public static function getAvailableFormularies(): array
    {
        if (self::$formularies !== null) {
            return self::$formularies;
        }

        self::$formularies = [];
        $baseDir = defined('ZIMRX_BASE_DIR') ? ZIMRX_BASE_DIR : dirname(__DIR__, 2);
        $seedsDir = $baseDir . '/systemdata/seeds';

        if (is_dir($seedsDir)) {
            $files = glob($seedsDir . '/zimrx_drugs_*.json');
            if (is_array($files)) {
                foreach ($files as $file) {
                    $raw = @file_get_contents($file);
                    if ($raw === false) {
                        continue;
                    }
                    $data = json_decode($raw, true);
                    if (is_array($data) && !empty($data['country_code'])) {
                        $code = strtoupper(trim((string)$data['country_code']));
                        self::$formularies[$code] = $data;
                    }
                }
            }
        }

        return self::$formularies;
    }

    /**
     * Check if commercial drug formulary exists for a given country code.
     */
    public static function hasFormulary(string $code): bool
    {
        $code = strtoupper(trim($code));
        $all = self::getAvailableFormularies();
        return isset($all[$code]);
    }

    /**
     * Retrieve formulary metadata pack for a country code.
     * @return array<string, mixed>|null
     */
    public static function getFormulary(string $code): ?array
    {
        $code = strtoupper(trim($code));
        $all = self::getAvailableFormularies();
        return $all[$code] ?? null;
    }

    /**
     * Returns default prescribing mode for a country ('dual' or 'generic').
     */
    public static function getDefaultPrescribingMode(string $code): string
    {
        $pack = self::getFormulary($code);
        if ($pack !== null && !empty($pack['default_mode'])) {
            $mode = strtolower(trim((string)$pack['default_mode']));
            return in_array($mode, ['dual', 'brand'], true) ? 'dual' : 'generic';
        }
        return self::hasFormulary($code) ? 'dual' : 'generic';
    }

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $addressPacks = null;

    /**
     * Discovers all available regional address hierarchy packs from systemdata/seeds/zimrx_address_hierarchy_*.json or zimrx_address_hierarchy_*.sql.
     * @return array<string, array<string, mixed>> Indexed by ISO country code (e.g. 'BD')
     */
    public static function getAvailableAddressPacks(): array
    {
        if (self::$addressPacks !== null) {
            return self::$addressPacks;
        }

        self::$addressPacks = [];
        $baseDir = defined('ZIMRX_BASE_DIR') ? ZIMRX_BASE_DIR : dirname(__DIR__, 2);
        $seedsDir = $baseDir . '/systemdata/seeds';

        if (is_dir($seedsDir)) {
            $jsonFiles = glob($seedsDir . '/zimrx_address_hierarchy_*.json') ?: [];
            foreach ($jsonFiles as $file) {
                $raw = @file_get_contents($file);
                if ($raw === false) continue;
                $data = json_decode($raw, true);
                if (is_array($data) && !empty($data['country_code'])) {
                    $code = strtoupper(trim((string)$data['country_code']));
                    self::$addressPacks[$code] = $data;
                }
            }

            $sqlFiles = glob($seedsDir . '/zimrx_address_hierarchy_*.sql') ?: [];
            foreach ($sqlFiles as $file) {
                if (preg_match('/zimrx_address_hierarchy_([a-z0-9_-]+)\.sql$/i', basename($file), $m)) {
                    $code = strtoupper(trim($m[1]));
                    if (!isset(self::$addressPacks[$code])) {
                        self::$addressPacks[$code] = [
                            'country_code' => $code,
                            'seed_file' => basename($file),
                            'places_count' => ($code === 'BD') ? 5528 : 0,
                        ];
                    }
                }
            }
        }

        return self::$addressPacks;
    }

    /**
     * Check if address hierarchy pack exists for a given country code.
     */
    public static function hasAddressHierarchy(string $code): bool
    {
        $code = strtoupper(trim($code));
        $all = self::getAvailableAddressPacks();
        return isset($all[$code]);
    }

    /**
     * Retrieve address pack metadata for a country code.
     * @return array<string, mixed>|null
     */
    public static function getAddressPack(string $code): ?array
    {
        $code = strtoupper(trim($code));
        $all = self::getAvailableAddressPacks();
        return $all[$code] ?? null;
    }
}


if (!class_exists('CountryService', false)) {
    class_alias(CountryService::class, 'CountryService');
}
