<?php

declare(strict_types=1);

namespace Phil\DevTools\Config;

use Phil\DevTools\DevTools;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Matrix\Matrix;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\Workspace;

/**
 * What the shared configurations of PHP-CS-Fixer, Rector, PHPStan and Arkitect read from a workspace: the paths of the
 * packages analysed and their exceptions, with every relative path of a manifest made absolute from its own package.
 *
 * It depends on nothing but PHP, because the configurations load it inside each tool's own process.
 */
final readonly class Tooling
{
    /**
     * The files at the root of a package that the code style also covers, when they exist.
     *
     * @var list<string>
     */
    private const array ROOT_FILES = [
        '.php-cs-fixer.dist.php',
        'arkitect.php',
        'composer-dependency-analyser.php',
        'phil-dev.php',
        'rector.php',
    ];

    public function __construct(public Workspace $workspace)
    {
    }

    public static function fromEnvironment(?string $root = null): self
    {
        return new self(Workspace::load($root ?? DevTools::root()));
    }

    /**
     * The directories the tools read, absolute.
     *
     * @return list<string>
     */
    public function directories(): array
    {
        $directories = [];

        foreach ($this->workspace->analysed() as $package) {
            foreach ($package->paths() as $path) {
                if (is_dir($path)) {
                    $directories[] = $path;
                }
            }
        }

        return $directories;
    }

    /**
     * The single files the tools read, absolute: those a manifest lists among its paths.
     *
     * @return list<string>
     */
    public function files(): array
    {
        $files = [];

        foreach ($this->workspace->analysed() as $package) {
            foreach ($package->paths() as $path) {
                if (is_file($path)) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    /**
     * The configuration files at the root of each package analysed, absolute, which the code style also covers: they
     * load the tools' own classes, so PHPStan, which does not have them, leaves them out.
     *
     * @return list<string>
     */
    public function configurationFiles(): array
    {
        $files = [];

        foreach ($this->workspace->analysed() as $package) {
            foreach (self::ROOT_FILES as $file) {
                if (is_file($package->path($file))) {
                    $files[] = $package->path($file);
                }
            }
        }

        return $files;
    }

    /**
     * The patterns PHP-CS-Fixer leaves out (`notPath`, relative to each directory read), from each manifest's
     * `cs.exclude`.
     *
     * @return list<string>
     */
    public function codeStyleExclusions(): array
    {
        $exclusions = ['reference.php'];

        foreach ($this->workspace->analysed() as $package) {
            foreach ($package->manifest->strings('cs', 'exclude') as $pattern) {
                $exclusions[] = $pattern;
            }
        }

        return $exclusions;
    }

    /**
     * The rules the root's manifest adds to or changes in the shared code style (`cs.rules`).
     *
     * @return array<string, mixed>
     */
    public function codeStyleRules(): array
    {
        return array_filter($this->workspace->root->manifest->map('cs', 'rules'), \is_string(...), \ARRAY_FILTER_USE_KEY);
    }

    /**
     * Rector's skips from each manifest's `rector.skip`, its paths absolute: a rule class alone, a path alone, or a rule
     * class with the paths it skips.
     *
     * @return array<array-key, mixed>
     */
    public function rectorSkips(): array
    {
        $skips = [];

        foreach ($this->workspace->analysed() as $package) {
            foreach ($package->manifest->map('rector', 'skip') as $key => $value) {
                if (\is_int($key)) {
                    if (!\is_string($value)) {
                        throw new InvalidManifest(\sprintf('rector.skip of %s holds a value that is neither a rule nor a path.', $package->name));
                    }

                    $skips[] = $this->isPath($value) ? $this->absolute($package, $value) : $value;

                    continue;
                }

                $paths = [];

                foreach (\is_array($value) ? $value : [$value] as $path) {
                    if (!\is_string($path)) {
                        throw new InvalidManifest(\sprintf('rector.skip.%s of %s must list paths.', $key, $package->name));
                    }

                    $paths[] = $this->absolute($package, $path);
                }

                $existing = $skips[$key] ?? [];
                $skips[$key] = [...(\is_array($existing) ? $existing : []), ...$paths];
            }
        }

        return $skips;
    }

    /**
     * The patterns of the code layers every analysed package has, absolute, such as `…/src/Domain/*` and
     * `…/src/*\/Domain/*`, for the rules the shared Rector configuration skips in a layer.
     *
     * @return list<string>
     */
    public function layerPatterns(string $layer): array
    {
        $patterns = [];

        foreach ($this->workspace->analysed() as $package) {
            foreach ($package->sourceDirectories() as $directory) {
                $patterns[] = $directory.'/'.$layer.'/*';
                $patterns[] = $directory.'/*/'.$layer.'/*';
            }
        }

        return $patterns;
    }

    /**
     * The parameters and includes the shared PHPStan configuration adds for the workspace.
     *
     * @return array{includes: list<string>, parameters: array{phpVersion: array{min: int, max: int}, paths: list<string>, excludePaths: array{analyse: list<string>}, ignoreErrors: list<mixed>}}
     */
    public function phpstan(): array
    {
        $includes = [];
        $excludes = [
            '*/tests/Fixtures/*',
            '*/var/*',
        ];
        $ignores = [];

        foreach ($this->workspace->known() as $package) {
            foreach ($package->manifest->strings('phpstan', 'includes') as $include) {
                $includes[] = $this->absolute($package, $include);
            }
        }

        foreach ($this->workspace->analysed() as $package) {
            foreach ($package->manifest->strings('phpstan', 'excludePaths') as $exclude) {
                $excludes[] = $this->absolute($package, $exclude);
            }

            foreach ($package->manifest->map('phpstan', 'ignoreErrors') as $ignore) {
                $ignores[] = $this->ignoreError($package, $ignore);
            }
        }

        return [
            'includes' => $includes,
            'parameters' => [
                'phpVersion' => Matrix::load()->phpVersionRange(),
                'paths' => [...$this->directories(), ...$this->files()],
                'excludePaths' => ['analyse' => $excludes],
                'ignoreErrors' => $ignores,
            ],
        ];
    }

    /**
     * The witnesses of the PHPStan rules: the `tests/Witnesses/PhpStan` directory of each package analysed.
     *
     * @return list<string>
     */
    public function witnessDirectories(): array
    {
        $directories = [];

        foreach ($this->workspace->analysed() as $package) {
            $directory = $package->directory('tests/Witnesses/PhpStan');

            if (null !== $directory) {
                $directories[] = $directory;
            }
        }

        return $directories;
    }

    /**
     * The namespaces of the production code of every package analysed, without a trailing backslash.
     *
     * @return list<string>
     */
    public function namespaces(): array
    {
        $namespaces = [];

        foreach ($this->workspace->analysed() as $package) {
            foreach (array_keys($package->namespaces) as $namespace) {
                $namespaces[] = $namespace;
            }
        }

        return $namespaces;
    }

    /**
     * The directories of the production code of every package analysed.
     *
     * @return list<string>
     */
    public function sourceDirectories(): array
    {
        $directories = [];

        foreach ($this->workspace->analysed() as $package) {
            array_push($directories, ...$package->sourceDirectories());
        }

        return $directories;
    }

    /**
     * The extra Arkitect rules each analysed manifest declares under `arkitect.rules`: a callable that returns them.
     *
     * @return list<callable>
     */
    public function arkitectRules(): array
    {
        $rules = [];

        foreach ($this->workspace->analysed() as $package) {
            $callable = $package->manifest->get('arkitect', 'rules');

            if (null === $callable) {
                continue;
            }

            if (!\is_callable($callable)) {
                throw new InvalidManifest(\sprintf('arkitect.rules of %s must be a callable that returns rules.', $package->name));
            }

            $rules[] = $callable;
        }

        return $rules;
    }

    /**
     * The namespaces Arkitect admits besides those of the packages, from each manifest's `arkitect.namespaces`.
     *
     * @return list<string>
     */
    public function arkitectExtraNamespaces(): array
    {
        $namespaces = [];

        foreach ($this->workspace->analysed() as $package) {
            array_push($namespaces, ...$package->manifest->strings('arkitect', 'namespaces'));
        }

        return $namespaces;
    }

    /**
     * Writes `$data` to `var/phil-dev/<name>.php` of the root when it changed, and returns that file, for PHPStan to
     * include: PHPStan keys its container cache on the content of each configuration file, and a manifest it never
     * reads itself would otherwise leave that cache stale when it changes.
     *
     * @param array<string, mixed> $data
     */
    public function phpstanInclude(string $name, array $data): string
    {
        $directory = $this->workspace->root->path('var/phil-dev');
        $file = $directory.'/'.$name.'.php';
        $json = json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
        $content = "<?php\n\n// Generated by phil-dev from the phil-dev.php of each package. Do not edit.\n\nreturn json_decode('"
            .addcslashes($json, "'\\")."', true, flags: \\JSON_THROW_ON_ERROR);\n";

        if (!is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        if (!is_file($file) || file_get_contents($file) !== $content) {
            file_put_contents($file, $content);
        }

        return $file;
    }

    private function absolute(Package $package, string $path): string
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '*')) {
            return $path;
        }

        return $package->path($path);
    }

    private function isPath(string $value): bool
    {
        return str_contains($value, '/') || str_contains($value, '*') || str_ends_with($value, '.php');
    }

    private function ignoreError(Package $package, mixed $ignore): mixed
    {
        if (\is_array($ignore) && \is_string($ignore['path'] ?? null)) {
            $ignore['path'] = $this->absolute($package, $ignore['path']);
        }

        if (\is_array($ignore) && \is_array($ignore['paths'] ?? null)) {
            $ignore['paths'] = array_map(fn (mixed $path): mixed => \is_string($path) ? $this->absolute($package, $path) : $path, $ignore['paths']);
        }

        return $ignore;
    }
}
