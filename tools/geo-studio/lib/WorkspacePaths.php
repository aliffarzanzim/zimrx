<?php
declare(strict_types=1);

namespace GeoStudio;

use RuntimeException;

final class WorkspacePaths
{
    public static function seedDirectory(): string
    {
        $root = dirname(__DIR__, 3);
        $directory = $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR
            . 'systemdata' . DIRECTORY_SEPARATOR . 'seeds';
        if (!is_dir($directory) || !is_file($directory . DIRECTORY_SEPARATOR . 'zimrx_static.sql')) {
            throw new RuntimeException('Geo Studio must run inside a ZimRx source checkout.');
        }
        return $directory;
    }
}
