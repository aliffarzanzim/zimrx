<?php
/**
 * ZimRx Geo Studio - Wikidata / Wikipedia Importer Service
 * Queries Wikidata SPARQL endpoint natively in pure PHP.
 * Imports national administrative divisions, districts, localities, and postal codes.
 * Embeds Creative Commons CC0 1.0 Universal Public Domain Dedication.
 * Zero external dependencies.
 */

declare(strict_types=1);

namespace ZimRx\GeoStudio;

use RuntimeException;

class WikidataImporter
{
    /**
     * Executes a Wikidata SPARQL query with robust timeouts and user agent.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public static function querySparql(string $sparql): ?array
    {
        $url = 'https://query.wikidata.org/sparql?format=json&query=' . urlencode($sparql);
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: ZimRxGeoStudio/1.0 (https://github.com/AlifFarzan/ZimRx; offline-prescription-project)\r\nAccept: application/sparql-results+json\r\n",
                'timeout' => 45,
            ]
        ];
        $ctx = stream_context_create($options);
        $resp = @file_get_contents($url, false, $ctx);

        if ($resp === false && function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZimRxGeoStudio/1.0 (https://github.com/AlifFarzan/ZimRx; offline-prescription-project)');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/sparql-results+json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 45);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            $resp = curl_exec($ch);
            curl_close($ch);
        }

        if (!$resp) {
            return null;
        }

        $data = json_decode((string)$resp, true);
        return $data['results']['bindings'] ?? null;
    }

    /**
     * Imports and saves a dataset from Wikidata or an uploaded Wikidata JSON file.
     */
    public static function fetchAndSaveDataset(
        string $countryCode,
        string $countryName,
        string $contributor,
        string $countriesDir,
        ?string $rawUploadedJson = null,
        int $maxLevel = 0,
        array $selectedLevels = []
    ): array {
        $countryCode = CountryCatalog::sanitizeCode($countryCode);
        $countryName = trim($countryName) !== '' ? trim($countryName) : $countryCode;
        if ($countryCode === 'GB' && ($countryName === 'GB' || strtoupper($countryName) === 'UK')) {
            $countryName = 'United Kingdom';
        }
        $contributor = trim($contributor) !== '' ? trim($contributor) : 'Alif Farzan Zim';

        // 1. If uploaded JSON file is provided
        if ($rawUploadedJson !== null && trim($rawUploadedJson) !== '') {
            $dataset = json_decode(trim($rawUploadedJson), true);
            if (!is_array($dataset) || empty($dataset['places'])) {
                throw new RuntimeException("Invalid Wikidata JSON format. Expected root object with 'places' array.");
            }
            if (!empty($selectedLevels)) {
                if (!empty($dataset['hierarchy']['levels']) && is_array($dataset['hierarchy']['levels'])) {
                    $newLevels = [];
                    $d = 1;
                    foreach ($dataset['hierarchy']['levels'] as $lvl) {
                        $origDepth = (int)($lvl['depth'] ?? 0);
                        if (in_array($origDepth, $selectedLevels, true)) {
                            $lvl['depth'] = $d++;
                            $newLevels[] = $lvl;
                        }
                    }
                    $dataset['hierarchy']['levels'] = $newLevels;
                }
            } elseif ($maxLevel > 0) {
                $prune = function(&$node, $curDepth) use (&$prune, $maxLevel) {
                    if ($curDepth >= $maxLevel) {
                        unset($node['places'], $node['children']);
                        return;
                    }
                    if (!empty($node['places']) && is_array($node['places'])) {
                        foreach ($node['places'] as &$c) {
                            $prune($c, $curDepth + 1);
                        }
                    }
                };
                foreach ($dataset['places'] as &$p) {
                    $prune($p, 1);
                }
                if (!empty($dataset['hierarchy']['levels']) && is_array($dataset['hierarchy']['levels'])) {
                    $dataset['hierarchy']['levels'] = array_values(array_filter(
                        $dataset['hierarchy']['levels'],
                        fn($lvl) => (int)($lvl['depth'] ?? 0) <= $maxLevel
                    ));
                }
            }
            $outFile = rtrim($countriesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $countryCode . '.json';
            $encoded = json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new RuntimeException("Failed to encode JSON for uploaded dataset '{$countryCode}'.");
            }
            CountryCatalog::atomicWrite($outFile, $encoded);
            return [
                'success' => true,
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'message' => "Successfully imported {$countryName} ({$countryCode}) from uploaded Wikidata JSON.",
            ];
        }

        // 2. Select appropriate SPARQL pipeline
        if ($countryCode === 'GB') {
            return self::importUnitedKingdom($countryCode, $countryName, $contributor, $countriesDir, $maxLevel, $selectedLevels);
        }

        if ($countryCode === 'BD') {
            return self::importBangladesh($countryCode, $countryName, $contributor, $countriesDir, $maxLevel, $selectedLevels);
        }

        return self::importGeneralCountry($countryCode, $countryName, $contributor, $countriesDir, $maxLevel, $selectedLevels);
    }

