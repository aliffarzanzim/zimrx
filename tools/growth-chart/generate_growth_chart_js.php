<?php
/**
 * ZimRx Growth Chart Generator
 * =============================
 * Generates application/public/assets/js/data/growth_chart_data.js from WHO and CDC
 * clinical reference tables.
 *
 * Usage:
 *     php generate_growth_chart_js.php
 */

declare(strict_types=1);

$scriptDir = __DIR__;
$appDir = dirname($scriptDir, 2) . '/application';
$targetJs = $appDir . '/public/assets/js/data/growth_chart_data.js';

$whoJsonPath = $scriptDir . '/who_growth_data.json';
$cdcJsonPath = $scriptDir . '/cdc_growth_data.json';
$engineJsPath = $scriptDir . '/growth_chart_engine.js';

if (!file_exists($whoJsonPath)) {
    throw new RuntimeException("Missing WHO dataset: {$whoJsonPath}");
}
if (!file_exists($cdcJsonPath)) {
    throw new RuntimeException("Missing CDC dataset: {$cdcJsonPath}");
}
if (!file_exists($engineJsPath)) {
    throw new RuntimeException("Missing engine code: {$engineJsPath}");
}

$whoData = json_decode((string)file_get_contents($whoJsonPath), true, 512, JSON_THROW_ON_ERROR);
$cdcData = json_decode((string)file_get_contents($cdcJsonPath), true, 512, JSON_THROW_ON_ERROR);
$engineCode = trim((string)file_get_contents($engineJsPath));

function formatRow(array $rowDict): string
{
    $items = [];
    foreach ($rowDict as $k => $v) {
        if (is_int($v) || is_float($v)) {
            $items[] = '"' . $k . '": ' . $v;
        } else {
            $items[] = '"' . $k . '": "' . $v . '"';
        }
    }
    return '      { ' . implode(', ', $items) . ' }';
}

function formatDataset(array $datasetDict): string
{
    $lines = ["{\n"];
    $keys = array_keys($datasetDict);
    $totalKeys = count($keys);

    foreach ($keys as $kIdx => $key) {
        $rows = $datasetDict[$key];
        $lines[] = '    "' . $key . "\": [\n";
        $totalRows = count($rows);
        foreach ($rows as $rIdx => $row) {
            $comma = ($rIdx < $totalRows - 1) ? ',' : '';
            $lines[] = formatRow($row) . $comma . "\n";
        }
        $tableComma = ($kIdx < $totalKeys - 1) ? ',' : '';
        $lines[] = "    ]{$tableComma}\n";
    }
    $lines[] = '  }';
    return implode('', $lines);
}

$header = "// Child growth chart dataset and calculation engine for WHO (0-5y) and CDC (2-20y) standards.\n"
    . "// Generated via tools/growth-chart/generate_growth_chart_js.php.\n\n"
    . "(function(root) {\n"
    . "  'use strict';\n\n"
    . "  const WHO_DATA = ";

$cdcStart = "\n\n  const CDC_DATA = ";
$calcStart = "\n\n  // Clinical calculation engine and percentile/Z-score classification logic\n\n  ";

$jsContent = $header
    . formatDataset($whoData)
    . ";"
    . $cdcStart
    . formatDataset($cdcData)
    . ";"
    . $calcStart
    . $engineCode
    . "\n\n  root.ZimRxGrowthData = {\n"
    . "    WHO: WHO_DATA,\n"
    . "    CDC: CDC_DATA,\n"
    . "    calculateZScore,\n"
    . "    getGrowthClassification,\n"
    . "    getMUACClassification,\n"
    . "    getAgeInMonths,\n"
    . "    getLmsAtAge\n"
    . "  };\n\n"
    . "})(typeof window !== 'undefined' ? window : this);\n";

$targetDir = dirname($targetJs);
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

file_put_contents($targetJs, str_replace("\r\n", "\n", $jsContent));

$totalLines = count(explode("\n", $jsContent));
$sizeKb = strlen($jsContent) / 1024;
echo "[OK] Generated {$targetJs}\n";
printf("     Lines: %d | Size: %.1f KB\n", $totalLines, $sizeKb);
