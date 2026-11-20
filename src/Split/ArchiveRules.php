<?php

declare(strict_types=1);

namespace Phil\DevTools\Split;

use Phil\DevTools\Files;

/**
 * What the Composer archive of a package leaves out: the paths its `.gitattributes` marks `export-ignore`, and those
 * of `archive.exclude` in its `composer.json`. A distribution of the package — the archive of its split repository —
 * holds every other file.
 *
 * Each pattern is an {@see ArchivePattern}. A leading `!` in `archive.exclude` takes a path back; the last pattern that
 * matches a path decides. A directory left out takes its whole content with it.
 */
final readonly class ArchiveRules
{
    /**
     * The files every archive keeps when the package has them: the licence goes with each copy of the code. The
     * composer.json is not one of them, since Composer reads a package's metadata from its repository, not from the
     * archive, and a kit may leave it out of what its own installer downloads.
     *
     * @var list<string>
     */
    private const array ESSENTIAL = ['LICENSE', 'README.md'];

    /**
     * The kinds of paths of an `autoload` section.
     *
     * @var list<string>
     */
    private const array AUTOLOAD_KINDS = ['psr-4', 'psr-0', 'classmap', 'files'];

    /**
     * @param list<ArchivePattern> $patterns
     */
    private function __construct(private array $patterns)
    {
    }

    /**
     * The rules of the package in `$directory`: its `.gitattributes`, then its `archive.exclude`.
     *
     * @param array<array-key, mixed> $composer the decoded `composer.json` of the package
     */
    public static function of(string $directory, array $composer): self
    {
        $patterns = [];
        $attributes = $directory.'/.gitattributes';

        if (is_file($attributes)) {
            foreach (explode("\n", str_replace("\r", "\n", Files::read($attributes))) as $line) {
                $fields = preg_split('/\s+/', trim($line), -1, \PREG_SPLIT_NO_EMPTY);
                $fields = false === $fields ? [] : $fields;

                if (isset($fields[0]) && !str_starts_with($fields[0], '#') && \in_array('export-ignore', \array_slice($fields, 1), true)) {
                    $patterns[] = new ArchivePattern($fields[0], true, '.gitattributes');
                }
            }
        }

        $archive = $composer['archive'] ?? null;
        $excludes = \is_array($archive) && \is_array($archive['exclude'] ?? null) ? $archive['exclude'] : [];

        foreach ($excludes as $pattern) {
            if (\is_string($pattern) && '' !== trim($pattern)) {
                $pattern = trim($pattern);
                $taken = str_starts_with($pattern, '!');
                $patterns[] = new ArchivePattern($taken ? substr($pattern, 1) : $pattern, !$taken, 'archive.exclude');
            }
        }

        return new self($patterns);
    }

    /**
     * Whether the archive leaves out the path, relative to the package's directory: the path itself or one of the
     * directories above it.
     */
    public function excludes(string $path, bool $directory): bool
    {
        return $this->excluder($path, $directory) instanceof ArchivePattern;
    }

    /**
     * What the archive must keep and leaves out, each with the reason it must keep it and the pattern that leaves it
     * out: the paths `autoload` and `autoload-dev` name — a test that autoload-dev points to must ship with the code —,
     * and LICENSE and README.md, those of them the package has.
     *
     * @param array<array-key, mixed> $composer
     *
     * @return list<string>
     */
    public function missing(string $directory, array $composer): array
    {
        $missing = [];

        foreach ($this->required($composer) as $path => $reason) {
            $absolute = $directory.'/'.$path;

            if (!file_exists($absolute)) {
                continue;
            }

            $excluder = $this->excluder($path, is_dir($absolute));

            if ($excluder instanceof ArchivePattern) {
                $missing[] = \sprintf('the archive leaves out %s%s (%s), through "%s" in %s', $path, is_dir($absolute) ? '/' : '', $reason, $excluder->pattern, $excluder->origin);
            }
        }

        return $missing;
    }

    /**
     * The paths the archive must keep, each with its reason, in the order of composer.json.
     *
     * @param array<array-key, mixed> $composer
     *
     * @return array<string, string>
     */
    private function required(array $composer): array
    {
        $required = [];

        foreach (['autoload', 'autoload-dev'] as $section) {
            $autoload = $composer[$section] ?? null;

            if (!\is_array($autoload)) {
                continue;
            }

            foreach (self::AUTOLOAD_KINDS as $kind) {
                foreach ($this->paths($autoload[$kind] ?? null) as $path) {
                    $required[$path] ??= $section.' '.$kind;
                }
            }
        }

        foreach (self::ESSENTIAL as $file) {
            $required[$file] ??= 'essential';
        }

        return $required;
    }

    /**
     * The paths of one kind of an `autoload` section: a namespace maps to a path or to a list of them, a classmap and
     * files are lists.
     *
     * @return list<string>
     */
    private function paths(mixed $entries): array
    {
        $paths = [];

        foreach (\is_array($entries) ? $entries : [] as $entry) {
            foreach (\is_array($entry) ? $entry : [$entry] as $path) {
                $path = \is_string($path) ? $this->normalise($path) : '';

                if ('' !== $path) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    /**
     * The pattern that leaves out the path or a directory above it, or null when the archive keeps it.
     */
    private function excluder(string $path, bool $directory): ?ArchivePattern
    {
        $segments = explode('/', $this->normalise($path));
        $prefix = '';

        foreach ($segments as $index => $segment) {
            $prefix = '' === $prefix ? $segment : $prefix.'/'.$segment;
            $isDirectory = $directory || $index < \count($segments) - 1;
            $decision = null;

            foreach ($this->patterns as $pattern) {
                if ($pattern->matches($prefix, $isDirectory)) {
                    $decision = $pattern;
                }
            }

            if (null !== $decision && $decision->excluded) {
                return $decision;
            }
        }

        return null;
    }

    /**
     * A path relative to the package without `./`, leading or trailing slashes.
     */
    private function normalise(string $path): string
    {
        $path = trim($path);

        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        return trim($path, '/');
    }
}
