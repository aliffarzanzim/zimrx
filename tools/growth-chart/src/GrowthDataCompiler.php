<?php

declare(strict_types=1);

namespace ZimRx\GrowthChart;

use RuntimeException;

final class GrowthDataCompiler
{
    public static function compile(array $datasets): string
    {
        foreach (['who', 'who2007', 'cdc', 'cdcExtended'] as $family) {
            if (!isset($datasets[$family]) || !is_array($datasets[$family])) {
                throw new RuntimeException("Missing {$family} growth dataset.");
            }
        }

        return json_encode(
            $datasets,
            JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        ) . "\n";
    }
}
