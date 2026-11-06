<?php

declare(strict_types=1);

use ZimRx\GrowthChart\GrowthReferenceBuilder;
use ZimRx\GrowthChart\GrowthDataCompiler;
use ZimRx\GrowthChart\RawReferenceSourceManager;

require_once dirname(__DIR__) . '/src/RawReferenceSourceManager.php';
require_once dirname(__DIR__) . '/src/GrowthReferenceBuilder.php';
require_once dirname(__DIR__) . '/src/GrowthDataCompiler.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

function assertNear(float $expected, float $actual, float $tolerance, string $message): void
{
    if (abs($expected - $actual) > $tolerance) {
        throw new RuntimeException($message);
    }
}

try {
    $toolDirectory = dirname(__DIR__);
    $rawDirectory = $toolDirectory . '/raw';
    $manifest = RawReferenceSourceManager::loadManifest($rawDirectory . '/source-manifest.json');
    assertSameValue(3, $manifest['schemaVersion'], 'Manifest rights metadata must use schema version 3.');
    assertSameValue(
        'documented_public_domain',
        $manifest['rightsProfiles']['cdc_2000']['status'],
        'CDC rights status must be recorded.'
    );
    assertSameValue(
        'licensed_for_product_incorporation',
        $manifest['rightsProfiles']['who_2006']['status'],
        'WHO 2006 product permission must be recorded.'
    );
    assertSameValue(
        'licensed_for_product_incorporation',
        $manifest['rightsProfiles']['who_2007']['status'],
        'WHO 2007 product permission must be recorded.'
    );
    assertSameValue(
        true,
        str_contains($manifest['rightsProfiles']['who_2006']['license'], 'non-exclusive permission'),
        'WHO 2006 product permission must be recorded.'
    );
    assertSameValue(
        true,
        str_contains($manifest['rightsProfiles']['who_2007']['license'], 'non-exclusive permission'),
        'WHO 2007 product permission must be recorded.'
    );
    assertSameValue(
        true,
        str_contains($manifest['rightsProfiles']['who_2006']['terms'], 'stand-alone basis'),
        'WHO 2006 standalone table distribution must remain prohibited.'
    );
    assertSameValue(
        true,
        str_contains($manifest['rightsProfiles']['who_2007']['terms'], 'stand-alone basis'),
        'WHO 2007 standalone table distribution must remain prohibited.'
    );
    RawReferenceSourceManager::verifyFiles($rawDirectory, $manifest['sources']);
    $datasets = GrowthReferenceBuilder::build($rawDirectory, $manifest);

    foreach (['wfa', 'lhfa', 'hcfa', 'bmifa'] as $metric) {
        foreach (['_boys', '_girls'] as $suffix) {
            assertSameValue(1827, count($datasets['who'][$metric . $suffix]), 'WHO series must retain every daily source record.');
        }
    }
    assertSameValue(218, count($datasets['cdc']['wfa_boys']), 'CDC weight series must retain all 218 source ages.');
    assertSameValue(219, count($datasets['cdc']['bmifa_boys']), 'CDC BMI series must retain all 219 source ages.');
    assertSameValue(24, $datasets['cdc']['wfa_boys'][0]['m'], 'CDC series must start at 24 months.');
    assertSameValue(24.5, $datasets['cdc']['wfa_boys'][1]['m'], 'CDC half-month source point was lost.');
    assertSameValue(240, $datasets['cdc']['wfa_boys'][217]['m'], 'CDC weight series must retain its final source age.');
    assertSameValue(240.5, $datasets['cdc']['bmifa_boys'][218]['m'], 'CDC BMI series must retain its final source age.');

    assertNear(-0.20615245, $datasets['cdc']['wfa_boys'][0]['L'], 0.00000001, 'CDC LMS anchor does not match the raw file.');
    assertNear(12.6707633, $datasets['cdc']['wfa_boys'][0]['M'], 0.0000001, 'CDC median anchor does not match the raw file.');
    assertNear(10.38209025, $datasets['cdc']['wfa_boys'][0]['p3'], 0.00000001, 'CDC percentile anchor does not match the raw file.');
    assertNear(0.3487, $datasets['who']['wfa_boys'][0]['L'], 0.00000001, 'WHO LMS anchor does not match the raw file.');
    assertNear(3.3464, $datasets['who']['wfa_boys'][0]['M'], 0.00000001, 'WHO median anchor does not match the raw file.');

    $whoDayTen = null;
    $handle = fopen($rawDirectory . '/who_weianthro_2006.txt', 'rb');
    if ($handle === false) {
        throw new RuntimeException('Unable to open WHO raw table for daily-resolution check.');
    }
    try {
        fgetcsv($handle, null, "\t", '"', '');
        while (($cells = fgetcsv($handle, null, "\t", '"', '')) !== false) {
            if (isset($cells[0], $cells[1]) && $cells[0] === '1' && $cells[1] === '10') {
                $whoDayTen = ['L' => (float)$cells[2], 'M' => (float)$cells[3], 'S' => (float)$cells[4]];
                break;
            }
        }
    } finally {
        fclose($handle);
    }
    if (!is_array($whoDayTen)) {
        throw new RuntimeException('WHO day-10 reference row is missing.');
    }
    assertNear(10 / $manifest['whoDaysPerMonth'], $datasets['who']['wfa_boys'][10]['m'], 0.000001, 'WHO day index must map to decimal months.');
    foreach (['L', 'M', 'S'] as $parameter) {
        assertNear($whoDayTen[$parameter], $datasets['who']['wfa_boys'][10][$parameter], 0.000000001, "WHO day-10 {$parameter} must match the raw source row.");
    }

    assertSameValue(60, $datasets['who']['wfa_boys'][1826]['m'], 'WHO series must retain the 60-month endpoint.');
    $runtimeData = GrowthDataCompiler::compile($datasets);
    $dataPath = $toolDirectory . '/visualizer/data/growth_chart_data.json';
    assertSameValue(hash('sha256', $runtimeData), hash_file('sha256', $dataPath), 'Generated JSON differs from the raw tables.');

    $enginePath = $toolDirectory . '/src/js/growth_chart_engine.js';
    $engineCode = file_get_contents($enginePath);
    if (!is_string($engineCode)) {
        throw new RuntimeException('Unable to read the growth calculation module.');
    }
    if (preg_match('/export\s+function\s+(?!createGrowthData\b)/', $engineCode) === 1) {
        throw new RuntimeException('The visualizer engine must not expose clinical calculation helpers.');
    }
    $engineOutputPath = $toolDirectory . '/visualizer/assets/js/growth_chart_engine.js';
    assertSameValue(hash('sha256', $engineCode), hash_file('sha256', $engineOutputPath), 'Generated engine copy differs from its source.');

    echo "Growth reference pipeline checks passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Growth reference pipeline check failed: ' . $error->getMessage() . "\n");
    exit(1);
}