    /**
     * Specialized SPARQL pipeline for United Kingdom (GB).
     */
    private static function importUnitedKingdom(
        string $countryCode,
        string $countryName,
        string $contributor,
        string $countriesDir,
        int $maxLevel = 0,
        array $selectedLevels = []
    ): array {
        if (empty($selectedLevels)) {
            $selectedLevels = ($maxLevel > 0) ? range(1, min($maxLevel, 3)) : [1, 2, 3];
        }

        $hasL1 = in_array(1, $selectedLevels, true);
        $hasL2 = in_array(2, $selectedLevels, true);
        $hasL3 = in_array(3, $selectedLevels, true);

        // Level 1: 4 Constituent countries of UK
        $countries = [
            'Q21' => ['en' => 'England', 'loc' => 'England', 'type' => 'country', 'children' => []],
            'Q22' => ['en' => 'Scotland', 'loc' => 'Scotland', 'type' => 'country', 'children' => []],
            'Q25' => ['en' => 'Wales', 'loc' => 'Wales', 'type' => 'country', 'children' => []],
            'Q26' => ['en' => 'Northern Ireland', 'loc' => 'Northern Ireland', 'type' => 'country', 'children' => []],
        ];

        $l2Map = [];
        if ($hasL2 || $hasL3) {
            // Level 2: Ceremonial counties of England
            $qEng = <<<SPARQL
SELECT ?item ?itemLabel ?postcode WHERE {
  ?item wdt:P31 wd:Q180673 .
  OPTIONAL { ?item wdt:P281 ?postcode . }
  OPTIONAL { ?item rdfs:label ?itemLabel . FILTER(LANG(?itemLabel) = "en") }
}
SPARQL;

            // Level 2: Scotland, Wales, and Northern Ireland via P150
            $qOthers = <<<SPARQL
SELECT ?country ?item ?itemLabel ?postcode WHERE {
  VALUES ?country { wd:Q22 wd:Q25 wd:Q26 }
  ?country wdt:P150 ?item .
  OPTIONAL { ?item wdt:P281 ?postcode . }
  OPTIONAL { ?item rdfs:label ?itemLabel . FILTER(LANG(?itemLabel) = "en") }
}
SPARQL;

            $engRows = self::querySparql($qEng);
            if ($engRows) {
                foreach ($engRows as $r) {
                    $qid = basename($r['item']['value'] ?? '');
                    $label = trim($r['itemLabel']['value'] ?? '');
                    $pc = trim($r['postcode']['value'] ?? '');
                    if ($qid === '' || $label === '') continue;
                    if (!isset($l2Map[$qid])) {
                        $l2Map[$qid] = [
                            'qid' => $qid,
                            'countryQid' => 'Q21',
                            'en' => $label,
                            'loc' => $label,
                            'type' => 'county',
                            'postcode' => $pc !== '' ? $pc : null,
                            'children' => []
                        ];
                    }
                }
            }

            $otherRows = self::querySparql($qOthers);
            if ($otherRows) {
                foreach ($otherRows as $r) {
                    $qid = basename($r['item']['value'] ?? '');
                    $countryQid = basename($r['country']['value'] ?? '');
                    $label = trim($r['itemLabel']['value'] ?? '');
                    $pc = trim($r['postcode']['value'] ?? '');
                    if ($qid === '' || $label === '') continue;

                    $ptype = 'county';
                    if ($countryQid === 'Q22') $ptype = 'council_area';
                    elseif ($countryQid === 'Q25') $ptype = 'principal_area';
                    elseif ($countryQid === 'Q26') $ptype = 'district';

                    if (!isset($l2Map[$qid])) {
                        $l2Map[$qid] = [
                            'qid' => $qid,
                            'countryQid' => $countryQid,
                            'en' => $label,
                            'loc' => $label,
                            'type' => $ptype,
                            'postcode' => $pc !== '' ? $pc : null,
                            'children' => []
                        ];
                    }
                }
            }
        }

        $l3Map = [];
        if ($hasL3) {
            // Level 3: Districts, London boroughs, Metropolitan boroughs, unitary authorities, cities, and towns
            $qL3 = <<<SPARQL
SELECT ?item ?itemLabel ?parent ?parentLabel ?type ?postcode WHERE {
  VALUES ?type {
    wd:Q1002812   # non-metropolitan district of England
    wd:Q209047    # London borough
    wd:Q2237970   # metropolitan borough
    wd:Q11696     # unitary authority of England
    wd:Q515       # city in UK
    wd:Q3957      # town in UK
  }
  ?item wdt:P31 ?type ;
        wdt:P131 ?parent .
  ?item wdt:P17 wd:Q145 .
  OPTIONAL { ?item wdt:P281 ?postcode . }
  OPTIONAL { ?item rdfs:label ?itemLabel . FILTER(LANG(?itemLabel) = "en") }
  OPTIONAL { ?parent rdfs:label ?parentLabel . FILTER(LANG(?parentLabel) = "en") }
}
SPARQL;

            $l3Rows = self::querySparql($qL3);
            if ($l3Rows) {
                foreach ($l3Rows as $r) {
                    $qid = basename($r['item']['value'] ?? '');
                    $parentQid = basename($r['parent']['value'] ?? '');
                    $parentLabel = trim($r['parentLabel']['value'] ?? '');
                    $label = trim($r['itemLabel']['value'] ?? '');
                    $typeQid = basename($r['type']['value'] ?? '');
                    $pc = trim($r['postcode']['value'] ?? '');
                    if ($label === '' || $qid === '') continue;

                    $ptype = 'district';
                    if ($typeQid === 'Q209047') $ptype = 'borough';
                    elseif ($typeQid === 'Q2237970') $ptype = 'borough';
                    elseif ($typeQid === 'Q515') $ptype = 'city';
                    elseif ($typeQid === 'Q3957') $ptype = 'town';

                    $labelClean = preg_replace('/,\s*.*$/', '', $label);
                    $key = $parentQid . '|' . strtolower($labelClean);
                    if (!isset($l3Map[$key])) {
                        $l3Map[$key] = [
                            'qid' => $qid,
                            'parentQid' => $parentQid,
                            'parentName' => $parentLabel,
                            'en' => $labelClean,
                            'loc' => $labelClean,
                            'type' => $ptype,
                            'postcode' => $pc !== '' ? $pc : null,
                            'children' => []
                        ];
                    }
                }
            }
        }

        // Attach L3 to L2 if L3 was queried
        if (!empty($l3Map)) {
            $l2NameIndex = [];
            foreach ($l2Map as $qid => $node) {
                $l2NameIndex[strtolower($node['en'])] = $qid;
                if (strcasecmp($node['en'], 'Greater London') === 0) {
                    $l2NameIndex['london'] = $qid;
                }
            }

            foreach ($l3Map as $item) {
                $pId = $item['parentQid'];
                $pName = strtolower($item['parentName'] ?? '');
                $cId = $item['countryQid'] ?? null;
                if ($hasL2 && isset($l2Map[$pId])) {
                    $l2Map[$pId]['children'][] = $item;
                } elseif ($hasL2 && $pName !== '' && isset($l2NameIndex[$pName])) {
                    $targetQid = $l2NameIndex[$pName];
                    $l2Map[$targetQid]['children'][] = $item;
                } elseif ($hasL1 && $cId && isset($countries[$cId])) {
                    $countries[$cId]['children'][] = $item;
                }
            }
        }

        // Attach L2 to L1
        if ($hasL1 && !empty($l2Map)) {
            foreach ($l2Map as $item) {
                $cId = $item['countryQid'];
                if (isset($countries[$cId])) {
                    $countries[$cId]['children'][] = $item;
                }
            }
        }

        // Format output tree
        $formatNode = function(array $node) use (&$formatNode): array {
            $out = [
                'en' => $node['en'],
                'loc' => $node['loc'],
                'type' => $node['type']
            ];
            if (!empty($node['postcode'])) {
                $out['postcode'] = $node['postcode'];
            }
            if (!empty($node['children'])) {
                $sorted = $node['children'];
                usort($sorted, fn($a, $b) => strcmp($a['en'], $b['en']));
                $out['places'] = [];
                foreach ($sorted as $c) {
                    $out['places'][] = $formatNode($c);
                }
            }
            return $out;
        };

        $rootPlaces = [];
        if ($hasL1) {
            foreach ($countries as $c) {
                $rootPlaces[] = $formatNode($c);
            }
        } elseif ($hasL2) {
            $sortedL2 = array_values($l2Map);
            usort($sortedL2, fn($a, $b) => strcmp($a['en'], $b['en']));
            foreach ($sortedL2 as $item) {
                $rootPlaces[] = $formatNode($item);
            }
        } elseif ($hasL3) {
            $sortedTowns = array_values($l3Map);
            usort($sortedTowns, fn($a, $b) => strcmp($a['en'], $b['en']));
            foreach ($sortedTowns as $item) {
                $rootPlaces[] = $formatNode($item);
            }
        }

        $allUkLevels = [
            [
                'depth' => 1,
                'types' => [
                    ['key' => 'country', 'name_en' => 'Constituent Country', 'name_loc' => 'Constituent Country']
                ]
            ],
            [
                'depth' => 2,
                'types' => [
                    ['key' => 'county', 'name_en' => 'County', 'name_loc' => 'County'],
                    ['key' => 'council_area', 'name_en' => 'Council Area', 'name_loc' => 'Council Area'],
                    ['key' => 'principal_area', 'name_en' => 'Principal Area', 'name_loc' => 'Principal Area'],
                    ['key' => 'district', 'name_en' => 'District', 'name_loc' => 'District']
                ]
            ],
            [
                'depth' => 3,
                'types' => [
                    ['key' => 'district', 'name_en' => 'District', 'name_loc' => 'District'],
                    ['key' => 'borough', 'name_en' => 'Borough', 'name_loc' => 'Borough'],
                    ['key' => 'city', 'name_en' => 'City', 'name_loc' => 'City'],
                    ['key' => 'town', 'name_en' => 'Town', 'name_loc' => 'Town']
                ]
            ]
        ];

        $ukLevels = [];
        $dIndex = 1;
        foreach ($allUkLevels as $lvlDef) {
            if (in_array($lvlDef['depth'], $selectedLevels, true)) {
                $lvlCopy = $lvlDef;
                $lvlCopy['depth'] = $dIndex++;
                $ukLevels[] = $lvlCopy;
            }
        }

        $dataset = [
            'manifest' => [
                'country_code' => $countryCode,
                'pack_type' => 'national',
                'country_name' => $countryName,
                'default_lang' => 'en',
                'local_lang' => 'en',
                'version' => '1.0.0',
                'release_date' => date('Y-m-d'),
                'last_updated' => date('Y-m-d'),
                'author' => 'Wikidata (Wikimedia Community)',
                'contributors' => array_values(array_unique(array_filter(['Wikimedia Contributors', $contributor]))),
                'license' => 'CC0-1.0',
                'sources' => [
                    'Wikidata SPARQL Query Service (https://query.wikidata.org) licensed under Creative Commons CC0 1.0 Universal Public Domain Dedication'
                ],
                'notes' => 'Administrative hierarchy dataset for the United Kingdom imported directly from Wikidata SPARQL Query Service (CC0-1.0 Public Domain).'
            ],
            'hierarchy' => [
                'model' => 'recursive_polymorphic',
                'child_key' => 'places',
                'levels' => $ukLevels
            ],
            'places' => $rootPlaces
        ];

        $outFile = rtrim($countriesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $countryCode . '.json';
        $encoded = json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException("Failed to encode JSON for UK dataset '{$countryCode}'.");
        }
        CountryCatalog::atomicWrite($outFile, $encoded);

        $total = 0;
        $countWalk = function($n) use (&$countWalk, &$total) {
            $total++;
            foreach ($n['places'] ?? [] as $c) $countWalk($c);
        };
        foreach ($rootPlaces as $rp) $countWalk($rp);

        return [
            'success' => true,
            'country_code' => $countryCode,
            'country_name' => $countryName,
            'total_places' => $total,
            'message' => "Successfully imported {$countryName} ({$countryCode}) with {$total} places from Wikidata (CC0 1.0).",
        ];
    }

