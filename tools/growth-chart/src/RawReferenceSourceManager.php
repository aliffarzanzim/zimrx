<?php

declare(strict_types=1);

namespace ZimRx\GrowthChart;

use RuntimeException;

final class RawReferenceSourceManager
{
    private const MAX_DOWNLOAD_BYTES = 2_000_000;

    private const REFERENCE_METRICS = [
        'cdc_2000' => ['wfa', 'hfa', 'bmifa'],
        'cdc_2022_extended' => ['bmifa'],
        'who_2006' => ['wfa', 'lhfa', 'hcfa', 'bmifa'],
        'who_2007' => ['wfa', 'hfa', 'bmifa'],
    ];

    public static function loadManifest(string $manifestPath): array
    {
        $contents = file_get_contents($manifestPath);
        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read source manifest: {$manifestPath}");
        }

        $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($manifest)
            || ($manifest['schemaVersion'] ?? null) !== 3
            || !isset($manifest['whoDaysPerMonth'], $manifest['sources'])
            || !isset($manifest['rightsProfiles'])
            || !is_array($manifest['sources'])
            || !is_array($manifest['rightsProfiles'])
            || !is_numeric($manifest['whoDaysPerMonth'])
            || (float)$manifest['whoDaysPerMonth'] <= 0.0
        ) {
            throw new RuntimeException('Invalid raw reference source manifest.');
        }

        $files = [];
        $metrics = [];
        foreach ($manifest['sources'] as $source) {
            if (!is_array($source)) {
                throw new RuntimeException('Invalid source entry in raw reference manifest.');
            }
            foreach (['file', 'url', 'sha256', 'format', 'reference', 'metric'] as $key) {
                if (!isset($source[$key]) || !is_string($source[$key])) {
                    throw new RuntimeException("Source manifest entry is missing {$key}.");
                }
            }

            $file = $source['file'];
            if ($file === '' || basename($file) !== $file || str_contains($file, '..') || isset($files[$file])) {
                throw new RuntimeException("Invalid or duplicate raw source filename: {$file}");
            }
            if (!preg_match('/^[a-f0-9]{64}$/', $source['sha256'])) {
                throw new RuntimeException("Invalid SHA-256 value for {$file}.");
            }
            $reference = $source['reference'];
            $expectedFormat = str_starts_with($reference, 'who_') ? 'tsv' : 'csv';
            if (!isset(self::REFERENCE_METRICS[$reference])
                || !in_array($source['metric'], self::REFERENCE_METRICS[$reference] ?? [], true)
                || $source['format'] !== $expectedFormat
            ) {
                throw new RuntimeException("Unsupported source mapping for {$file}.");
            }
            $rights = $manifest['rightsProfiles'][$reference] ?? null;
            if (!is_array($rights)
                || !in_array($rights['status'] ?? null, [
                    'documented_public_domain',
                    'reuse_terms_unconfirmed',
                    'licensed_for_product_incorporation',
                ], true)
            ) {
                throw new RuntimeException("Missing or invalid rights profile for {$reference}.");
            }
            foreach (['license', 'attribution', 'citation', 'terms', 'termsUrl'] as $key) {
                if (!isset($rights[$key]) || !is_string($rights[$key]) || trim($rights[$key]) === '') {
                    throw new RuntimeException("Rights profile for {$reference} is missing {$key}.");
                }
            }

            $url = parse_url($source['url']);
            $allowedHosts = ['www.cdc.gov', 'raw.githubusercontent.com'];
            if (($url['scheme'] ?? '') !== 'https'
                || !in_array(strtolower((string)($url['host'] ?? '')), $allowedHosts, true)
                || isset($url['user'])
                || isset($url['pass'])
                || isset($url['port'])
            ) {
                throw new RuntimeException("Untrusted or invalid HTTPS source URL for {$file}.");
            }
            if (str_starts_with($reference, 'who_')) {
                $repository = $reference === 'who_2007' ? 'anthroplus' : 'anthro';
                if (!preg_match('~WorldHealthOrganization/' . $repository . '/[a-f0-9]{40}/data-raw/~', $source['url'])) {
                    throw new RuntimeException("WHO source must use its pinned repository revision: {$file}");
                }
            }

            $metricKey = $reference . ':' . $source['metric'];
            if (isset($metrics[$metricKey])) {
                throw new RuntimeException("Duplicate dataset mapping in source manifest: {$metricKey}");
            }
            $files[$file] = true;
            $metrics[$metricKey] = true;
        }

        if (count($manifest['sources']) !== 11) {
            throw new RuntimeException('The growth chart source manifest must list exactly eleven reference tables.');
        }

        $manifest['whoDaysPerMonth'] = (float)$manifest['whoDaysPerMonth'];
        return $manifest;
    }

    public static function ensureFiles(string $rawDirectory, array $sources, bool $force = false): void
    {
        if (!is_dir($rawDirectory) && !mkdir($rawDirectory, 0755, true) && !is_dir($rawDirectory)) {
            throw new RuntimeException("Unable to create raw data directory: {$rawDirectory}");
        }

        foreach ($sources as $source) {
            $path = $rawDirectory . DIRECTORY_SEPARATOR . $source['file'];
            if (is_file($path)) {
                $actualHash = hash_file('sha256', $path);
                if (is_string($actualHash) && hash_equals($source['sha256'], $actualHash)) {
                    if (!$force) {
                        echo "[CACHED] {$source['file']} sha256 verified\n";
                        continue;
                    }
                } elseif (!$force) {
                    throw new RuntimeException("Checksum mismatch for {$source['file']}; inspect it or re-download with --force.");
                }
            }

            self::download($path, $source);
            echo "[FETCHED] {$source['file']} sha256 verified\n";
        }
    }

    public static function verifyFiles(string $rawDirectory, array $sources): void
    {
        foreach ($sources as $source) {
            $path = $rawDirectory . DIRECTORY_SEPARATOR . $source['file'];
            if (!is_file($path)) {
                throw new RuntimeException("Missing raw reference table: {$source['file']}");
            }
            $actualHash = hash_file('sha256', $path);
            if (!is_string($actualHash) || !hash_equals($source['sha256'], $actualHash)) {
                throw new RuntimeException("Checksum mismatch for raw reference table: {$source['file']}");
            }
            echo "[PASS] {$source['file']} sha256 verified\n";
        }
    }

    private static function download(string $destination, array $source): void
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required to download missing raw tables.');
        }

        $handle = curl_init($source['url']);
        if ($handle === false) {
            throw new RuntimeException("Unable to initialize download for {$source['file']}.");
        }

        $data = '';
        $receivedBytes = 0;
        $sizeLimitExceeded = false;
        $curlOptions = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'ZimRx growth chart reference data builder',
            CURLOPT_FAILONERROR => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$data, &$receivedBytes, &$sizeLimitExceeded): int {
                $chunkLength = strlen($chunk);
                if ($chunkLength > self::MAX_DOWNLOAD_BYTES - $receivedBytes) {
                    $sizeLimitExceeded = true;
                    return 0;
                }
                $data .= $chunk;
                $receivedBytes += $chunkLength;
                return $chunkLength;
            },
        ];
        if (defined('CURLOPT_PROTOCOLS_STR') && defined('CURLOPT_REDIR_PROTOCOLS_STR')) {
            $curlOptions[constant('CURLOPT_PROTOCOLS_STR')] = 'https';
            $curlOptions[constant('CURLOPT_REDIR_PROTOCOLS_STR')] = 'https';
        } else {
            $curlOptions[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            $curlOptions[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        if (!curl_setopt_array($handle, $curlOptions)) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new RuntimeException("Unable to configure secure download for {$source['file']}: {$error}");
        }

        $downloadSucceeded = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $effectiveUrl = (string)curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($handle);
        curl_close($handle);

        if ($sizeLimitExceeded) {
            throw new RuntimeException("Downloaded raw table exceeds the 2 MB limit: {$source['file']}");
        }
        $sourceHost = strtolower((string)parse_url($source['url'], PHP_URL_HOST));
        $finalHost = strtolower((string)parse_url($effectiveUrl, PHP_URL_HOST));
        if ($status !== 200 || $downloadSucceeded !== true) {
            throw new RuntimeException("Download failed for {$source['file']} (HTTP {$status}): {$error}");
        }
        if ($finalHost !== $sourceHost || parse_url($effectiveUrl, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException("Download redirected outside the pinned source host for {$source['file']}.");
        }
        if (!hash_equals($source['sha256'], hash('sha256', $data))) {
            throw new RuntimeException("Downloaded content checksum does not match the manifest: {$source['file']}");
        }

        AtomicFileWriter::writeIfChanged($destination, $data);
    }
}
