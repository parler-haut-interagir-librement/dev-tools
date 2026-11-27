<?php

declare(strict_types=1);

namespace Phil\DevTools\Docs;

use Phil\DevTools\Workspace\Package;

/**
 * Where the French documentation of a package lives: `README.fr.md` beside `README.md`, and `doc/fr/<name>.md` for
 * each `doc/<name>.md`, with the same names and the same tree. Every other document at the root of a package or of a
 * repository has its French twin beside it: `CHANGELOG.fr.md` for `CHANGELOG.md`, `SECURITY.fr.md` for `SECURITY.md`;
 * `AGENTS.md`, written for coding agents, and the licence have none.
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

    private const string TWIN_SUFFIX = '.fr.md';

    /**
     * The documents at the root that have no French twin.
     *
     * @var list<string>
     */
    public const array WITHOUT_TWIN = ['AGENTS.md', 'LICENSE.md'];

    public static function isMirror(string $file): bool
    {
        return self::README === $file || self::isTwin($file) || (str_starts_with($file, self::DIRECTORY) && str_ends_with($file, '.md'));
    }

    /**
     * A French twin at the root, `README.fr.md` included: `<name>.fr.md` beside a document that has one.
     */
    public static function isTwin(string $file): bool
    {
        return !str_contains($file, '/') && str_ends_with($file, self::TWIN_SUFFIX) && \strlen($file) > \strlen(self::TWIN_SUFFIX)
            && !\in_array(substr($file, 0, -\strlen(self::TWIN_SUFFIX)).'.md', self::WITHOUT_TWIN, true);
    }

    /**
     * An English document at the root that has a French twin, `README.md` included.
     */
    public static function hasTwin(string $file): bool
    {
        return !str_contains($file, '/') && str_ends_with($file, '.md') && !str_ends_with($file, self::TWIN_SUFFIX)
            && !\in_array($file, self::WITHOUT_TWIN, true);
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
        return str_starts_with($source, self::SOURCE_DIRECTORY)
            ? self::DIRECTORY.substr($source, \strlen(self::SOURCE_DIRECTORY))
            : substr($source, 0, -\strlen('.md')).self::TWIN_SUFFIX;
    }

    public static function sourceOf(string $mirror): string
    {
        return str_starts_with($mirror, self::DIRECTORY)
            ? self::SOURCE_DIRECTORY.substr($mirror, \strlen(self::DIRECTORY))
            : substr($mirror, 0, -\strlen(self::TWIN_SUFFIX)).'.md';
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
     * The English documents at the root that have a French twin, sorted: `README.md` only when asked, since a package
     * checks it with its documentation.
     *
     * @return list<string>
     */
    public static function twinSources(Package $package, bool $readme = false): array
    {
        return self::rootDocuments($package, static fn (string $file): bool => self::hasTwin($file) && ($readme || self::SOURCE_README !== $file));
    }

    /**
     * The French twins that exist at the root, sorted: `README.fr.md` only when asked.
     *
     * @return list<string>
     */
    public static function twins(Package $package, bool $readme = false): array
    {
        return self::rootDocuments($package, static fn (string $file): bool => self::isTwin($file) && ($readme || self::README !== $file));
    }

    /**
     * The mirrors, the twins, and the documents written in French from the start, relative to the package.
     *
     * @return list<string>
     */
    public static function french(Package $package): array
    {
        return [...self::mirrors($package), ...self::twins($package), ...(is_file($package->path(self::GLOSSARY)) ? [self::GLOSSARY] : [])];
    }

    /**
     * @param callable(string): bool $filter
     *
     * @return list<string>
     */
    private static function rootDocuments(Package $package, callable $filter): array
    {
        $entries = scandir($package->dir);
        $files = array_filter(false === $entries ? [] : $entries, static fn (string $file): bool => $filter($file) && is_file($package->path($file)));

        return array_values($files);
    }

    /**
     * @return array<int, string>
     */
    private static function documents(Package $package): array
    {
        return array_filter($package->filesUnder('doc'), static fn (string $file): bool => str_ends_with($file, '.md'));
    }
}
