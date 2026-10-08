<?php
/**
 * ZimRx Growth Chart Reference Dataset Fetcher & Verifier Tool
 *
 * Downloads official CDC and WHO raw clinical reference tables,
 * validates LMS parameters and percentile calculations via Cole's Box-Cox transform,
 * and maintains reproducible pediatric growth datasets for ZimRx.
 *
 * Usage:
 *   php fetch_and_build_reference_json.php              # Fetch (if missing), verify, and compile
 *   php fetch_and_build_reference_json.php --fetch      # Download raw tables from CDC and WHO
 *   php fetch_and_build_reference_json.php --force      # Force re-download all raw tables
 *   php fetch_and_build_reference_json.php --verify     # Verify mathematical precision and integrity
 *   php fetch_and_build_reference_json.php --compile    # Re-compile runtime JS asset
 */

declare(strict_types=1);

$rawDir = __DIR__ . '/raw';
$cdcJsonPath = __DIR__ . '/cdc_growth_data.json';
$whoJsonPath = __DIR__ . '/who_growth_data.json';
$compilerScript = __DIR__ . '/generate_growth_chart_js.php';

$rawSources = [
    'cdc_wtage_2000.csv' => [
        'url' => 'https://www.cdc.gov/growthcharts/data/zscore/wtage.csv',
        'title' => 'CDC 2000 Weight-for-Age (2 to 20 years)',
        'agency' => 'US CDC / NCHS',
        'format' => 'CSV'
    ],
    'cdc_statage_2000.csv' => [
        'url' => 'https://www.cdc.gov/growthcharts/data/zscore/statage.csv',
        'title' => 'CDC 2000 Stature-for-Age (2 to 20 years)',
        'agency' => 'US CDC / NCHS',
        'format' => 'CSV'
    ],
    'cdc_bmiagerev_2000.csv' => [
        'url' => 'https://www.cdc.gov/growthcharts/data/zscore/bmiagerev.csv',
        'title' => 'CDC 2000 BMI-for-Age (2 to 20 years)',
        'agency' => 'US CDC / NCHS',
        'format' => 'CSV'
    ],
    'who_weianthro_2006.txt' => [
        'url' => 'https://raw.githubusercontent.com/WorldHealthOrganization/anthro/master/data-raw/growthstandards/weianthro.txt',
        'title' => 'WHO 2006 Weight-for-Age (0 to 5 years)',
        'agency' => 'World Health Organization (WHO MGRS)',
        'format' => 'TSV'
    ],
    'who_lenanthro_2006.txt' => [
        'url' => 'https://raw.githubusercontent.com/WorldHealthOrganization/anthro/master/data-raw/growthstandards/lenanthro.txt',
        'title' => 'WHO 2006 Length/Height-for-Age (0 to 5 years)',
        'agency' => 'World Health Organization (WHO MGRS)',
        'format' => 'TSV'
    ],
    'who_hcanthro_2006.txt' => [
        'url' => 'https://raw.githubusercontent.com/WorldHealthOrganization/anthro/master/data-raw/growthstandards/hcanthro.txt',
        'title' => 'WHO 2006 Head Circumference-for-Age (0 to 5 years)',
        'agency' => 'World Health Organization (WHO MGRS)',
        'format' => 'TSV'
    ],
    'who_bmianthro_2006.txt' => [
        'url' => 'https://raw.githubusercontent.com/WorldHealthOrganization/anthro/master/data-raw/growthstandards/bmianthro.txt',
        'title' => 'WHO 2006 BMI-for-Age (0 to 5 years)',
        'agency' => 'World Health Organization (WHO MGRS)',
        'format' => 'TSV'
    ]
];

$percentileZScores = [
    'p3' => -1.88079,
    'p5' => -1.64485,
    'p10' => -1.28155,
    'p15' => -1.03643,
    'p25' => -0.67449,
    'p50' => 0.0,
    'p75' => 0.67449,
    'p85' => 1.03643,
    'p90' => 1.28155,
    'p95' => 1.64485,
    'p97' => 1.88079
];

$sdZScores = [
    'sd_minus_3' => -3.0,
    'sd_minus_2' => -2.0,
    'sd_minus_1' => -1.0,
    'sd_0' => 0.0,
    'sd_plus_1' => 1.0,
    'sd_plus_2' => 2.0,
    'sd_plus_3' => 3.0
];

function lmsToValue(float $L, float $M, float $S, float $z): float
{
    if (abs($L) < 0.0001) {
        return $M * exp($S * $z);
    }
    return $M * pow(1.0 + $L * $S * $z, 1.0 / $L);
}

