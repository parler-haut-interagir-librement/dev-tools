<?php

declare(strict_types=1);

namespace Phil\DevTools;

/**
 * Where `phil/dev-tools` is installed, and the paths a project uses to reach it.
 */
final class DevTools
{
    public const string NAME = 'phil-dev';

    public const string VERSION = '0.1.x-dev';

    /**
     * The directory of this package: `vendor/phil/dev-tools` in a project, the repository itself while it is developed.
     */
    public static function directory(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * The root the tools work on: `PHIL_DEV_ROOT`, or the directory they run from.
     */
    public static function root(): string
    {
        $variable = getenv('PHIL_DEV_ROOT');

        if (\is_string($variable) && '' !== $variable) {
            return $variable;
        }

        // The directory they run from, which every path is resolved against.
        return '.';
    }

    public static function resource(string $path): string
    {
        return self::directory().'/resources/'.$path;
    }

    /**
     * The path of `$target` relative to `$from`, both absolute directories or files: `vendor/phil/dev-tools/config`
     * from a project's root, `config` from this repository's.
     */
    public static function relative(string $from, string $target): string
    {
        $from = explode('/', trim(self::normalise($from), '/'));
        $to = explode('/', trim(self::normalise($target), '/'));

        while ([] !== $from && [] !== $to && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        $relative = rtrim(str_repeat('../', \count($from)).implode('/', $to), '/');

        return '' === $relative ? '.' : $relative;
    }

    /**
     * The path a project's Composer scripts call `phil-dev` by: the Composer proxy when this package is installed in
     * the project, else the path of its own binary.
     */
    public static function binary(string $root, ?string $directory = null): string
    {
        $directory ??= self::directory();

        if (str_starts_with(self::normalise($directory), self::normalise($root).'/vendor/') || is_file($root.'/vendor/bin/phil-dev')) {
            return 'vendor/bin/phil-dev';
        }

        return self::relative($root, $directory.'/bin/phil-dev');
    }

    private static function normalise(string $path): string
    {
        $real = realpath($path);

        return rtrim(false === $real ? $path : $real, '/');
    }
}
