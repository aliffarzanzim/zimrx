<?php

declare(strict_types=1);

use ZimRx\GrowthChart\AtomicFileWriter;
use ZimRx\GrowthChart\GrowthDataCompiler;
use ZimRx\GrowthChart\GrowthReferenceBuilder;
use ZimRx\GrowthChart\RawReferenceSourceManager;

$toolDirectory = dirname(__DIR__);
require_once $toolDirectory . '/src/AtomicFileWriter.php';
require_once $toolDirectory . '/src/RawReferenceSourceManager.php';
require_once $toolDirectory . '/src/GrowthReferenceBuilder.php';
require_once $toolDirectory . '/src/GrowthDataCompiler.php';

$options = array_slice($argv, 1);
$allowedOptions = ['--force', '--verify'];
if (count($options) > 1 || array_diff($options, $allowedOptions) !== []) {
    fwrite(STDERR, "Usage: php tools/growth-chart/bin/build_growth_chart_data.php [--force|--verify]\n");
    exit(2);
}

$verifyOnly = $options === ['--verify'];
$forceFetch = $options === ['--force'];
$rawDirectory = $toolDirectory . '/raw';
$enginePath = $toolDirectory . '/src/js/growth_chart_engine.js';
$targets = [
    $toolDirectory . '/visualizer/data/growth_chart_data.json' => 'data',
    $toolDirectory . '/visualizer/assets/js/growth_chart_engine.js' => 'engine',
];

try {
    $sourceManifest = RawReferenceSourceManager::loadManifest($rawDirectory . '/source-manifest.json');
    if (!$verifyOnly) {
        RawReferenceSourceManager::ensureFiles($rawDirectory, $sourceManifest['sources'], $forceFetch);
    }
    RawReferenceSourceManager::verifyFiles($rawDirectory, $sourceManifest['sources']);

    $datasets = GrowthReferenceBuilder::build($rawDirectory, $sourceManifest);
    $engineCode = file_get_contents($enginePath);
    if (!is_string($engineCode)) {
        throw new RuntimeException("Unable to read growth chart engine: {$enginePath}");
    }
    $runtimeData = GrowthDataCompiler::compile($datasets);
    $outputs = [];
    foreach ($targets as $path => $type) {
        $outputs[$path] = $type === 'data' ? $runtimeData : $engineCode;
    }

    if ($verifyOnly) {
        foreach ($outputs as $path => $contents) {
            $currentHash = is_file($path) ? hash_file('sha256', $path) : false;
            if (!is_string($currentHash) || !hash_equals(hash('sha256', $contents), $currentHash)) {
                throw new RuntimeException("Generated output differs from {$path}. Run the default build command.");
            }
        }
        echo "[PASS] Growth data and engine outputs reproduce from the raw tables and source module.\n";
    } else {
        foreach ($outputs as $path => $contents) {
            $changed = AtomicFileWriter::writeIfChanged($path, $contents);
            printf("[%s] %s (%.1f KB)\n", $changed ? 'BUILT' : 'CURRENT', $path, strlen($contents) / 1024);
        }
    }

    echo "Growth chart data pipeline completed successfully.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Growth chart data pipeline failed: ' . $error->getMessage() . "\n");
    exit(1);
}
