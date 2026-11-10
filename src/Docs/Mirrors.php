<?php

declare(strict_types=1);

namespace Phil\DevTools\Docs;

use Phil\DevTools\Workspace\Package;

/**
 * Where the French documentation of a package lives: `README.fr.md` beside `README.md`, and `doc/fr/<name>.md` for
 * each `doc/<name>.md`, with the same names and the same tree.
 */
final class Mirrors
{
    public const string SOURCE_README = 'README.md';

    public const string README = 'README.fr.md';

    private const string SOURCE_DIRECTORY = 'doc/';

    private const string DIRECTORY = 'doc/fr/';

    /**
     * The glossary the translations follow: written in French, it mirrors no English document.
     */
    private const string GLOSSARY = 'doc/fr-glossary.md';

    public static function isMirror(string $file): bool
    {
        return self::README === $file || (str_starts_with($file, self::DIRECTORY) && str_ends_with($file, '.md'));
    }

    /**
     * A mirror, or a document written in French from the start.
     */
    public static function isFrench(string $file): bool
    {
        return self::GLOSSARY === $file || self::isMirror($file);
    }

    public static function mirrorOf(string $source): string
    {
        return self::SOURCE_README === $source ? self::README : self::DIRECTORY.substr($source, \strlen(self::SOURCE_DIRECTORY));
    }

    public static function sourceOf(string $mirror): string
    {
        return self::README === $mirror ? self::SOURCE_README : self::SOURCE_DIRECTORY.substr($mirror, \strlen(self::DIRECTORY));
    }

    /**
     * The English documents that have a mirror, relative to the package: the README, then those of `doc/`.
     *
     * @return list<string>
     */
    public static function sources(Package $package): array
    {
        $documents = array_filter(self::documents($package), static fn (string $file): bool => !self::isFrench($file));

        return [...(is_file($package->path(self::SOURCE_README)) ? [self::SOURCE_README] : []), ...$documents];
    }

    /**
     * The mirrors that exist, relative to the package: `README.fr.md`, then those of `doc/fr/`.
     *
     * @return list<string>
     */
    public static function mirrors(Package $package): array
    {
        return [...(is_file($package->path(self::README)) ? [self::README] : []), ...array_filter(self::documents($package), self::isMirror(...))];
    }

    /**
     * The mirrors, and the documents written in French from the start, relative to the package.
     *
     * @return list<string>
     */
    public static function french(Package $package): array
    {
        return [...self::mirrors($package), ...(is_file($package->path(self::GLOSSARY)) ? [self::GLOSSARY] : [])];
    }

    /**
     * @return array<int, string>
     */
    private static function documents(Package $package): array
    {
        return array_filter($package->filesUnder('doc'), static fn (string $file): bool => str_ends_with($file, '.md'));
    }
}