    /**
     * Specialized SPARQL pipeline for Bangladesh (BD).
     */
    private static function importBangladesh(
        string $countryCode,
        string $countryName,
        string $contributor,
        string $countriesDir,
        int $maxLevel = 0,
        array $selectedLevels = []
    ): array {
        if (empty($selectedLevels)) {
            $selectedLevels = ($maxLevel > 0) ? range(1, min($maxLevel, 4)) : [1, 2, 3, 4];
        }

        $hasL1 = in_array(1, $selectedLevels, true);
        $hasL2 = in_array(2, $selectedLevels, true);
        $hasL3 = in_array(3, $selectedLevels, true);
        $hasL4 = in_array(4, $selectedLevels, true);

        $cleanName = function(string $en, string $bn, string $ptype): array {
            $en = trim($en);
            $bn = trim($bn);
            $en = preg_replace('/,\s*.*$/u', '', $en);
            $bn = preg_replace('/[,،]\s*.*$/u', '', $bn);

            if ($ptype === 'division') {
                $en = preg_replace('/\s+Division$/i', '', $en);
                $bn = preg_replace('/\s*বিভাগ$/u', '', $bn);
            } elseif ($ptype === 'district') {
                $en = preg_replace('/\s+District$/i', '', $en);
                $bn = preg_replace('/\s*জেলা$/u', '', $bn);
            } elseif ($ptype === 'upazila') {
                $en = preg_replace('/\s+Upazila$/i', '', $en);
                $bn = preg_replace('/\s*উপজেলা$/u', '', $bn);
            } elseif ($ptype === 'thana') {
                $en = preg_replace('/\s+Thana$/i', '', $en);
                $bn = preg_replace('/\s*থানা$/u', '', $bn);
            } elseif ($ptype === 'union') {
                $en = preg_replace('/\s+Union(\s+Parishad)?$/i', '', $en);
                $bn = preg_replace('/\s*ইউনিয়ন(\s*পরিষদ)?$/u', '', $bn);
            }
            return [trim($en), trim($bn)];
        };

        $bnDigits = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
        $enDigits = ['0','1','2','3','4','5','6','7','8','9'];
        $normalizePostcode = function(?string $pc) use ($bnDigits, $enDigits): ?string {
            if ($pc === null || trim($pc) === '') return null;
            $pc = str_replace($bnDigits, $enDigits, trim($pc));
            if (preg_match('/\b\d{4}\b/', $pc, $m)) {
                return $m[0];
            }
            return $pc !== '' ? $pc : null;
        };

        // Step 1: Divisions and Districts (Always needed for parent structure)
        $sparql1 = <<<SPARQL
SELECT ?item ?itemLabelEn ?itemLabelBn ?type ?parent ?postcode WHERE {
  VALUES ?type { wd:Q878040 wd:Q152732 }
  ?item wdt:P31 ?type .
  OPTIONAL { ?item wdt:P131 ?parent . }
  OPTIONAL { ?item wdt:P281 ?postcode . }
  OPTIONAL { ?item rdfs:label ?itemLabelEn . FILTER(LANG(?itemLabelEn) = "en") }
  OPTIONAL { ?item rdfs:label ?itemLabelBn . FILTER(LANG(?itemLabelBn) = "bn") }
}
SPARQL;

        $rows1 = self::querySparql($sparql1);
        if (!$rows1) {
            throw new RuntimeException("Failed to retrieve divisions and districts from Wikidata.");
        }

        // Step 2: Upazilas and Thanas (Only if requested level 3 or 4)
        $rows2 = [];
        if ($hasL3 || $hasL4) {
            $sparql2 = <<<SPARQL
SELECT ?item ?itemLabelEn ?itemLabelBn ?type ?parent ?postcode WHERE {
  VALUES ?type { wd:Q620471 wd:Q19832617 }
  ?item wdt:P31 ?type .
  OPTIONAL { ?item wdt:P131 ?parent . }
  OPTIONAL { ?item wdt:P281 ?postcode . }
  OPTIONAL { ?item rdfs:label ?itemLabelEn . FILTER(LANG(?itemLabelEn) = "en") }
  OPTIONAL { ?item rdfs:label ?itemLabelBn . FILTER(LANG(?itemLabelBn) = "bn") }
}
SPARQL;

            $rows2 = self::querySparql($sparql2) ?: [];
        }

        // Step 3: Unions (Only if requested level 4)
        $rows3 = [];
        if ($hasL4) {
            $sparql3 = <<<SPARQL
SELECT ?item ?itemLabelEn ?itemLabelBn ?parent ?postcode WHERE {
  ?item wdt:P31 wd:Q3812392 .
  OPTIONAL { ?item wdt:P131 ?parent . }
  OPTIONAL { ?item wdt:P281 ?postcode . }
  OPTIONAL { ?item rdfs:label ?itemLabelEn . FILTER(LANG(?itemLabelEn) = "en") }
  OPTIONAL { ?item rdfs:label ?itemLabelBn . FILTER(LANG(?itemLabelBn) = "bn") }
}
SPARQL;

            $rows3 = self::querySparql($sparql3) ?: [];
        }

        $divisions = [];
        $districts = [];
        $upazilas = [];
        $unions = [];

        foreach ($rows1 as $r) {
            $qid = basename($r['item']['value'] ?? '');
            $t = basename($r['type']['value'] ?? '');
            $en = trim($r['itemLabelEn']['value'] ?? '');
            $bn = trim($r['itemLabelBn']['value'] ?? '');
            $parent = isset($r['parent']['value']) ? basename($r['parent']['value']) : null;
            $pc = $normalizePostcode($r['postcode']['value'] ?? null);
            if ($en === '' && $bn === '') continue;
            if ($en === '') $en = $bn;
            if ($bn === '') $bn = $en;

            if ($t === 'Q878040') {
                [$cEn, $cBn] = $cleanName($en, $bn, 'division');
                if (!isset($divisions[$qid])) {
                    $divisions[$qid] = ['qid' => $qid, 'en' => $cEn, 'loc' => $cBn, 'type' => 'division', 'postcode' => $pc, 'children' => []];
                }
            } elseif ($t === 'Q152732') {
                [$cEn, $cBn] = $cleanName($en, $bn, 'district');
                if (!isset($districts[$qid])) {
                    $districts[$qid] = ['qid' => $qid, 'en' => $cEn, 'loc' => $cBn, 'type' => 'district', 'parent' => $parent, 'postcode' => $pc, 'children' => []];
                }
            }
        }

        foreach ($rows2 as $r) {
            $qid = basename($r['item']['value'] ?? '');
            $t = basename($r['type']['value'] ?? '');
            $ptype = ($t === 'Q620471') ? 'upazila' : 'thana';
            $en = trim($r['itemLabelEn']['value'] ?? '');
            $bn = trim($r['itemLabelBn']['value'] ?? '');
            $parent = isset($r['parent']['value']) ? basename($r['parent']['value']) : null;
            $pc = $normalizePostcode($r['postcode']['value'] ?? null);
            if ($en === '' && $bn === '') continue;
            if ($en === '') $en = $bn;
            if ($bn === '') $bn = $en;

            [$cEn, $cBn] = $cleanName($en, $bn, $ptype);
            if (!isset($upazilas[$qid])) {
                $upazilas[$qid] = ['qid' => $qid, 'en' => $cEn, 'loc' => $cBn, 'type' => $ptype, 'parent' => $parent, 'postcode' => $pc, 'children' => []];
            }
        }

        foreach ($rows3 as $r) {
            $qid = basename($r['item']['value'] ?? '');
            $en = trim($r['itemLabelEn']['value'] ?? '');
            $bn = trim($r['itemLabelBn']['value'] ?? '');
            $parent = isset($r['parent']['value']) ? basename($r['parent']['value']) : null;
            $pc = $normalizePostcode($r['postcode']['value'] ?? null);
            if ($en === '' && $bn === '') continue;
            if ($en === '') $en = $bn;
            if ($bn === '') $bn = $en;

            [$cEn, $cBn] = $cleanName($en, $bn, 'union');
            if (!isset($unions[$qid])) {
                $unions[$qid] = ['qid' => $qid, 'en' => $cEn, 'loc' => $cBn, 'type' => 'union', 'parent' => $parent, 'postcode' => $pc];
            }
        }

        // Linking unions (L4)
        if ($hasL4) {
            foreach ($unions as $uQid => $uNode) {
                $pId = $uNode['parent'] ?? null;
                if ($hasL3) {
                    if ($pId && isset($upazilas[$pId])) {
                        $upazilas[$pId]['children'][] = $uNode;
                    } elseif ($pId && isset($districts[$pId])) {
                        $districts[$pId]['children'][] = $uNode;
                    }
                } elseif ($hasL2) {
                    $dstId = null;
                    if ($pId && isset($districts[$pId])) {
                        $dstId = $pId;
                    } elseif ($pId && isset($upazilas[$pId])) {
                        $dstId = $upazilas[$pId]['parent'] ?? null;
                    }
                    if ($dstId && isset($districts[$dstId])) {
                        $districts[$dstId]['children'][] = $uNode;
                    }
                } elseif ($hasL1) {
                    $divId = null;
                    if ($pId && isset($divisions[$pId])) {
                        $divId = $pId;
                    } elseif ($pId && isset($districts[$pId])) {
                        $divId = $districts[$pId]['parent'] ?? null;
                    } elseif ($pId && isset($upazilas[$pId])) {
                        $dstId = $upazilas[$pId]['parent'] ?? null;
                        $divId = ($dstId && isset($districts[$dstId])) ? ($districts[$dstId]['parent'] ?? null) : null;
                    }
                    if ($divId && isset($divisions[$divId])) {
                        $divisions[$divId]['children'][] = $uNode;
                    }
                }
            }
        }

        // Linking upazilas (L3)
        if ($hasL3) {
            foreach ($upazilas as $uZid => $uNode) {
                $pId = $uNode['parent'] ?? null;
                if ($hasL2) {
                    if ($pId && isset($districts[$pId])) {
                        $districts[$pId]['children'][] = $uNode;
                    }
                } elseif ($hasL1) {
                    $divId = ($pId && isset($districts[$pId])) ? ($districts[$pId]['parent'] ?? null) : null;
                    if ($divId && isset($divisions[$divId])) {
                        $divisions[$divId]['children'][] = $uNode;
                    }
                }
            }
        }

        // Linking districts (L2) -> divisions (L1)
        if ($hasL2 && $hasL1) {
            foreach ($districts as $dQid => $dNode) {
                $pId = $dNode['parent'] ?? null;
                if ($pId && isset($divisions[$pId])) {
                    $divisions[$pId]['children'][] = $dNode;
                } else {
                    foreach ($divisions as $divQid => $divNode) {
                        if (strcasecmp($divNode['en'], $dNode['en']) === 0) {
                            $divisions[$divQid]['children'][] = $dNode;
                            break;
                        }
                    }
                }
            }
        }

        $formatNode = function($node) use (&$formatNode) {
            $out = [
                'en' => $node['en'],
                'loc' => $node['loc'],
                'type' => $node['type']
            ];
            if (!empty($node['postcode'])) {
                $out['postcode'] = $node['postcode'];
            }
            if (!empty($node['children'])) {
                $sorted = $node['children'];
                usort($sorted, fn($a, $b) => strcmp($a['en'], $b['en']));
                $out['places'] = [];
                foreach ($sorted as $c) {
                    $out['places'][] = $formatNode($c);
                }
            }
            return $out;
        };

        $rootPlaces = [];
        if ($hasL1) {
            $sortedDivs = array_values($divisions);
            usort($sortedDivs, fn($a, $b) => strcmp($a['en'], $b['en']));
            foreach ($sortedDivs as $div) {
                $rootPlaces[] = $formatNode($div);
            }
        } elseif ($hasL2) {
            $sortedDistricts = array_values($districts);
            usort($sortedDistricts, fn($a, $b) => strcmp($a['en'], $b['en']));
            foreach ($sortedDistricts as $dst) {
                $rootPlaces[] = $formatNode($dst);
            }
        } elseif ($hasL3) {
            $sortedUpazilas = array_values($upazilas);
            usort($sortedUpazilas, fn($a, $b) => strcmp($a['en'], $b['en']));
            foreach ($sortedUpazilas as $upz) {
                $rootPlaces[] = $formatNode($upz);
            }
        } elseif ($hasL4) {
            $sortedUnions = array_values($unions);
            usort($sortedUnions, fn($a, $b) => strcmp($a['en'], $b['en']));
            foreach ($sortedUnions as $un) {
                $rootPlaces[] = $formatNode($un);
            }
        }

        $allBdLevels = [
            ['depth' => 1, 'types' => [['key' => 'division', 'name_en' => 'Division', 'name_loc' => 'বিভাগ']]],
            ['depth' => 2, 'types' => [['key' => 'district', 'name_en' => 'District', 'name_loc' => 'জেলা']]],
            ['depth' => 3, 'types' => [
                ['key' => 'upazila', 'name_en' => 'Upazila', 'name_loc' => 'উপজেলা'],
                ['key' => 'thana', 'name_en' => 'Thana', 'name_loc' => 'থানা']
            ]],
            ['depth' => 4, 'types' => [['key' => 'union', 'name_en' => 'Union', 'name_loc' => 'ইউনিয়ন']]]
        ];

        $bdLevels = [];
        $dIndex = 1;
        foreach ($allBdLevels as $lvlDef) {
            if (in_array($lvlDef['depth'], $selectedLevels, true)) {
                $lvlCopy = $lvlDef;
                $lvlCopy['depth'] = $dIndex++;
                $bdLevels[] = $lvlCopy;
            }
        }

        $dataset = [
            'manifest' => [
                'country_code' => $countryCode,
                'pack_type' => 'national',
                'country_name' => $countryName,
                'default_lang' => 'en',
                'local_lang' => 'bn',
                'version' => '1.0.0',
                'release_date' => date('Y-m-d'),
                'last_updated' => date('Y-m-d'),
                'author' => 'Wikidata (Wikimedia Community)',
                'contributors' => array_values(array_unique(array_filter(['Wikimedia Contributors', $contributor]))),
                'license' => 'CC0-1.0',
                'sources' => [
                    'Wikidata SPARQL Query Service (https://query.wikidata.org) licensed under Creative Commons CC0 1.0 Universal Public Domain Dedication'
                ],
                'notes' => 'Administrative dataset imported directly from Wikidata SPARQL Query Service (CC0-1.0 Public Domain).'
            ],
            'hierarchy' => [
                'model' => 'recursive_polymorphic',
                'child_key' => 'places',
                'levels' => $bdLevels
            ],
            'places' => $rootPlaces
        ];

        $outFile = rtrim($countriesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $countryCode . '.json';
        $encoded = json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException("Failed to encode JSON for BD dataset '{$countryCode}'.");
        }
        CountryCatalog::atomicWrite($outFile, $encoded);

        $total = 0;
        $countWalk = function($n) use (&$countWalk, &$total) {
            $total++;
            foreach ($n['places'] ?? [] as $c) $countWalk($c);
        };
        foreach ($rootPlaces as $rp) $countWalk($rp);

        return [
            'success' => true,
            'country_code' => $countryCode,
            'country_name' => $countryName,
            'total_places' => $total,
            'message' => "Successfully imported {$countryName} ({$countryCode}) with {$total} places from Wikidata (CC0 1.0).",
        ];
    }

