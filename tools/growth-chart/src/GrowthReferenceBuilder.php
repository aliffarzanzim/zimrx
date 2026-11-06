<?php

declare(strict_types=1);

namespace ZimRx\GrowthChart;

use RuntimeException;

final class GrowthReferenceBuilder
{
    private const PERCENTILE_Z = [
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
        'p97' => 1.88079,
    ];

    private const SD_Z = [
        'sd_minus_3' => -3.0,
        'sd_minus_2' => -2.0,
        'sd_minus_1' => -1.0,
        'sd_0' => 0.0,
        'sd_plus_1' => 1.0,
        'sd_plus_2' => 2.0,
        'sd_plus_3' => 3.0,
    ];

    private const CDC_HEADERS = [
        'wfa' => ['Sex', 'Agemos', 'L', 'M', 'S', 'P3', 'P5', 'P10', 'P25', 'P50', 'P75', 'P90', 'P95', 'P97'],
        'hfa' => ['Sex', 'Agemos', 'L', 'M', 'S', 'P3', 'P5', 'P10', 'P25', 'P50', 'P75', 'P90', 'P95', 'P97'],
        'bmifa' => ['Sex', 'Agemos', 'L', 'M', 'S', 'P3', 'P5', 'P10', 'P25', 'P50', 'P75', 'P85', 'P90', 'P95', 'P97'],
    ];

    private const WHO_2007_HEADER = ['sex', 'age', 'l', 'm', 's'];

    private const CDC_EXTENDED_HEADER = [
        'sex', 'agemos', 'L', 'M', 'S', 'sigma', 'P3', 'P5', 'P10', 'P25', 'P50', 'P75',
        'P85', 'P90', 'P95', 'P98', 'P99', 'P99_9', 'P99_99', 'pct120ofP95', 'Z2neg',
        'Z1_5neg', 'Z1neg', 'Z0_5neg', 'Z0', 'Z0_5', 'Z1', 'Z1_5', 'Z2', 'Z2_5', 'Z3',
        'Z3_5', 'Z4', 'Z4_5', 'Z5',
    ];

    public static function build(string $rawDirectory, array $manifest): array
    {
        $datasets = ['who' => [], 'who2007' => [], 'cdc' => [], 'cdcExtended' => []];
        foreach ($manifest['sources'] as $source) {
            $path = $rawDirectory . DIRECTORY_SEPARATOR . $source['file'];
            if ($source['reference'] === 'cdc_2000') {
                self::buildCdcFile($path, $source, $datasets['cdc']);
                continue;
            }
            if ($source['reference'] === 'cdc_2022_extended') {
                self::buildCdcExtendedFile($path, $source, $datasets['cdcExtended']);
                continue;
            }
            if ($source['reference'] === 'who_2007') {
                self::buildWho2007File($path, $source, $datasets['who2007']);
                continue;
            }
            self::buildWhoFile($path, $source, (float)$manifest['whoDaysPerMonth'], $datasets['who']);
        }

        self::validateDatasetShape($datasets['who'], 'who');
        self::validateDatasetShape($datasets['who2007'], 'who2007');
        self::validateDatasetShape($datasets['cdc'], 'cdc');
        self::validateDatasetShape($datasets['cdcExtended'], 'cdcExtended');
        return $datasets;
    }

