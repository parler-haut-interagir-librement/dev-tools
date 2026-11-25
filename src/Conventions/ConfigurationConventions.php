<?php

declare(strict_types=1);

namespace Phil\DevTools\Conventions;

use Phil\DevTools\Files;
use Phil\DevTools\Workspace\Package;

/**
 * The conventions of a bundle's configuration: its root key is `<vendor>_<name>`, each option has a default the
 * documentation names, and its Flex recipe writes only what differs from those defaults.
 *
 * - The root key of `phil/rate-limit-bundle` is `phil_rate_limit`, that of `tilttek/dice-bundle` `tilttek_dice`: the
 *   key a reader guesses from the package's name. It is read from the bundle's `$extensionAlias`, from the `getAlias()`
 *   of its extension, or derived from the bundle's class name as Symfony does.
 * - Each option of the tree — a scalar, boolean, integer, float, enum or string node outside a prototype — has a
 *   default (`defaultValue()`, `defaultTrue()`, `defaultFalse()`, `defaultNull()`) or says it is required
 *   (`isRequired()`), and its name appears in the English documentation, `doc/*.md` or `README.md`.
 * - The latest recipe under `recipe/<version>/config/packages/<root key>.yaml` sets no option to the value its default
 *   already has: a recipe that copies the defaults hides the ones an application chose, and freezes an old default in
 *   every application that installed it.
 *
 * The tree is read by {@see ConfigurationTreeReader} from the files under the source directories that build one,
 * the recipe by {@see RecipeReader}.
 */
final class ConfigurationConventions
{
    /**
     * @return list<string> the warnings, empty when the package follows the conventions or is no bundle
     */
    public function check(Package $package): array
    {
        if (!$package->isBundle()) {
            return [];
        }

        $sources = $this->treeSources($package);

        if ([] === $sources) {
            return [];
        }

        $warnings = [];
        $expected = $this->expectedRootKey($package->name);
        $root = $this->rootKey($package);

        if (null !== $root && $root !== $expected) {
            $warnings[] = \sprintf('the configuration\'s root key is "%s": name it "%s", after the package.', $root, $expected);
        }

        $options = [];

        foreach ($sources as $source) {
            array_push($options, ...new ConfigurationTreeReader()->options(Files::read($source)));
        }

        $documentation = $this->documentation($package);

        foreach ($options as $option) {
            if (!$option->hasDefault && !$option->required) {
                $warnings[] = \sprintf('option "%s" has no default: give it one, or declare it isRequired().', $option->path);
            }

            if (!$this->isDocumented($option->name(), $documentation)) {
                $warnings[] = \sprintf('option "%s" is not named in doc/ nor in README.md: document it with its default.', $option->path);
            }
        }

        foreach ($this->recipeCopies($package, $root ?? $expected, $options) as $warning) {
            $warnings[] = $warning;
        }

        return $warnings;
    }

    /**
     * The root key a package's name gives: `phil/rate-limit-bundle` → `phil_rate_limit`.
     */
    public function expectedRootKey(string $package): string
    {
        [$vendor, $name] = array_pad(explode('/', $package, 2), 2, '');
        $name = preg_replace('/-bundle$/', '', $name) ?? $name;

        return str_replace('-', '_', strtolower($vendor.'_'.$name));
    }

    /**
     * The files under the source directories that build a configuration tree.
     *
     * @return list<string>
     */
    private function treeSources(Package $package): array
    {
        $sources = [];

        foreach ($package->sourceDirectories() as $directory) {
            foreach ($package->filesUnder(substr($directory, \strlen($package->dir) + 1)) as $relative) {
                if (!str_ends_with($relative, '.php')) {
                    continue;
                }

                $code = Files::read($package->path($relative));

                if (str_contains($code, 'DefinitionConfigurator') || str_contains($code, 'TreeBuilder')) {
                    $sources[] = $package->path($relative);
                }
            }
        }

        return $sources;
    }

    /**
     * The root key the bundle declares, or the one Symfony derives from its class name; null when no bundle class is
     * found.
     */
    private function rootKey(Package $package): ?string
    {
        foreach ($package->sourceDirectories() as $directory) {
            foreach ($this->glob($directory.'/*Bundle.php') as $bundle) {
                $code = Files::read($bundle);

                if (1 === preg_match('/\$extensionAlias\s*=\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $code, $match)) {
                    return $match[1];
                }

                $class = basename($bundle, '.php');

                return strtolower((string) preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'], '\1_\2', substr($class, 0, -\strlen('Bundle'))));
            }

            foreach ($package->filesUnder(substr($directory, \strlen($package->dir) + 1)) as $relative) {
                if (str_ends_with($relative, 'Extension.php') && 1 === preg_match('/function\s+getAlias\s*\(\s*\)\s*:\s*string\s*\{\s*return\s*[\'"]([A-Za-z0-9_]+)[\'"]/', Files::read($package->path($relative)), $match)) {
                    return $match[1];
                }
            }
        }

        return null;
    }

    /**
     * The English documentation of the package, in one string: `doc/*.md` and `README.md`.
     */
    private function documentation(Package $package): string
    {
        $text = '';

        foreach ([$package->path('README.md'), ...$this->glob($package->path('doc').'/*.md')] as $file) {
            if (is_file($file)) {
                $text .= Files::read($file)."\n";
            }
        }

        return $text;
    }

    private function isDocumented(string $name, string $documentation): bool
    {
        $quoted = preg_quote($name, '/');

        return 1 === preg_match('/`[^`\n]*(?<![A-Za-z0-9_])'.$quoted.'(?![A-Za-z0-9_])[^`\n]*`|^\s*'.$quoted.':/m', $documentation);
    }

    /**
     * The settings of the latest recipe that copy a default.
     *
     * @param list<ConfigurationOption> $options
     *
     * @return list<string>
     */
    private function recipeCopies(Package $package, string $root, array $options): array
    {
        $versions = $this->glob($package->path('recipe').'/*', \GLOB_ONLYDIR);

        if ([] === $versions) {
            return [];
        }

        usort($versions, static fn (string $a, string $b): int => version_compare(basename($a), basename($b)));
        $file = end($versions).'/config/packages/'.$root.'.yaml';

        if (!is_file($file)) {
            return [];
        }

        $defaults = [];

        foreach ($options as $option) {
            if (null !== $option->default) {
                $defaults[$option->path] = $option->default;
            }
        }

        $warnings = [];

        foreach (new RecipeReader()->values(Files::read($file), $root) as $path => $value) {
            if (($defaults[$path] ?? null) === $value) {
                $warnings[] = \sprintf('the recipe %s sets "%s" to its default, %s: leave it out.', substr($file, \strlen($package->dir) + 1), $path, $value);
            }
        }

        return $warnings;
    }

    /**
     * @return list<string>
     */
    private function glob(string $pattern, int $flags = 0): array
    {
        $found = glob($pattern, $flags);

        return false === $found ? [] : $found;
    }
}
