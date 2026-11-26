<?php

declare(strict_types=1);

namespace Phil\DevTools\Config;

use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\PackageRole;
use Phil\DevTools\Workspace\Workspace;

/**
 * The optional integrations of the bundles of a workspace: whether a bundle keeps the code that touches the packages it
 * only suggests in an `Integration` layer, and the `Suggested…` layers of those packages that the workspace does not
 * know otherwise.
 */
final readonly class Integrations
{
    /** The directory, beside `Domain`, that holds the code touching the packages a bundle only suggests. */
    public const string DIRECTORY = 'Integration';

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
     * Whether the code of a package touching what it suggests lives in its own layer: `deptrac.integrations`, or by
     * default whether a bundle has an `Integration` directory in one of its namespaces. A library, and a package whose
     * layers its manifest declares, never does.
     */
    public function integrates(Package $package): bool
    {
        if (!$package->isBundle() || null !== $package->manifest->get('deptrac', 'layers')) {
            return false;
        }

        $found = false;

        foreach ($package->namespaces as $directories) {
            foreach ($directories as $directory) {
                $found = $found || is_dir($package->path($directory.'/'.self::DIRECTORY));
            }
        }

        return $package->manifest->flag('deptrac', 'integrations', $found);
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
            $directory = $this->workspace->root->path('vendor/'.$name);

            if (\in_array($name, $package->requires, true) || $this->workspace->byName($name) instanceof Package || !is_file($directory.'/composer.json')) {
                continue;
            }

            foreach (array_keys(Package::fromDirectory($directory, 'vendor/'.$name, PackageRole::Installed)->namespaces) as $namespace) {
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
