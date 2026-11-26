<?php

declare(strict_types=1);

namespace Phil\DevTools\Config;

use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\Workspace;

/**
 * The Deptrac configuration of a workspace, generated from the `composer.json` and `phil-dev.php` of every package it
 * knows, so that no central file lists the packages and their layers.
 *
 * - A **library** is one layer, named after its namespace without backslashes (`Phil\Slug` → `PhilSlug`).
 * - A **bundle** has a layer per code layer, `<Name>Domain`, `<Name>Application` and `<Name>Infrastructure`, and
 *   `<Name>` for the classes at the root of its namespace, the bundle class.
 * - `DefaultAllowed` is what every library and every domain may use: the Symfony components and contracts, the
 *   polyfills and the PSR interfaces — a library uses an existing component rather than reinventing what it does. The
 *   root's `deptrac.default` replaces it.
 * - Each package's `deptrac.allow` lists the other dependencies it is allowed, decided for that package: each becomes
 *   an `Allowed…` layer that only the packages allowing it see.
 * - `Forbidden` is the rest of the world, closed by a negative lookahead: a dependency added tomorrow falls there
 *   without anyone having to think of putting it there, and allowing it is a decision.
 *
 * A library sees the default, what it is allowed and the libraries it requires — never a bundle. A bundle's domain
 * sees the same, and the domains it requires; its application sees everything but the infrastructure of what it
 * requires; its infrastructure and its bundle class see everything, `require-dev` and `Forbidden` included: they
 * integrate. A manifest replaces the layers of its package with `deptrac.layers` and the rules of a layer with
 * `deptrac.ruleset`.
 *
 * A bundle with an `Integration` directory beside its `Domain` — or whose manifest sets `deptrac.integrations` — keeps
 * the code that touches the packages it only suggests in `<Name>Integration`:
 *
 * - each package of its `suggest` that it does not require becomes visible to that layer and to the bundle class only:
 *   a known package through its own layers, an installed one through a `Suggested…` layer named after its namespace,
 *   read from `vendor/<name>/composer.json`; a namespace the default or an `allow` already covers stays there;
 * - its domain, application and infrastructure see neither the integration nor those packages, even those of
 *   `require-dev`;
 * - only its bundle class sees the integration, which no other package sees;
 * - every other layer that sees `Forbidden` sees the `Suggested…` layers too, the rest of the world they were taken
 *   from.
 */
