<?php

declare(strict_types=1);

namespace Phil\DevTools;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Reads a file this package needs, failing loudly when it cannot, and removes the directories it generates.
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

    /**
     * Removes a directory and everything in it.
     */
    public static function removeDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
