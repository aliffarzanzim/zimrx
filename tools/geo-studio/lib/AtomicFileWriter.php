<?php
declare(strict_types=1);

namespace GeoStudio;

use RuntimeException;

final class AtomicFileWriter
{
    public static function write(string $path, iterable $chunks): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create output directory.');
        }
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $stream = fopen($temporary, 'xb');
        if ($stream === false) throw new RuntimeException('Unable to create temporary output.');
        try {
            foreach ($chunks as $chunk) {
                $length = strlen($chunk);
                for ($offset = 0; $offset < $length; $offset += $written) {
                    $written = fwrite($stream, substr($chunk, $offset));
                    if ($written === false || $written === 0) throw new RuntimeException('Unable to write output.');
                }
            }
            if (!fflush($stream)) throw new RuntimeException('Unable to flush output.');
            fclose($stream);
            $stream = null;
            if (!rename($temporary, $path)) throw new RuntimeException('Unable to replace output.');
        } finally {
            if (is_resource($stream)) fclose($stream);
            if (is_file($temporary)) unlink($temporary);
        }
    }
}
