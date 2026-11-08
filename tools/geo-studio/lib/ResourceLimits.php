<?php
declare(strict_types=1);

namespace GeoStudio;

use InvalidArgumentException;

final class ResourceLimits
{
    public const DEFAULT_PACK_BYTES = 4194304;
    public const DEFAULT_PLACES = 20000;

    public static function packBytes(): int
    {
        return self::positiveInteger('GEOSTUDIO_MAX_PACK_BYTES', self::DEFAULT_PACK_BYTES);
    }

    public static function places(): int
    {
        return self::positiveInteger('GEOSTUDIO_MAX_PLACES', self::DEFAULT_PLACES);
    }

    private static function positiveInteger(string $name, int $default): int
    {
        $raw = getenv($name);
        if ($raw === false || trim($raw) === '') {
            return $default;
        }

        $value = filter_var(trim($raw), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => intdiv(PHP_INT_MAX, 8)],
        ]);
        if ($value === false) {
            throw new InvalidArgumentException("{$name} must be a positive integer.");
        }
        return $value;
    }
}