    /**
     * General SPARQL pipeline for any ISO2 country.
     */
    private static function importGeneralCountry(
        string $countryCode,
        string $countryName,
        string $contributor,
        string $countriesDir,
        int $maxLevel = 0,
        array $selectedLevels = []
    ): array {
        if (empty($selectedLevels)) {
            $selectedLevels = ($maxLevel > 0) ? range(1, min($maxLevel, 2)) : [1, 2];
        }

        $hasL1 = in_array(1, $selectedLevels, true);
        $hasL2 = in_array(2, $selectedLevels, true);

        // Query country entity & first-level divisions
        $qCountry = <<<SPARQL
SELECT ?country ?countryLabel ?adm1 ?adm1Label ?adm1Postcode WHERE {
  ?country wdt:P297 "$countryCode" .
  ?country wdt:P150 ?adm1 .
  OPTIONAL { ?adm1 wdt:P281 ?adm1Postcode . }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
}
LIMIT 200
SPARQL;

        $rows = self::querySparql($qCountry);
        if (!$rows || empty($rows)) {
            throw new RuntimeException("No administrative divisions found in Wikidata for ISO code '{$countryCode}'. Please verify the country code or upload a Wikidata JSON export file.");
        }

        $detectedName = $rows[0]['countryLabel']['value'] ?? $countryName;
        if ($detectedName !== '' && $detectedName !== $countryCode && $countryName === $countryCode) {
            $countryName = $detectedName;
        }

        $adm1List = [];
        foreach ($rows as $r) {
            $qid = basename($r['adm1']['value'] ?? '');
            $label = trim($r['adm1Label']['value'] ?? '');
            $pc = trim($r['adm1Postcode']['value'] ?? '');
            if ($qid === '' || $label === '') continue;
            if (!isset($adm1List[$qid])) {
                $adm1List[$qid] = [
                    'qid' => $qid,
                    'en' => $label,
                    'loc' => $label,
                    'type' => 'state',
                    'postcode' => $pc !== '' ? $pc : null,
                    'places' => []
                ];
            }
        }

        // Query second-level subdivisions under adm1 (Only if requested level 2)
        $adm2List = [];
        if ($hasL2) {
            $adm1Qids = array_keys($adm1List);
            if (!empty($adm1Qids)) {
                $chunk = array_slice($adm1Qids, 0, 50);
                $valuesClause = implode(' ', array_map(fn($q) => 'wd:' . $q, $chunk));

                $qL2 = <<<SPARQL
SELECT ?adm1 ?adm2 ?adm2Label ?adm2Postcode WHERE {
  VALUES ?adm1 { $valuesClause }
  ?adm1 wdt:P150 ?adm2 .
  OPTIONAL { ?adm2 wdt:P281 ?adm2Postcode . }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
}
LIMIT 500
SPARQL;

                $rows2 = self::querySparql($qL2);
                if ($rows2) {
                    foreach ($rows2 as $r) {
                        $pQid = basename($r['adm1']['value'] ?? '');
                        $label = trim($r['adm2Label']['value'] ?? '');
                        $pc = trim($r['adm2Postcode']['value'] ?? '');
                        if ($pQid === '' || $label === '') continue;

                        $adm2Node = [
                            'en' => $label,
                            'loc' => $label,
                            'type' => 'county',
                            'postcode' => $pc !== '' ? $pc : null,
                        ];

                        if ($hasL1 && isset($adm1List[$pQid])) {
                            $adm1List[$pQid]['places'][] = $adm2Node;
                        } else {
                            $adm2List[] = $adm2Node;
                        }
                    }
                }
            }
        }

        if ($hasL1) {
            $placesTree = array_values($adm1List);
            usort($placesTree, fn($a, $b) => strcmp($a['en'], $b['en']));
        } else {
            $placesTree = array_values($adm2List);
            usort($placesTree, fn($a, $b) => strcmp($a['en'], $b['en']));
        }

        $allGenLevels = [
            ['depth' => 1, 'types' => [['key' => 'state', 'name_en' => 'State / Region', 'name_loc' => 'State / Region']]],
            ['depth' => 2, 'types' => [['key' => 'county', 'name_en' => 'County / District', 'name_loc' => 'County / District']]]
        ];

        $genLevels = [];
        $dIndex = 1;
        foreach ($allGenLevels as $lvlDef) {
            if (in_array($lvlDef['depth'], $selectedLevels, true)) {
                $lvlCopy = $lvlDef;
                $lvlCopy['depth'] = $dIndex++;
                $genLevels[] = $lvlCopy;
            }
        }

        $dataset = [
            'manifest' => [
                'country_code' => $countryCode,
                'pack_type' => 'national',
                'country_name' => $countryName,
                'default_lang' => 'en',
                'local_lang' => 'en',
                'version' => '1.0.0',
                'release_date' => date('Y-m-d'),
                'last_updated' => date('Y-m-d'),
                'author' => 'Wikidata (Wikimedia Community)',
                'contributors' => array_values(array_unique(array_filter(['Wikimedia Contributors', $contributor]))),
                'license' => 'CC0-1.0',
                'sources' => [
                    'Wikidata SPARQL Query Service (https://query.wikidata.org) licensed under Creative Commons CC0 1.0 Universal Public Domain Dedication'
                ],
                'notes' => "Administrative hierarchy dataset for {$countryName} imported directly from Wikidata SPARQL Query Service (CC0-1.0 Public Domain)."
            ],
            'hierarchy' => [
                'model' => 'recursive_polymorphic',
                'child_key' => 'places',
                'levels' => $genLevels
            ],
            'places' => $placesTree
        ];

        $outFile = rtrim($countriesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $countryCode . '.json';
        $encoded = json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException("Failed to encode JSON for dataset '{$countryCode}'.");
        }
        CountryCatalog::atomicWrite($outFile, $encoded);

        $total = 0;
        $countWalk = function($n) use (&$countWalk, &$total) {
            $total++;
            foreach ($n['places'] ?? [] as $c) $countWalk($c);
        };
        foreach ($placesTree as $rp) $countWalk($rp);

        return [
            'success' => true,
            'country_code' => $countryCode,
            'country_name' => $countryName,
            'total_places' => $total,
            'message' => "Successfully imported {$countryName} ({$countryCode}) with {$total} places from Wikidata (CC0 1.0).",
        ];
    }

