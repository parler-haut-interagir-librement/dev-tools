<?php

declare(strict_types=1);

namespace Phil\DevTools\Process;

/**
 * The programs found on the `PATH`, for the tools that are not PHP packages — splitsh-lite, Vale, cspell,
 * markdownlint — and are used when they are installed.
 */
final readonly class Executables
{
    /**
     * @param list<string> $directories
     */
    public function __construct(private array $directories)
    {
    }

    public static function fromEnvironment(): self
    {
        $path = getenv('PATH');

        return new self(explode(\PATH_SEPARATOR, \is_string($path) ? $path : ''));
    }

    /**
     * The absolute path of the program, or null when no directory of the `PATH` holds it.
     */
    public function find(string $name): ?string
    {
        foreach ($this->directories as $directory) {
            $candidate = rtrim($directory, '/').'/'.$name;

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
