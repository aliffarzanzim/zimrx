<?php
/**
 * ZimRx Geo Studio - GeoNames Importer Service
 * Parses GeoNames postal datasets (TSV or JSON) into ZimRx hierarchical tree format.
 * Embeds required Creative Commons Attribution 4.0 (CC-BY 4.0) licensing.
 * Zero external dependencies.
 */

declare(strict_types=1);

namespace ZimRx\GeoStudio;

use ZipArchive;
use RuntimeException;

class GeoNamesImporter
{
    /**
     * Extracts tab-delimited text from a GeoNames zip file.
     */
    public static function extractZipContent(string $zipFilePath): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException("PHP zip extension is required to process GeoNames .zip files.");
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            throw new RuntimeException("Unable to open GeoNames ZIP archive.");
        }

        $rawContent = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) continue;
            $name = strtolower($stat['name']);
            if (str_ends_with($name, '.txt') && !str_starts_with($name, 'readme')) {
                $rawContent = (string)$zip->getFromIndex($i);
                break;
            }
        }
        $zip->close();

        if (empty($rawContent)) {
            throw new RuntimeException("No valid data file found inside the GeoNames ZIP archive.");
        }

        return $rawContent;
    }

    /**
     * Downloads live country postal archive from download.geonames.org.
     */
    public static function fetchLiveZip(string $countryCode): string
    {
        $code = CountryCatalog::sanitizeCode($countryCode);
        $url = "http://download.geonames.org/export/zip/{$code}.zip";

        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: ZimRxGeoStudio/1.0\r\n",
                'timeout' => 45,
            ]
        ];
        $ctx = stream_context_create($options);
        $zipData = @file_get_contents($url, false, $ctx);

        if ($zipData === false && function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZimRxGeoStudio/1.0');
            curl_setopt($ch, CURLOPT_TIMEOUT, 45);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $zipData = curl_exec($ch);
            curl_close($ch);
        }

        if (!$zipData) {
            throw new RuntimeException("Unable to download {$code}.zip from GeoNames. Please check internet connectivity or upload file manually.");
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'geo_') . '.zip';
        file_put_contents($tmpZip, $zipData);

        try {
            $content = self::extractZipContent($tmpZip);
        } finally {
            @unlink($tmpZip);
        }

        return $content;
    }

    /**
     * Parses raw GeoNames TSV or JSON data and creates country pack.
     */
    public static function parseAndSaveDataset(
        string $rawContent,
        string $countryCode,
        string $countryName,
        string $contributor,
        string $countriesDir
    ): array {
        $countryCode = CountryCatalog::sanitizeCode($countryCode);
        $countryName = trim($countryName) !== '' ? trim($countryName) : $countryCode;
        $contributor = trim($contributor) !== '' ? trim($contributor) : 'Alif Farzan Zim';

        $placesTree = [];
        $totalPlaces = 0;

        $trimmed = trim($rawContent);
        $firstChar = substr($trimmed, 0, 1);

        if ($firstChar === '{' || $firstChar === '[') {
            // JSON format
            $json = json_decode($trimmed, true);
            $records = $json['geonames'] ?? (isset($json[0]) ? $json : []);
            $treeIndex = [];

            foreach ($records as $r) {
                $pcode = trim((string)($r['postalCode'] ?? $r['postalcode'] ?? $r['postcode'] ?? ''));
                $name = trim((string)($r['placeName'] ?? $r['placename'] ?? $r['name'] ?? ''));
                $adm1 = trim((string)($r['adminName1'] ?? $r['adminname1'] ?? ''));
                $adm2 = trim((string)($r['adminName2'] ?? $r['adminname2'] ?? ''));
                if ($name === '') continue;
                if ($adm1 === '') $adm1 = $countryName;
                $treeIndex[$adm1][$adm2][] = [
                    'en' => $name,
                    'loc' => $name,
                    'type' => ['place', 'postoffice'],
                    'postcode' => $pcode !== '' ? $pcode : null,
                ];
            }

            foreach ($treeIndex as $adm1Name => $adm2Map) {
                $totalPlaces++;
                $adm1Node = ['en' => $adm1Name, 'loc' => $adm1Name, 'type' => 'state', 'places' => []];
                foreach ($adm2Map as $adm2Name => $leafs) {
                    if ($adm2Name !== '') {
                        $totalPlaces++;
                        $adm2Node = ['en' => $adm2Name, 'loc' => $adm2Name, 'type' => 'county', 'places' => []];
                        foreach ($leafs as $lf) {
                            $totalPlaces++;
                            $adm2Node['places'][] = $lf;
                        }
                        $adm1Node['places'][] = $adm2Node;
                    } else {
                        foreach ($leafs as $lf) {
                            $totalPlaces++;
                            $adm1Node['places'][] = $lf;
                        }
                    }
                }
                $placesTree[] = $adm1Node;
            }
        } else {
            // Tab-delimited TSV format from GeoNames postalCodes.zip
            $lines = explode("\n", $trimmed);
            $treeIndex = [];
            $seenLeaves = [];

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                $parts = explode("\t", $line);
                if (count($parts) < 3) continue;

                $pcode = trim($parts[1] ?? '');
                $name = trim($parts[2] ?? '');
                $adm1 = trim($parts[3] ?? '');
                $adm2 = trim($parts[5] ?? '');
                if ($name === '') continue;
                if ($adm1 === '') $adm1 = $countryName;

                $leafKey = $adm1 . '|' . $adm2 . '|' . $name . '|' . $pcode;
                if (isset($seenLeaves[$leafKey])) continue;
                $seenLeaves[$leafKey] = true;

                $treeIndex[$adm1][$adm2][] = [
                    'en' => $name,
                    'loc' => $name,
                    'type' => ['place', 'postoffice'],
                    'postcode' => $pcode !== '' ? $pcode : null,
                ];
            }

            foreach ($treeIndex as $adm1Name => $adm2Map) {
                $totalPlaces++;
                $adm1Node = ['en' => $adm1Name, 'loc' => $adm1Name, 'type' => 'state', 'places' => []];
                foreach ($adm2Map as $adm2Name => $leafs) {
                    if ($adm2Name !== '') {
                        $totalPlaces++;
                        $adm2Node = ['en' => $adm2Name, 'loc' => $adm2Name, 'type' => 'county', 'places' => []];
                        foreach ($leafs as $lf) {
                            $totalPlaces++;
                            $adm2Node['places'][] = $lf;
                        }
                        $adm1Node['places'][] = $adm2Node;
                    } else {
                        foreach ($leafs as $lf) {
                            $totalPlaces++;
                            $adm1Node['places'][] = $lf;
                        }
                    }
                }
                $placesTree[] = $adm1Node;
            }
        }

        $contributors = array_values(array_unique(array_filter(['GeoNames Contributors', $contributor])));

        $dataset = [
            'manifest' => [
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'default_lang' => 'en',
                'local_lang' => 'en',
                'version' => '1.0.0',
                'release_date' => date('Y-m-d'),
                'last_updated' => date('Y-m-d'),
                'author' => 'GeoNames (geonames.org)',
                'contributors' => $contributors,
                'license' => 'CC-BY-4.0',
                'sources' => [
                    'GeoNames postal code database (https://www.geonames.org) licensed under Creative Commons Attribution 4.0 (CC-BY 4.0)'
                ],
                'notes' => 'Geographic dataset imported from GeoNames (geonames.org). Attribution to GeoNames is required under Creative Commons Attribution 4.0 License.'
            ],
            'hierarchy' => [
                'model' => 'recursive_polymorphic',
                'child_key' => 'places',
                'levels' => [
                    ['depth' => 1, 'types' => [['key' => 'state', 'name_en' => 'State / Province', 'name_loc' => 'State / Province']]],
                    ['depth' => 2, 'types' => [['key' => 'county', 'name_en' => 'County / District', 'name_loc' => 'County / District']]],
                    ['depth' => 3, 'types' => [
                        ['key' => 'place', 'name_en' => 'City / Locality', 'name_loc' => 'City / Locality'],
                        ['key' => 'postoffice', 'name_en' => 'Post Office', 'name_loc' => 'Post Office']
                    ]]
                ]
            ],
            'places' => $placesTree
        ];

        $outFile = rtrim($countriesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $countryCode . '.json';
        $encoded = json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException("Failed to encode JSON for GeoNames dataset '{$countryCode}'.");
        }
        CountryCatalog::atomicWrite($outFile, $encoded);

        return [
            'success' => true,
            'country_code' => $countryCode,
            'country_name' => $countryName,
            'total_places' => $totalPlaces,
            'message' => "Successfully imported {$countryName} ({$countryCode}) with {$totalPlaces} places from GeoNames.",
        ];
    }

    /**
     * Returns an offline, bundled ISO 3166-1 country catalog.
     * Zero network calls, 100% offline, zero IP leak.
     *
     * @return array<string, mixed>
     */
    public static function getOfflineCountryCatalog(): array
    {
        $configFile = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'countries.php';
        $countries = [];

        // Known common postal countries on GeoNames for offline badge indication
        $commonPostal = [
            'AD','AE','AI','AL','AR','AS','AT','AU','AX','AZ','BD','BE','BG','BM','BR','BY','CA','CC','CH','CL',
            'CN','CO','CR','CX','CY','CZ','DE','DK','DO','DZ','EC','EE','ES','FI','FK','FM','FO','FR','GB','GF',
            'GG','GI','GL','GP','GT','GU','HR','HT','HU','ID','IE','IL','IM','IN','IS','IT','JE','JP','KE','KR',
            'LI','LK','LT','LU','LV','MC','MD','MF','MH','MK','MP','MQ','MT','MW','MX','MY','NC','NF','NL','NO',
            'NZ','PE','PF','PG','PH','PK','PL','PM','PR','PT','PW','RE','RO','RS','RU','SA','SE','SG','SI','SJ',
            'SK','SM','SZ','TH','TR','TW','UA','US','UY','VA','VI','WF','YT','ZA','ZM'
        ];
        $commonPostalMap = array_flip($commonPostal);

        if (is_file($configFile)) {
            $data = require $configFile;
            $territories = $data['grouped']['Countries & Territories'] ?? [];
            foreach ($territories as $code => $name) {
                $code = strtoupper(trim((string)$code));
                if (strlen($code) !== 2) continue;
                $countries[] = [
                    'code' => $code,
                    'name' => (string)$name,
                    'has_postal' => isset($commonPostalMap[$code]),
                    'is_offline' => true,
                ];
            }
        }

        // Sort alphabetically by name
        usort($countries, fn($a, $b) => strcmp($a['name'], $b['name']));

        return [
            'success' => true,
            'is_offline' => true,
            'total_countries' => count($countries),
            'countries' => $countries,
        ];
    }

    /**
     * Explicit opt-in method to download live country catalog from GeoNames.
     * Only executed when user explicitly requests live directory fetch.
     * Caches result locally so subsequent lookups do not re-query GeoNames.
     *
     * @return array<string, mixed>
     */
    public static function fetchLiveCountryCatalog(bool $forceRefresh = false, ?string $cacheDir = null): array
    {
        $cacheDir = $cacheDir ?? sys_get_temp_dir();
        $cacheFile = rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'geonames_countries_cache.json';

        if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400 * 7)) {
            $cached = @file_get_contents($cacheFile);
            if ($cached) {
                $data = json_decode($cached, true);
                if (is_array($data) && !empty($data['countries'])) {
                    $data['from_cache'] = true;
                    return $data;
                }
            }
        }

        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: ZimRxGeoStudio/1.0 (offline-prescription-project)\r\n",
                'timeout' => 20,
            ]
        ];
        $ctx = stream_context_create($options);

        // 1. Fetch available postal zip list from GeoNames
        $postalCodes = [];
        $zipHtml = @file_get_contents('http://download.geonames.org/export/zip/', false, $ctx);
        if ($zipHtml && preg_match_all('/href="([A-Z]{2})\.zip"/i', $zipHtml, $matches)) {
            $postalCodes = array_flip(array_unique(array_map('strtoupper', $matches[1])));
        }

        // 2. Fetch countryInfo.txt
        $infoTxt = @file_get_contents('http://download.geonames.org/export/dump/countryInfo.txt', false, $ctx);
        if (!$infoTxt && function_exists('curl_init')) {
            $ch = curl_init('http://download.geonames.org/export/dump/countryInfo.txt');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZimRxGeoStudio/1.0 (offline-prescription-project)');
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            $infoTxt = curl_exec($ch);
            curl_close($ch);
        }

        if (!$infoTxt) {
            throw new RuntimeException("Unable to connect to GeoNames server. Please check internet connectivity or use offline presets.");
        }

        $lines = explode("\n", (string)$infoTxt);
        $countries = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, '#') || trim($line) === '') continue;
            $cols = explode("\t", $line);
            $code = strtoupper(trim($cols[0] ?? ''));
            $name = trim($cols[4] ?? '');
            if ($code === '' || $name === '' || strlen($code) !== 2) continue;

            $hasPostal = isset($postalCodes[$code]);
            $postalFormat = trim($cols[13] ?? '');
            $capital = trim($cols[5] ?? '');
            $continent = trim($cols[8] ?? '');

            $countries[] = [
                'code' => $code,
                'name' => $name,
                'capital' => $capital,
                'continent' => $continent,
                'has_postal' => $hasPostal,
                'postal_format' => $postalFormat,
                'is_offline' => false,
            ];
        }

        // Sort alphabetically by name
        usort($countries, fn($a, $b) => strcmp($a['name'], $b['name']));

        $result = [
            'success' => true,
            'from_cache' => false,
            'is_offline' => false,
            'timestamp' => date('Y-m-d H:i:s'),
            'total_countries' => count($countries),
            'total_postal_dumps' => count($postalCodes),
            'countries' => $countries,
        ];

        @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $result;
    }
}

