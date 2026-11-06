<?php

declare(strict_types=1);

namespace ZimRx\GrowthChart;

use RuntimeException;

final class AtomicFileWriter
{
    public static function writeIfChanged(string $path, string $contents): bool
    {
        $existing = is_file($path) ? file_get_contents($path) : false;
        if (is_string($existing) && hash_equals(hash('sha256', $existing), hash('sha256', $contents))) {
            return false;
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create output directory: {$directory}");
        }

        $temporaryPath = tempnam($directory, '.growth-chart-');
        if ($temporaryPath === false) {
            throw new RuntimeException("Unable to create a temporary file in: {$directory}");
        }

        try {
            $written = file_put_contents($temporaryPath, $contents, LOCK_EX);
            if ($written !== strlen($contents)) {
                throw new RuntimeException("Unable to write complete output file: {$path}");
            }
            if (!rename($temporaryPath, $path)) {
                throw new RuntimeException("Unable to replace output file: {$path}");
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        return true;
    }
}