    private static function buildCdcExtendedFile(string $path, array $source, array &$dataset): void
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open raw CDC table: {$path}");
        }

        try {
            $header = fgetcsv($handle, null, ',', '"', '');
            if (is_array($header)) {
                $header = array_map('trim', $header);
            }
            if ($header !== self::CDC_EXTENDED_HEADER) {
                throw new RuntimeException("Unexpected CDC extended BMI CSV header in {$source['file']}.");
            }

            $countBySex = [1 => 0, 2 => 0];
            $firstAgeBySex = [1 => null, 2 => null];
            $lastAgeBySex = [1 => -INF, 2 => -INF];
            $line = 1;
            while (($cells = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;
                if ($cells === [null]) {
                    continue;
                }
                if (count($cells) !== count($header)) {
                    throw new RuntimeException("Malformed CDC extended BMI row in {$source['file']} at line {$line}.");
                }
                $row = array_combine($header, $cells);
                if (!is_array($row)) {
                    throw new RuntimeException("Unable to map CDC extended BMI columns in {$source['file']} at line {$line}.");
                }

                $sexValue = self::number($row['sex'], $source['file'], $line, 'sex');
                $sex = (int)$sexValue;
                if (!in_array($sex, [1, 2], true) || (float)$sex !== $sexValue) {
                    throw new RuntimeException("Invalid CDC extended BMI sex code in {$source['file']} at line {$line}.");
                }
                $age = self::number($row['agemos'], $source['file'], $line, 'agemos');
                if ($age <= $lastAgeBySex[$sex] || $age < 24 || $age > 240.5) {
                    throw new RuntimeException("CDC extended BMI ages are duplicated, unordered, or out of range in {$source['file']}.");
                }
                $firstAgeBySex[$sex] ??= $age;
                $lastAgeBySex[$sex] = $age;
                $countBySex[$sex]++;

                $lms = [
                    'L' => self::number($row['L'], $source['file'], $line, 'L'),
                    'M' => self::number($row['M'], $source['file'], $line, 'M'),
                    'S' => self::number($row['S'], $source['file'], $line, 'S'),
                ];
                self::validateLms($lms, $source['file'], $line);
                $sigma = self::number($row['sigma'], $source['file'], $line, 'sigma');
                $pct120ofP95 = self::number($row['pct120ofP95'], $source['file'], $line, 'pct120ofP95');
                if ($sigma <= 0.0 || $pct120ofP95 <= 0.0) {
                    throw new RuntimeException("Invalid CDC extended BMI calculation parameters in {$source['file']} at line {$line}.");
                }
                $percentiles = [];
                foreach ([
                    'P3' => 'p3',
                    'P50' => 'p50',
                    'P95' => 'p95',
                    'P98' => 'p98',
                    'P99' => 'p99',
                    'P99_9' => 'p99_9',
                    'P99_99' => 'p99_99',
                ] as $column => $key) {
                    $value = self::number($row[$column], $source['file'], $line, $column);
                    if ($value <= 0.0) {
                        throw new RuntimeException("Non-positive CDC extended BMI percentile in {$source['file']} at line {$line}.");
                    }
                    $percentiles[$key] = $value;
                }
                $orderedPercentiles = array_values($percentiles);
                for ($index = 1, $length = count($orderedPercentiles); $index < $length; $index++) {
                    if ($orderedPercentiles[$index] <= $orderedPercentiles[$index - 1]) {
                        throw new RuntimeException("CDC extended BMI percentiles are not increasing in {$source['file']} at line {$line}.");
                    }
                }
                if (abs($percentiles['p50'] - $lms['M']) > 0.001) {
                    throw new RuntimeException("CDC extended BMI median does not match its M value in {$source['file']} at line {$line}.");
                }

                $suffix = $sex === 1 ? '_boys' : '_girls';
                $seriesKey = $source['metric'] . $suffix;
                $dataset[$seriesKey][] = [
                    'm' => self::normalizeAge($age),
                    ...$lms,
                    'sigma' => $sigma,
                    'pct120ofP95' => $pct120ofP95,
                    ...$percentiles,
                ];
            }
        } finally {
            fclose($handle);
        }

        foreach ([1, 2] as $sex) {
            if ($countBySex[$sex] !== 219 || $firstAgeBySex[$sex] !== 24.0 || $lastAgeBySex[$sex] !== 240.5) {
                throw new RuntimeException("Unexpected CDC extended BMI row count or age range in {$source['file']} for sex {$sex}.");
            }
        }
    }

    private static function buildCdcFile(string $path, array $source, array &$dataset): void
    {
        $expectedHeader = self::CDC_HEADERS[$source['metric']] ?? null;
        if (!is_array($expectedHeader)) {
            throw new RuntimeException("Unsupported CDC metric in source manifest: {$source['metric']}");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open raw CDC table: {$path}");
        }

        try {
            $header = fgetcsv($handle, null, ',', '"', '');
            if (is_array($header)) {
                $header = array_map('trim', $header);
            }
            if ($header !== $expectedHeader) {
                throw new RuntimeException("Unexpected CDC CSV header in {$source['file']}.");
            }

            $countBySex = [1 => 0, 2 => 0];
            $lastAgeBySex = [1 => -INF, 2 => -INF];
            $line = 1;
            while (($cells = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;
                if ($cells === [null]) {
                    continue;
                }
                if ($cells === $expectedHeader) {
                    if ($source['metric'] !== 'bmifa'
                        || $countBySex[1] !== 219
                        || $countBySex[2] !== 0
                    ) {
                        throw new RuntimeException("Unexpected repeated CDC CSV header in {$source['file']} at line {$line}.");
                    }
                    continue;
                }
                if (count($cells) !== count($header)) {
                    throw new RuntimeException("Malformed CDC row in {$source['file']} at line {$line}.");
                }
                $row = array_combine($header, $cells);
                if (!is_array($row)) {
                    throw new RuntimeException("Unable to map CDC columns in {$source['file']} at line {$line}.");
                }

                $sexValue = self::number($row['Sex'], $source['file'], $line, 'Sex');
                $sex = (int)$sexValue;
                if (!in_array($sex, [1, 2], true) || (float)$sex !== $sexValue) {
                    throw new RuntimeException("Invalid CDC sex code in {$source['file']} at line {$line}.");
                }
                $age = self::number($row['Agemos'], $source['file'], $line, 'Agemos');
                if ($age <= $lastAgeBySex[$sex]) {
                    throw new RuntimeException("CDC ages are duplicated or out of order in {$source['file']}.");
                }
                $lastAgeBySex[$sex] = $age;
                $countBySex[$sex]++;

                $lms = [
                    'L' => self::number($row['L'], $source['file'], $line, 'L'),
                    'M' => self::number($row['M'], $source['file'], $line, 'M'),
                    'S' => self::number($row['S'], $source['file'], $line, 'S'),
                ];
                self::validateLms($lms, $source['file'], $line);
                $sourcePercentiles = [];
                foreach (self::sourcePercentileColumns($header) as $key => $column) {
                    $value = self::number($row[$column], $source['file'], $line, $column);
                    if ($value <= 0.0) {
                        throw new RuntimeException("Non-positive CDC percentile value in {$source['file']} at line {$line}.");
                    }
                    $expected = self::lmsToValue($lms['L'], $lms['M'], $lms['S'], self::PERCENTILE_Z[$key]);
                    if ($expected === null || abs($value - $expected) > 0.05) {
                        throw new RuntimeException("CDC LMS and {$column} disagree in {$source['file']} at line {$line}.");
                    }
                    $sourcePercentiles[$key] = $value;
                }

                $suffix = $sex === 1 ? '_boys' : '_girls';
                $seriesKey = $source['metric'] . $suffix;
                $dataset[$seriesKey][] = self::makeRecord($age, $lms, $sourcePercentiles);
            }
        } finally {
            fclose($handle);
        }

        $expectedCount = $source['metric'] === 'bmifa' ? 219 : 218;
        $expectedLastAge = $source['metric'] === 'bmifa' ? 240.5 : 240.0;
        foreach ([1, 2] as $sex) {
            if ($countBySex[$sex] !== $expectedCount || $lastAgeBySex[$sex] !== $expectedLastAge) {
                throw new RuntimeException("Unexpected CDC row count or age range in {$source['file']} for sex {$sex}.");
            }
        }
    }

    private static function buildWhoFile(string $path, array $source, float $daysPerMonth, array &$dataset): void
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open raw WHO table: {$path}");
        }

        try {
            $header = fgetcsv($handle, null, "\t", '"', '');
            if (is_array($header)) {
                $header = array_map('trim', $header);
            }
            $expectedHeader = in_array($source['metric'], ['lhfa', 'bmifa'], true)
                ? ['sex', 'age', 'l', 'm', 's', 'loh']
                : ['sex', 'age', 'l', 'm', 's'];
            if ($header !== $expectedHeader) {
                throw new RuntimeException("Unexpected WHO TSV header in {$source['file']}.");
            }

            $rowsBySex = [1 => [], 2 => []];
            $nextAgeBySex = [1 => 0, 2 => 0];
            $line = 1;
            while (($cells = fgetcsv($handle, null, "\t", '"', '')) !== false) {
                $line++;
                if ($cells === [null]) {
                    continue;
                }
                if (count($cells) !== count($header)) {
                    throw new RuntimeException("Malformed WHO row in {$source['file']} at line {$line}.");
                }
                $row = array_combine($header, $cells);
                if (!is_array($row)) {
                    throw new RuntimeException("Unable to map WHO columns in {$source['file']} at line {$line}.");
                }

                $sexValue = self::number($row['sex'], $source['file'], $line, 'sex');
                $sex = (int)$sexValue;
                if (!in_array($sex, [1, 2], true) || (float)$sex !== $sexValue) {
                    throw new RuntimeException("Invalid WHO sex code in {$source['file']} at line {$line}.");
                }
                $ageValue = self::number($row['age'], $source['file'], $line, 'age');
                $age = (int)$ageValue;
                if ((float)$age !== $ageValue || $age !== $nextAgeBySex[$sex] || $age > 1826) {
                    throw new RuntimeException("WHO daily ages are missing, duplicated, or out of order in {$source['file']} at line {$line}.");
                }
                $nextAgeBySex[$sex]++;
                $lms = [
                    'L' => self::number($row['l'], $source['file'], $line, 'l'),
                    'M' => self::number($row['m'], $source['file'], $line, 'm'),
                    'S' => self::number($row['s'], $source['file'], $line, 's'),
                ];
                self::validateLms($lms, $source['file'], $line);
                $rowsBySex[$sex][$age] = $lms;
            }
        } finally {
            fclose($handle);
        }

        foreach ([1, 2] as $sex) {
            if ($nextAgeBySex[$sex] !== 1827) {
                throw new RuntimeException("Unexpected WHO daily row count in {$source['file']} for sex {$sex}.");
            }
            $suffix = $sex === 1 ? '_boys' : '_girls';
            $seriesKey = $source['metric'] . $suffix;
            $dataset[$seriesKey] = [];
            for ($day = 0; $day <= 1826; $day++) {
                $month = $day === 1826 ? 60.0 : $day / $daysPerMonth;
                $dataset[$seriesKey][] = self::makeRecord($month, $rowsBySex[$sex][$day], []);
            }
        }
    }

    private static function buildWho2007File(string $path, array $source, array &$dataset): void
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open WHO 2007 table: {$path}");
        }

        try {
            $header = fgetcsv($handle, null, "\t", '"', '');
            if (is_array($header)) {
                $header = array_map('trim', $header);
            }
            if ($header !== self::WHO_2007_HEADER) {
                throw new RuntimeException("Unexpected WHO 2007 TSV header in {$source['file']}.");
            }

            $firstAge = 60;
            $lastAge = $source['metric'] === 'wfa' ? 121 : 229;
            $rowsBySex = [1 => [], 2 => []];
            $nextAgeBySex = [1 => $firstAge, 2 => $firstAge];
            $line = 1;
            while (($cells = fgetcsv($handle, null, "\t", '"', '')) !== false) {
                $line++;
                if ($cells === [null]) {
                    continue;
                }
                if (count($cells) !== count($header)) {
                    throw new RuntimeException("Malformed WHO 2007 row in {$source['file']} at line {$line}.");
                }
                $row = array_combine($header, $cells);
                if (!is_array($row)) {
                    throw new RuntimeException("Unable to map WHO 2007 columns in {$source['file']} at line {$line}.");
                }

                $sexValue = self::number($row['sex'], $source['file'], $line, 'sex');
                $sex = (int)$sexValue;
                if (!in_array($sex, [1, 2], true) || (float)$sex !== $sexValue) {
                    throw new RuntimeException("Invalid WHO 2007 sex code in {$source['file']} at line {$line}.");
                }
                $ageValue = self::number($row['age'], $source['file'], $line, 'age');
                $age = (int)$ageValue;
                if ((float)$age !== $ageValue || $age !== $nextAgeBySex[$sex] || $age > $lastAge) {
                    throw new RuntimeException("WHO 2007 ages are missing, duplicated, or out of order in {$source['file']} at line {$line}.");
                }
                $nextAgeBySex[$sex]++;

                $lms = [
                    'L' => self::number($row['l'], $source['file'], $line, 'l'),
                    'M' => self::number($row['m'], $source['file'], $line, 'm'),
                    'S' => self::number($row['s'], $source['file'], $line, 's'),
                ];
                self::validateLms($lms, $source['file'], $line);
                $rowsBySex[$sex][$age] = $lms;
            }
        } finally {
            fclose($handle);
        }

        $expectedCount = $lastAge - $firstAge + 1;
        foreach ([1, 2] as $sex) {
            if ($nextAgeBySex[$sex] !== $lastAge + 1 || count($rowsBySex[$sex]) !== $expectedCount) {
                throw new RuntimeException("Unexpected WHO 2007 row count or age range in {$source['file']} for sex {$sex}.");
            }
            $suffix = $sex === 1 ? '_boys' : '_girls';
            $seriesKey = $source['metric'] . $suffix;
            $dataset[$seriesKey] = [];
            for ($age = $firstAge; $age <= $lastAge; $age++) {
                $dataset[$seriesKey][] = self::makeRecord($age, $rowsBySex[$sex][$age], []);
            }
        }
    }

    private static function makeRecord(int|float $age, array $lms, array $sourcePercentiles): array
    {
        $record = [
            'm' => self::normalizeAge($age),
            'L' => $lms['L'],
            'M' => $lms['M'],
            'S' => $lms['S'],
        ];

        foreach (self::PERCENTILE_Z as $key => $z) {
            if (array_key_exists($key, $sourcePercentiles)) {
                $record[$key] = $sourcePercentiles[$key];
                continue;
            }
            $value = self::lmsToValue($lms['L'], $lms['M'], $lms['S'], $z);
            $record[$key] = $value === null ? null : round($value, 2);
        }
        foreach (self::SD_Z as $key => $z) {
            $value = self::lmsToValue($lms['L'], $lms['M'], $lms['S'], $z);
            $record[$key] = $value === null ? null : round($value, 2);
        }

        return $record;
    }

    private static function sourcePercentileColumns(array $header): array
    {
        $columns = [];
        foreach (['P3' => 'p3', 'P5' => 'p5', 'P10' => 'p10', 'P25' => 'p25', 'P50' => 'p50', 'P75' => 'p75', 'P85' => 'p85', 'P90' => 'p90', 'P95' => 'p95', 'P97' => 'p97'] as $column => $key) {
            if (in_array($column, $header, true)) {
                $columns[$key] = $column;
            }
        }
        return $columns;
    }

    private static function validateDatasetShape(array $dataset, string $family): void
    {
        $expectedMetrics = match ($family) {
            'who' => ['wfa', 'lhfa', 'hcfa', 'bmifa'],
            'who2007' => ['hfa', 'wfa', 'bmifa'],
            'cdcExtended' => ['bmifa'],
            default => ['wfa', 'hfa', 'bmifa'],
        };
        $expectedRows = $family === 'who' ? 1827 : null;
        $expectedKeys = [];
        foreach ($expectedMetrics as $metric) {
            foreach (['_boys', '_girls'] as $suffix) {
                $key = $metric . $suffix;
                $expectedKeys[] = $key;
                $metricRows = match ($family) {
                    'who' => $expectedRows,
                    'who2007' => $metric === 'wfa' ? 62 : 170,
                    'cdcExtended' => 219,
                    default => $metric === 'bmifa' ? 219 : 218,
                };
                if (!isset($dataset[$key]) || count($dataset[$key]) !== $metricRows) {
                    throw new RuntimeException("Invalid {$family} series length for {$key}.");
                }
                $lastAge = -INF;
                foreach ($dataset[$key] as $row) {
                    if (!is_array($row)
                        || !isset($row['m'], $row['L'], $row['M'], $row['S'])
                        || $row['m'] <= $lastAge
                    ) {
                        throw new RuntimeException("Invalid or unordered age rows in {$key}.");
                    }
                    if ($family === 'cdcExtended'
                        && (!isset($row['sigma'], $row['pct120ofP95'])
                            || $row['sigma'] <= 0.0
                            || $row['pct120ofP95'] <= 0.0)
                    ) {
                        throw new RuntimeException("Missing or invalid CDC extended BMI parameters in {$key}.");
                    }
                    $lastAge = $row['m'];
                }
            }
        }
        if (array_keys($dataset) !== $expectedKeys) {
            throw new RuntimeException("Unexpected series keys in {$family} reference data.");
        }
    }

    private static function number(string $value, string $file, int $line, string $column): float
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            throw new RuntimeException("Invalid numeric value for {$column} in {$file} at line {$line}.");
        }
        $number = (float)$value;
        if (!is_finite($number)) {
            throw new RuntimeException("Non-finite numeric value for {$column} in {$file} at line {$line}.");
        }
        return $number;
    }

    private static function validateLms(array $lms, string $file, int $line): void
    {
        if ($lms['M'] <= 0.0 || $lms['S'] <= 0.0) {
            throw new RuntimeException("Invalid LMS values in {$file} at line {$line}.");
        }
    }

    private static function lmsToValue(float $l, float $m, float $s, float $z): ?float
    {
        if (abs($l) < 0.0001) {
            $value = $m * exp($s * $z);
        } else {
            $base = 1.0 + $l * $s * $z;
            if ($base <= 0.0) {
                return null;
            }
            $value = $m * pow($base, 1.0 / $l);
        }
        if (!is_finite($value) || $value <= 0.0) {
            return null;
        }
        return $value;
    }

    private static function normalizeAge(float|int $age): float|int
    {
        $rounded = round((float)$age, 6);
        return abs($rounded - round($rounded)) < 0.000001 ? (int)round($rounded) : $rounded;
    }
}
