<?php

declare(strict_types=1);

namespace Phil\DevTools\Workspace;

use FilesystemIterator;
use JsonException;
use Phil\DevTools\Exception\WorkspaceNotFound;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * One package of a workspace: its directory, what its `composer.json` declares and its `phil-dev.php`.
 *
 * A package is the root, a package of the monorepo under one of the root's globs, or a package installed under
 * `vendor/` that ships a `phil-dev.php`: the last kind is known to the generated configurations — its layers, its
 * namespaces — but never analysed.
 */
final readonly class Package
{
    /**
     * @param string                      $dir         the absolute directory, without a trailing slash
     * @param string                      $relative    the directory relative to the workspace root, '' for the root
     * @param array<string, list<string>> $namespaces  the PSR-4 namespaces of `autoload`, without a trailing
     *                                                 backslash, each with its directories relative to the package
     * @param list<string>                $requires    the packages `require` names
     * @param list<string>                $devRequires the packages `require-dev` names
     */
    public function __construct(
        public string $dir,
        public string $relative,
        public string $name,
        public string $type,
        public Manifest $manifest,
        public array $namespaces,
        public array $requires,
        public array $devRequires,
        public PackageRole $role,
    ) {
    }

    public static function fromDirectory(string $dir, string $relative, PackageRole $role): self
    {
        $dir = rtrim($dir, '/');
        $file = $dir.'/composer.json';
        $json = is_file($file) ? file_get_contents($file) : false;

        if (false === $json) {
            throw new WorkspaceNotFound(\sprintf('%s holds no composer.json.', $dir));
        }

        try {
            $composer = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new WorkspaceNotFound(\sprintf('%s is not valid JSON: %s', $file, $jsonException->getMessage()), $jsonException->getCode(), previous: $jsonException);
        }

        if (!\is_array($composer)) {
            throw new WorkspaceNotFound(\sprintf('%s is not a JSON object.', $file));
        }

        $name = $composer['name'] ?? basename($dir);
        $type = $composer['type'] ?? 'library';

        return new self(
            $dir,
            $relative,
            \is_string($name) ? $name : basename($dir),
            \is_string($type) ? $type : 'library',
            Manifest::load($dir.'/'.Manifest::FILE),
            self::namespaces($composer['autoload'] ?? null),
            self::names($composer['require'] ?? null),
            self::names($composer['require-dev'] ?? null),
            $role,
        );
    }

    public function isRoot(): bool
    {
        return PackageRole::Root === $this->role;
    }

    public function isBundle(): bool
    {
        if ('symfony-bundle' === $this->type) {
            return true;
        }

        return [] !== array_filter(array_keys($this->namespaces), static fn (string $namespace): bool => str_ends_with($namespace, 'Bundle'));
    }

    public function vendor(): string
    {
        return explode('/', $this->name)[0];
    }

    /**
     * An absolute path inside the package.
     */
    public function path(string $relative): string
    {
        return '' === $relative || '.' === $relative ? $this->dir : $this->dir.'/'.ltrim($relative, '/');
    }

    /**
     * The paths the tools read — directories and files — absolute, those that exist: the manifest's `paths`, or `src`
     * and `tests`.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        $paths = [];

        foreach ($this->manifest->strings('paths', null, ['src', 'tests']) as $path) {
            $absolute = $this->path($path);

            if (file_exists($absolute)) {
                $paths[] = $absolute;
            }
        }

        return $paths;
    }

    /**
     * The directories of the production code, absolute, those that exist.
     *
     * @return list<string>
     */
    public function sourceDirectories(): array
    {
        $directories = [];

        foreach ($this->namespaces as $dirs) {
            foreach ($dirs as $dir) {
                $absolute = $this->path($dir);

                if (is_dir($absolute) && !\in_array($absolute, $directories, true)) {
                    $directories[] = $absolute;
                }
            }
        }

        return $directories;
    }

    /**
     * Every directory of the package matching `$relative`, such as `tests/Witnesses/PhpStan`, when it exists.
     */
    public function directory(string $relative): ?string
    {
        $absolute = $this->path($relative);

        return is_dir($absolute) ? $absolute : null;
    }

    /**
     * The files under a directory of the package, relative to the package, sorted; `vendor/`, `var/`, `node_modules/`
     * and dot directories left out.
     *
     * @return list<string>
     */
    public function filesUnder(string $directory): array
    {
        $root = $this->path($directory);

        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $info */
        foreach ($iterator as $info) {
            $relative = substr($info->getPathname(), \strlen($this->dir) + 1);

            if (1 !== preg_match('#(^|/)(vendor|var|node_modules|\.[^/]+)/#', $relative)) {
                $files[] = $relative;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function namespaces(mixed $autoload): array
    {
        if (!\is_array($autoload) || !\is_array($autoload['psr-4'] ?? null)) {
            return [];
        }

        $namespaces = [];

        foreach ($autoload['psr-4'] as $namespace => $dirs) {
            if (!\is_string($namespace) || '' === trim($namespace, '\\')) {
                continue;
            }

            $list = [];

            foreach (\is_array($dirs) ? $dirs : [$dirs] as $dir) {
                if (\is_string($dir)) {
                    $list[] = '' === $dir ? '.' : rtrim($dir, '/');
                }
            }

            $namespaces[rtrim($namespace, '\\')] = $list;
        }

        return $namespaces;
    }

    /**
     * @return list<string>
     */
    private static function names(mixed $requires): array
    {
        if (!\is_array($requires)) {
            return [];
        }

        $names = [];

        foreach (array_keys($requires) as $name) {
            if (\is_string($name) && str_contains($name, '/')) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
