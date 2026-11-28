<?php

declare(strict_types=1);

namespace Phil\DevTools\Config;

use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\PackageRole;
use Phil\DevTools\Workspace\Workspace;

/**
 * The optional integrations of the bundles of a workspace: the directories in which a bundle keeps the code that touches
 * the packages it only suggests, gathered in its `Integration` layer, and the `Suggested…` layers of those packages that the workspace does not
 * know otherwise.
 */
final readonly class Integrations
{
    /** The directory, beside `Domain`, that holds the code touching the packages a bundle only suggests. */
    public const string DIRECTORY = 'Integration';

    /** A directory relative to a namespace: segments that are PHP namespace names, separated by slashes. */
    private const string PATTERN = '{^[A-Za-z_][A-Za-z0-9_]*(?:/[A-Za-z_][A-Za-z0-9_]*)*$}D';

    public function __construct(private Workspace $workspace)
    {
    }

    /**
     * The name of the layer of a namespace a bundle only suggests: `Suggested` and the namespace without backslashes
     * (`Loupe\Loupe` → `SuggestedLoupeLoupe`).
     */
    public static function name(string $namespace): string
    {
        return 'Suggested'.DeptracConfig::name($namespace);
    }

    /**
     * Whether the code of a package touching what it suggests lives in its own layer: whether it has integration
     * directories.
     */
    public function integrates(Package $package): bool
    {
        return [] !== $this->directories($package);
    }

    /**
     * The directories of a package that hold the code touching what it suggests, relative to each of its namespaces
     * and separated by slashes (`Infrastructure/Doctrine`): the list `deptrac.integrations` names, `Integration` when it
     * is `true`, none when it is `false`, and by default `Integration` when a bundle has that directory in one of its
     * namespaces. A library, and a package whose layers its manifest declares, have none.
     *
     * @return list<string>
     */
    public function directories(Package $package): array
    {
        if (!$package->isBundle() || null !== $package->manifest->get('deptrac', 'layers')) {
            return [];
        }

        $declared = $package->manifest->get('deptrac', 'integrations');

        if (null === $declared) {
            $declared = false;

            foreach ($package->namespaces as $directories) {
                foreach ($directories as $directory) {
                    $declared = $declared || is_dir($package->path($directory.'/'.self::DIRECTORY));
                }
            }
        }

        if (\is_bool($declared)) {
            return $declared ? [self::DIRECTORY] : [];
        }

        if (!\is_array($declared) || !array_is_list($declared)) {
            throw new InvalidManifest(\sprintf('deptrac.integrations of %s must be a boolean or a list of directories.', $package->name));
        }

        $list = [];

        foreach ($declared as $directory) {
            if (!\is_string($directory) || 1 !== preg_match(self::PATTERN, $directory)) {
                throw new InvalidManifest(\sprintf('deptrac.integrations of %s lists "%s", which is not a directory of its namespace such as "Infrastructure/Doctrine".', $package->name, \is_string($directory) ? $directory : get_debug_type($directory)));
            }

            $list[] = $directory;
        }

        return array_values(array_unique($list));
    }

    /**
     * The `Suggested…` layers of a package that integrates, by name: each PSR-4 namespace of the packages it suggests,
     * neither required nor known, installed under the root's `vendor/`, that no regular expression of the default or of
     * an `allow` already covers.
     *
     * @param list<string> $covering the regular expressions of the default and of every `allow`
     *
     * @return array<string, string> the regular expression of each layer, by name
     */
    public function suggested(Package $package, array $covering): array
    {
        if (!$this->integrates($package)) {
            return [];
        }

        $layers = [];

        foreach ($package->suggests as $name) {
            $relative = 'vendor/'.$name;
            $directory = $this->workspace->root->path($relative);

            if (\in_array($name, $package->requires, true) || $this->workspace->byName($name) instanceof Package || !is_file($directory.'/composer.json')) {
                continue;
            }

            foreach (array_keys(Package::fromDirectory($directory, $relative, PackageRole::Installed)->namespaces) as $namespace) {
                if (!$this->covered($namespace, $covering)) {
                    $layers[self::name($namespace)] = '^'.str_replace('\\', '\\\\', $namespace).'\\\\.*';
                }
            }
        }

        return $layers;
    }

    /**
     * Whether a regular expression of the default or of an `allow` matches the classes of a namespace.
     *
     * @param list<string> $regexes
     */
    private function covered(string $namespace, array $regexes): bool
    {
        return array_any($regexes, static fn (string $regex): bool => 1 === preg_match('{'.$regex.'}', $namespace.'\\Probe'));
    }
}