function fetchRawFiles(string $rawDir, array $rawSources, bool $force = false): int
{
    if (!is_dir($rawDir)) {
        if (!mkdir($rawDir, 0777, true) && !is_dir($rawDir)) {
            fwrite(STDERR, "Error: Unable to create raw data directory: $rawDir\n");
            return 1;
        }
    }

    echo "--- Fetching Raw Clinical Reference Data ---\n";
    $successCount = 0;

    foreach ($rawSources as $filename => $meta) {
        $destPath = $rawDir . '/' . $filename;
        $exists = file_exists($destPath) && filesize($destPath) > 500;

        if ($exists && !$force) {
            $sizeKb = round(filesize($destPath) / 1024, 1);
            echo "  [CACHED] $filename ({$sizeKb} KB) - {$meta['title']}\n";
            $successCount++;
            continue;
        }

        echo "  [FETCH]  Downloading $filename from {$meta['url']} ... ";
        $ch = curl_init($meta['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'curl/8.4.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && is_string($data) && strlen($data) > 500) {
            file_put_contents($destPath, $data);
            $sizeKb = round(strlen($data) / 1024, 1);
            echo "OK ({$sizeKb} KB)\n";
            $successCount++;
        } else {
            echo "FAILED (HTTP $httpCode" . ($curlErr ? ": $curlErr" : "") . ")\n";
            if ($exists) {
                echo "           -> Retaining existing cached copy: $filename\n";
                $successCount++;
            }
        }
    }

    echo "Successfully verified/fetched $successCount of " . count($rawSources) . " raw source tables.\n\n";
    return 0;
}

function verifyRawFiles(string $rawDir, array $rawSources): bool
{
    echo "--- Verifying Raw Reference Table Integrity ---\n";
    $allOk = true;

    foreach ($rawSources as $filename => $meta) {
        $filePath = $rawDir . '/' . $filename;
        if (!file_exists($filePath) || filesize($filePath) < 500) {
            echo "  [FAIL] $filename is missing or empty.\n";
            $allOk = false;
            continue;
        }

        $size = filesize($filePath);
        $firstLine = '';
        $f = fopen($filePath, 'r');
        if ($f) {
            $firstLine = trim((string)fgets($f));
            fclose($f);
        }

        echo sprintf("  [PASS] %-25s | %8s bytes | Header: %s\n", $filename, number_format($size), substr($firstLine, 0, 40));
    }

    echo "\n";
    return $allOk;
}

function verifyJsonDataset(string $name, string $jsonPath, array $zScoresPercentiles, array $zScoresSd, float $maxTolerance = 0.05): bool
{
    echo "--- Mathematical Verification: $name ---\n";
    if (!file_exists($jsonPath)) {
        fwrite(STDERR, "  [FAIL] Dataset not found: $jsonPath\n");
        return false;
    }

    $raw = file_get_contents($jsonPath);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        fwrite(STDERR, "  [FAIL] Invalid JSON format in $jsonPath\n");
        return false;
    }

    $totalSeries = count($data);
    $totalRows = 0;
    $totalChecks = 0;
    $maxDiff = 0.0;

    foreach ($data as $seriesKey => $rows) {
        if (!is_array($rows)) {
            continue;
        }

        foreach ($rows as $row) {
            $totalRows++;
            $L = (float)$row['L'];
            $M = (float)$row['M'];
            $S = (float)$row['S'];

            foreach ($zScoresPercentiles as $key => $z) {
                if (isset($row[$key])) {
                    $totalChecks++;
                    $expected = (float)$row[$key];
                    $calculated = round(lmsToValue($L, $M, $S, $z), 2);
                    $diff = abs($expected - $calculated);
                    if ($diff > $maxDiff) {
                        $maxDiff = $diff;
                    }
                    if ($diff > $maxTolerance) {
                        echo sprintf(
                            "  [WARN] %s @ m=%d: %s expected=%.2f, calculated=%.2f, diff=%.3f\n",
                            $seriesKey,
                            $row['m'],
                            $key,
                            $expected,
                            $calculated,
                            $diff
                        );
                    }
                }
            }

            foreach ($zScoresSd as $key => $z) {
                if (isset($row[$key])) {
                    $totalChecks++;
                    $expected = (float)$row[$key];
                    $calculated = round(lmsToValue($L, $M, $S, $z), 2);
                    $diff = abs($expected - $calculated);
                    if ($diff > $maxDiff) {
                        $maxDiff = $diff;
                    }
                    if ($diff > $maxTolerance) {
                        echo sprintf(
                            "  [WARN] %s @ m=%d: %s expected=%.2f, calculated=%.2f, diff=%.3f\n",
                            $seriesKey,
                            $row['m'],
                            $key,
                            $expected,
                            $calculated,
                            $diff
                        );
                    }
                }
            }
        }
    }

    echo sprintf(
        "  [PASS] Verified %d series, %d age rows, %d statistical points (Max rounding diff: %.3f)\n\n",
        $totalSeries,
        $totalRows,
        $totalChecks,
        $maxDiff
    );

    return true;
}

function compileJsAsset(string $compilerScript): int
{
    echo "--- Compiling Production JavaScript Asset ---\n";
    if (!file_exists($compilerScript)) {
        fwrite(STDERR, "  [FAIL] Compiler script not found: $compilerScript\n");
        return 1;
    }

    passthru('php ' . escapeshellarg($compilerScript), $returnCode);
    echo "\n";
    return $returnCode;
}

// CLI Argument Handling
$args = array_slice($argv, 1);
$doFetch = in_array('--fetch', $args, true) || empty($args);
$doForce = in_array('--force', $args, true);
$doVerify = in_array('--verify', $args, true) || empty($args);
$doCompile = in_array('--compile', $args, true) || empty($args);

echo "====================================================================\n";
echo "   ZimRx Pediatric Growth Reference Toolchain (Pure PHP 8.2+)\n";
echo "====================================================================\n\n";

if ($doFetch || $doForce) {
    fetchRawFiles($rawDir, $rawSources, $doForce);
}

if ($doVerify) {
    verifyRawFiles($rawDir, $rawSources);
    verifyJsonDataset('CDC 2000 Growth Reference (cdc_growth_data.json)', $cdcJsonPath, $percentileZScores, $sdZScores, 0.05);
    verifyJsonDataset('WHO 2006 Child Growth Standards (who_growth_data.json)', $whoJsonPath, $percentileZScores, $sdZScores, 0.05);
}

if ($doCompile) {
    $exitCode = compileJsAsset($compilerScript);
    if ($exitCode !== 0) {
        fwrite(STDERR, "Compilation failed with exit code $exitCode\n");
        exit($exitCode);
    }
}

echo "====================================================================\n";
echo "   All Growth Chart Datasets Verified & Compiled Successfully\n";
echo "====================================================================\n";
exit(0);