    /**
     * Discovers available administrative division levels and unit counts for a country.
     *
     * @return array{success: bool, country_code: string, country_name: string, levels: array<int, array{depth: int, name: string, name_loc: string, count: int|null}>}
     */
    public static function discoverCountryLevels(string $countryCode, ?string $cacheDir = null): array
    {
        $countryCode = CountryCatalog::sanitizeCode($countryCode);

        if ($countryCode === 'BD') {
            return [
                'success' => true,
                'country_code' => 'BD',
                'country_name' => 'Bangladesh',
                'levels' => [
                    ['depth' => 1, 'name' => 'Division', 'name_loc' => 'বিভাগ', 'count' => 8],
                    ['depth' => 2, 'name' => 'District', 'name_loc' => 'জেলা', 'count' => 64],
                    ['depth' => 3, 'name' => 'Upazila / Thana', 'name_loc' => 'উপজেলা / থানা', 'count' => 495],
                    ['depth' => 4, 'name' => 'Union Council', 'name_loc' => 'ইউনিয়ন', 'count' => 4540],
                ]
            ];
        }

        if ($countryCode === 'GB') {
            return [
                'success' => true,
                'country_code' => 'GB',
                'country_name' => 'United Kingdom',
                'levels' => [
                    ['depth' => 1, 'name' => 'Constituent Country', 'name_loc' => 'Constituent Country', 'count' => 4],
                    ['depth' => 2, 'name' => 'County / Council Area', 'name_loc' => 'County / Council Area', 'count' => 120],
                    ['depth' => 3, 'name' => 'District / Borough / City / Town', 'name_loc' => 'District / Borough / Town', 'count' => 600],
                ]
            ];
        }

        $cacheDir = $cacheDir ?? sys_get_temp_dir();
        $cacheFile = rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wikidata_levels_' . $countryCode . '.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400 * 7)) {
            $cached = @file_get_contents($cacheFile);
            if ($cached) {
                $dec = json_decode($cached, true);
                if (is_array($dec) && !empty($dec['levels'])) {
                    return $dec;
                }
            }
        }

        $cleanType = function(string $raw): string {
            $t = trim($raw);
            $t = preg_replace('/\s+of\s+[A-Za-z\s]+$/i', '', $t);
            $t = preg_replace('/\s+in\s+[A-Za-z\s]+$/i', '', $t);
            $t = preg_replace('/^U\.S\.\s+/i', '', $t);
            $t = trim($t);
            return $t !== '' ? ucwords($t) : 'Region';
        };

        // Query level 1
        $qL1 = <<<SPARQL
