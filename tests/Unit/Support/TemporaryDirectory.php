<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Support;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\After;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A directory of its own for each test, removed after it.
 */
trait TemporaryDirectory
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/phil-dev-test-'.random_int(1, \PHP_INT_MAX);
        mkdir($directory, 0o777, true);
        $real = realpath($directory);
        $this->temporaryDirectories[] = false === $real ? $directory : $real;

        return false === $real ? $directory : $real;
    }

    /**
     * A directory made elsewhere, to remove after the test.
     */
    protected function adopt(string $directory): void
    {
        $this->temporaryDirectories[] = $directory;
    }

    /**
     * A copy of a fixture under `tests/Fixtures`.
     */
    protected function fixture(string $name): string
    {
        $source = \dirname(__DIR__, 2).'/Fixtures/'.$name;
        $target = $this->temporaryDirectory();
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $path = $target.'/'.substr($item->getPathname(), \strlen($source) + 1);
            $item->isDir() ? mkdir($path, 0o777, true) : copy($item->getPathname(), $path);
        }

        return $target;
    }

    /**
     * Writes a file, with its directories.
     */
    protected function put(string $path, string $content): void
    {
        if (!is_dir(\dirname($path))) {
            mkdir(\dirname($path), 0o777, true);
        }

        file_put_contents($path, $content);
    }

    #[After]
    protected function removeTemporaryDirectories(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

            /** @var SplFileInfo $item */
            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }

            rmdir($directory);
        }

        $this->temporaryDirectories = [];
    }
}
