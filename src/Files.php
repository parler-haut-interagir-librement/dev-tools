<?php

declare(strict_types=1);

namespace Phil\DevTools;

use RuntimeException;

/**
 * Reads a file this package needs, failing loudly when it cannot.
 */
final class Files
{
    public static function read(string $path): string
    {
        $content = is_file($path) ? file_get_contents($path) : false;

        if (false === $content) {
            throw new RuntimeException(\sprintf('Cannot read %s.', $path));
        }

        return $content;
    }
}