final readonly class DeptracConfig
{
    public const string DEFAULT = 'DefaultAllowed';

    public const string FORBIDDEN = 'Forbidden';

    /** @var list<string> */
    public const array DEFAULT_ALLOWED = [
        '^Symfony\\\\Component\\\\.*',
        '^Symfony\\\\Contracts\\\\.*',
        '^Symfony\\\\Polyfill\\\\.*',
        '^Psr\\\\.*',
    ];

    private Integrations $integrations;

    public function __construct(private Workspace $workspace)
    {
        $this->integrations = new Integrations($workspace);
    }

    /**
     * @return array{deptrac: array{paths: list<string>, layers: list<array{name: string, collectors: list<array{type: string, value: string}>}>, ruleset: array<string, list<string>>}}
     */
    public function build(): array
    {
        $default = $this->workspace->root->manifest->strings('deptrac', 'default', self::DEFAULT_ALLOWED);
        $layers = [];

        foreach ($this->workspace->known() as $package) {
            foreach ($this->layersOf($package) as $layer) {
                if (isset($layers[$layer->name])) {
                    throw new InvalidManifest(\sprintf('The layer "%s" of %s is already declared by another package.', $layer->name, $package->name));
                }

                $layers[$layer->name] = $layer->regexes;
            }
        }

        $allowed = $this->allowedLayers($default);
        $covering = [...$default, ...array_merge(...array_values($allowed))];
        $suggested = $this->suggestedLayers($layers, $covering);

        $lookaheads = '';
        $generated = [];

        foreach ([...$layers, self::DEFAULT => $default, ...$allowed, ...$suggested] as $name => $regexes) {
            $generated[] = ['name' => $name, 'collectors' => $this->collectors($regexes)];

            foreach ($regexes as $regex) {
                $lookaheads .= '(?!'.ltrim($regex, '^').')';
            }
        }

        // `[^\\]+\\.+` requires a namespace separator, which leaves out PHP's internal classes (`\DateTimeImmutable`);
        // `Random\` is left out for the same reason: the classes of the `random` extension are internal.
        $generated[] = ['name' => self::FORBIDDEN, 'collectors' => $this->collectors(['^'.$lookaheads.'(?!Random\\\\)[^\\\\]+\\\\.+'])];

        $known = [...$layers, self::DEFAULT => [], self::FORBIDDEN => [], ...$allowed, ...$suggested];
        $ruleset = [];

        foreach ($this->workspace->known() as $package) {
            foreach ($this->rulesOf($package, $allowed, $suggested, $covering) as $name => $visible) {
                $ruleset[$name] = array_values(array_unique(array_filter(
                    $visible,
                    static fn (string $other): bool => $other !== $name && isset($known[$other]),
                )));
            }
        }

        $paths = [];

        foreach ($this->workspace->analysed() as $package) {
            array_push($paths, ...$package->sourceDirectories());
        }

        return ['deptrac' => ['paths' => $paths, 'layers' => $generated, 'ruleset' => $ruleset]];
    }

    public function yaml(): string
    {
        return YamlWriter::write($this->build(), "Generated by phil-dev from the composer.json and phil-dev.php of each package.\nDo not edit: change the phil-dev.php of a package instead.");
    }

    /**
     * The extra Deptrac configurations of the root (`deptrac.configs`), absolute: rules no package owns.
     *
     * @return list<string>
     */
    public function extraConfigurations(): array
    {
        return array_map(
            $this->workspace->root->path(...),
            $this->workspace->root->manifest->strings('deptrac', 'configs'),
        );
    }

    public static function name(string $namespace): string
    {
        return str_replace('\\', '', $namespace);
    }

    /**
     * The name of the layer of a dependency some package is allowed: `Allowed` and the namespace its regular
     * expression starts with (`^League\\Uri\\.*` → `AllowedLeagueUri`), or its position when it has none.
     */
    public static function allowedName(string $regex, int $position): string
    {
        if (1 === preg_match('/^\^((?:[A-Za-z0-9_]+\\\\\\\\)+)/', $regex, $match)) {
            return 'Allowed'.str_replace('\\', '', $match[1]);
        }

        return 'Allowed'.$position;
    }

    /**
     * The `Suggested…` layers of every package that integrates, each once, but a name a package already declares.
     *
     * @param array<string, list<string>> $layers
     * @param list<string>                $covering
     *
     * @return array<string, list<string>>
     */
    private function suggestedLayers(array $layers, array $covering): array
    {
        $suggested = [];

        foreach ($this->workspace->known() as $package) {
            foreach ($this->integrations->suggested($package, $covering) as $name => $regex) {
                if (!isset($layers[$name])) {
                    $suggested[$name] = [$regex];
                }
            }
        }

        return $suggested;
    }

    /**
     * @param list<string> $regexes
     *
     * @return list<array{type: string, value: string}>
     */
    private function collectors(array $regexes): array
    {
        return array_map(static fn (string $regex): array => ['type' => 'classLike', 'value' => $regex], $regexes);
    }

    /**
     * A layer per dependency some package is allowed, each once, by name.
     *
     * @param list<string> $default
     *
     * @return array<string, list<string>>
     */
    private function allowedLayers(array $default): array
    {
        $layers = [];

        foreach ($this->workspace->known() as $package) {
            foreach ($package->manifest->strings('deptrac', 'allow') as $regex) {
                if (!\in_array($regex, $default, true) && !\in_array([$regex], $layers, true)) {
                    $layers[self::allowedName($regex, \count($layers) + 1)] = [$regex];
                }
            }
        }

        return $layers;
    }

    /**
     * The names of the layers a package is allowed besides the default.
     *
     * @param array<string, list<string>> $allowed
     *
     * @return list<string>
     */
    private function allowedOf(Package $package, array $allowed): array
    {
        $names = [];

        foreach ($package->manifest->strings('deptrac', 'allow') as $regex) {
            $name = array_search([$regex], $allowed, true);

            if (\is_string($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * The layers of a package.
     *
     * @return list<Layer>
     */
    private function layersOf(Package $package): array
    {
        $declared = $package->manifest->get('deptrac', 'layers');

        if (null !== $declared) {
            return $this->declaredLayers($package, $declared);
        }

        $layers = [];

        foreach (array_keys($package->namespaces) as $namespace) {
            $name = self::name($namespace);
            $quoted = str_replace('\\', '\\\\', $namespace);

            if (!$package->isBundle()) {
                $layers[] = new Layer($name, ['^'.$quoted.'\\\\.*'], LayerKind::Library);

                continue;
            }

            $layers[] = new Layer($name.'Domain', ['^'.$quoted.'\\\\Domain\\\\.*'], LayerKind::Domain);
            $layers[] = new Layer($name.'Application', ['^'.$quoted.'\\\\Application\\\\.*'], LayerKind::Application);
            $layers[] = new Layer($name.'Infrastructure', ['^'.$quoted.'\\\\Infrastructure\\\\.*'], LayerKind::Infrastructure);

            if ($this->integrations->integrates($package)) {
                $layers[] = new Layer($name.Integrations::DIRECTORY, ['^'.$quoted.'\\\\'.Integrations::DIRECTORY.'\\\\.*'], LayerKind::Integration);
            }

            $layers[] = new Layer($name, ['^'.$quoted.'\\\\[^\\\\]+$'], LayerKind::Bundle);
        }

        return $layers;
    }

    /**
     * @return list<Layer>
     */
    private function declaredLayers(Package $package, mixed $declared): array
    {
        if (!\is_array($declared)) {
            throw new InvalidManifest(\sprintf('deptrac.layers of %s must map layer names to regular expressions.', $package->name));
        }

        $layers = [];

        foreach ($declared as $name => $regexes) {
            $list = \is_string($regexes) ? [$regexes] : $regexes;

            if (!\is_string($name) || !\is_array($list) || [] !== array_filter($list, static fn (mixed $regex): bool => !\is_string($regex))) {
                throw new InvalidManifest(\sprintf('deptrac.layers of %s must map layer names to regular expressions.', $package->name));
            }

            /** @var list<string> $list */
            $layers[] = new Layer($name, $list, LayerKind::Library);
        }

        return $layers;
    }

    /**
     * The rules of each layer of a package: the defaults, replaced layer by layer by the manifest's `deptrac.ruleset`.
     *
     * Every rule that sees `Forbidden` also sees the `Suggested…` layers of the other packages; the integration and
     * the bundle class of a package that integrates see its own suggested packages as well.
     *
     * @param array<string, list<string>> $allowed
     * @param array<string, list<string>> $suggested the `Suggested…` layers of every package
     * @param list<string>                $covering  the regular expressions of the default and of every `allow`
     *
     * @return array<string, list<string>>
     */
    private function rulesOf(Package $package, array $allowed, array $suggested, array $covering): array
    {
        $integrated = $this->integratedOf($package, $covering);
        $rules = $this->defaultRulesOf($package, $this->allowedOf($package, $allowed), $integrated);

        foreach ($package->manifest->map('deptrac', 'ruleset') as $layer => $visible) {
            if (!\is_string($layer) || !\is_array($visible) || [] !== array_filter($visible, static fn (mixed $name): bool => !\is_string($name))) {
                throw new InvalidManifest(\sprintf('deptrac.ruleset of %s must map layer names to lists of layer names.', $package->name));
            }

            /** @var list<string> $visible */
            $rules[$layer] = $visible;
        }

        $integrating = [];

        foreach ($this->layersOf($package) as $layer) {
            if (LayerKind::Integration === $layer->kind || (LayerKind::Bundle === $layer->kind && [] !== $integrated)) {
                $integrating[] = $layer->name;
            }
        }

        $others = array_values(array_diff(array_keys($suggested), $integrated));

        foreach ($rules as $layer => $visible) {
            if (\in_array(self::FORBIDDEN, $visible, true)) {
                $rules[$layer] = [...$visible, ...$others, ...\in_array($layer, $integrating, true) ? $integrated : []];
            }
        }

        return $rules;
    }

    /**
     * The layers only the integration and the bundle class of a package see: those of the known packages it suggests
     * without requiring them, and its `Suggested…` layers.
     *
     * @param list<string> $covering
     *
     * @return list<string>
     */
    private function integratedOf(Package $package, array $covering): array
    {
        if (!$this->integrations->integrates($package)) {
            return [];
        }

        $names = [];

        foreach ($package->suggests as $name) {
            $dependency = $this->workspace->byName($name);

            if ($dependency instanceof Package && !\in_array($name, $package->requires, true)) {
                foreach ($this->layersOf($dependency) as $layer) {
                    if ($layer->seenByOthers()) {
                        $names[] = $layer->name;
                    }
                }
            }
        }

        return [...$names, ...array_keys($this->integrations->suggested($package, $covering))];
    }

    /**
     * @param list<string> $allowed    the layers of the dependencies the package is allowed besides the default
     * @param list<string> $integrated the layers only its integration and its bundle class see
     *
     * @return array<string, list<string>>
     */
    private function defaultRulesOf(Package $package, array $allowed, array $integrated): array
    {
        $required = $this->layersOfDependencies($package->requires);
        $everything = array_values(array_diff(
            $this->names(array_filter($this->layersOfDependencies([...$package->requires, ...$package->devRequires]), static fn (Layer $layer): bool => $layer->seenByOthers())),
            $integrated,
        ));
        $libraries = $this->names(array_filter($required, static fn (Layer $layer): bool => LayerKind::Library === $layer->kind));
        $domains = $this->names(array_filter($required, static fn (Layer $layer): bool => $layer->seenByDomains()));
        $services = $this->names(array_filter($required, static fn (Layer $layer): bool => $layer->seenByApplications()));
        $own = [];
        $rules = [];

        foreach ($this->layersOf($package) as $layer) {
            $own[$layer->kind->name][] = $layer->name;
        }

        foreach ($this->layersOf($package) as $layer) {
            $rules[$layer->name] = match ($layer->kind) {
                LayerKind::Library => [self::DEFAULT, ...$allowed, ...$libraries],
                LayerKind::Domain => [self::DEFAULT, ...$allowed, ...$domains],
                LayerKind::Application => [...$own['Domain'] ?? [], self::DEFAULT, ...$allowed, self::FORBIDDEN, ...$services],
                LayerKind::Infrastructure => [...$own['Domain'] ?? [], ...$own['Application'] ?? [], self::DEFAULT, ...$allowed, self::FORBIDDEN, ...$everything],
                LayerKind::Integration => [...$own['Domain'] ?? [], ...$own['Application'] ?? [], ...$own['Infrastructure'] ?? [], self::DEFAULT, ...$allowed, self::FORBIDDEN, ...$everything],
                LayerKind::Bundle => [...$own['Domain'] ?? [], ...$own['Application'] ?? [], ...$own['Infrastructure'] ?? [], ...$own['Integration'] ?? [], self::DEFAULT, ...$allowed, self::FORBIDDEN, ...$everything],
            };
        }

        return $rules;
    }

    /**
     * @param array<Layer> $layers
     *
     * @return list<string>
     */
    private function names(array $layers): array
    {
        $names = [];

        foreach ($layers as $layer) {
            $names[] = $layer->name;
        }

        return $names;
    }

    /**
     * The layers of the known packages among those named.
     *
     * @param list<string> $names
     *
     * @return list<Layer>
     */
    private function layersOfDependencies(array $names): array
    {
        $layers = [];

        foreach ($names as $name) {
            $dependency = $this->workspace->byName($name);

            if ($dependency instanceof Package) {
                array_push($layers, ...$this->layersOf($dependency));
            }
        }

        return $layers;
    }
}