SELECT ?tLabel (COUNT(?adm1) as ?cnt) WHERE {
  ?country wdt:P297 "$countryCode" .
  ?country wdt:P150 ?adm1 .
  OPTIONAL { ?adm1 wdt:P31 ?t . }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
}
GROUP BY ?tLabel
ORDER BY DESC(?cnt)
LIMIT 3
SPARQL;

        $rows1 = self::querySparql($qL1);
        $l1Name = 'State / Region';
        $l1Count = 0;
        if (!empty($rows1)) {
            foreach ($rows1 as $r) {
                $rawL = trim((string)($r['tLabel']['value'] ?? ''));
                $cnt = (int)($r['cnt']['value'] ?? 0);
                if ($rawL !== '' && !str_starts_with($rawL, 'Q') && $rawL !== 'administrative territorial entity') {
                    $l1Name = $cleanType($rawL);
                    $l1Count += $cnt;
                    break;
                }
                $l1Count += $cnt;
            }
        }

        // Query level 2
        $qL2 = <<<SPARQL
SELECT ?tLabel (COUNT(?adm2) as ?cnt) WHERE {
  ?country wdt:P297 "$countryCode" .
  ?country wdt:P150 ?adm1 .
  ?adm1 wdt:P150 ?adm2 .
  OPTIONAL { ?adm2 wdt:P31 ?t . }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
}
GROUP BY ?tLabel
ORDER BY DESC(?cnt)
LIMIT 3
SPARQL;

        $rows2 = self::querySparql($qL2);
        $l2Name = 'County / District';
        $l2Count = 0;
        if (!empty($rows2)) {
            foreach ($rows2 as $r) {
                $rawL = trim((string)($r['tLabel']['value'] ?? ''));
                $cnt = (int)($r['cnt']['value'] ?? 0);
                if ($rawL !== '' && !str_starts_with($rawL, 'Q') && $rawL !== 'administrative territorial entity') {
                    $l2Name = $cleanType($rawL);
                    $l2Count += $cnt;
                    break;
                }
                $l2Count += $cnt;
            }
        }

        $levels = [];
        $levels[] = [
            'depth' => 1,
            'name' => $l1Name,
            'name_loc' => $l1Name,
            'count' => $l1Count > 0 ? $l1Count : null
        ];

        if ($l2Count > 0 || !empty($rows2)) {
            $levels[] = [
                'depth' => 2,
                'name' => $l2Name,
                'name_loc' => $l2Name,
                'count' => $l2Count > 0 ? $l2Count : null
            ];
        } else {
            $levels[] = [
                'depth' => 2,
                'name' => 'County / District',
                'name_loc' => 'County / District',
                'count' => null
            ];
        }

        $res = [
            'success' => true,
            'country_code' => $countryCode,
            'country_name' => $countryCode,
            'levels' => $levels
        ];

        @file_put_contents($cacheFile, json_encode($res, JSON_UNESCAPED_UNICODE));
        return $res;
    }

    /**
     * Opt-in method to download live country catalog from Wikidata SPARQL.
     * Only executed when user explicitly requests live directory fetch.
     * Caches result locally so subsequent lookups do not re-query Wikidata.
     *
     * @return array<string, mixed>
     */
    public static function fetchLiveCountryCatalog(bool $forceRefresh = false, ?string $cacheDir = null): array
    {
        $cacheDir = $cacheDir ?? sys_get_temp_dir();
        $cacheFile = rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wikidata_countries_cache.json';

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

        $sparql = <<<'SPARQL'
SELECT DISTINCT ?iso ?countryLabel ?country WHERE {
  ?country wdt:P297 ?iso .
  FILTER NOT EXISTS { ?country wdt:P576 ?dissolved . }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
}
ORDER BY ?countryLabel
SPARQL;

        $bindings = self::querySparql($sparql);

        if ($bindings === null) {
            throw new RuntimeException("Unable to connect to Wikidata SPARQL query service. Please check internet connectivity.");
        }

        $countries = [];
        $seen = [];
        foreach ($bindings as $b) {
            $iso = strtoupper(trim((string)($b['iso']['value'] ?? '')));
            $name = trim((string)($b['countryLabel']['value'] ?? ''));
            $qid = basename(trim((string)($b['country']['value'] ?? '')));
            if (strlen($iso) !== 2 || empty($name)) continue;
            if (isset($seen[$iso])) continue;
            $seen[$iso] = true;
            $countries[] = [
                'code' => $iso,
                'name' => $name,
                'qid' => $qid,
            ];
        }

        usort($countries, fn($a, $b) => strcmp($a['name'], $b['name']));

        $result = [
            'success' => true,
            'from_cache' => false,
            'timestamp' => date('Y-m-d H:i:s'),
            'total_countries' => count($countries),
            'countries' => $countries,
        ];

        @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $result;
    }
}
