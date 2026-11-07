<?php

declare(strict_types=1);

namespace Phil\DevTools\Workspace;

use Phil\DevTools\Exception\WorkspaceNotFound;

/**
 * The packages the tools see from a root directory.
 *
 * - The **root** is the directory the tools run from: a package on its own, or a monorepo.
 * - The **members** are the packages under the globs of the root's `phil-dev.php` (`packages`, by default
 *   `packages/*`): each is found through its own `composer.json` and `phil-dev.php`, so adding a package adds a
 *   directory and touches no file of the root.
 * - The **installed** packages are those under `vendor/` that ship a `phil-dev.php`: the generated configurations know
 *   their namespaces and layers, and never analyse them.
 *
 * `PHIL_DEV_PACKAGES` — a comma-separated list of directory names or Composer names — narrows what is analysed to
 * those members, so that several batches can each run the tools on their own packages at the same time. Every package
 * stays known, so the generated rules are the same.
 */
final readonly class Workspace
{
    public const string PACKAGES_VARIABLE = 'PHIL_DEV_PACKAGES';

    /**
     * @param list<Package>     $members
     * @param list<Package>     $installed
     * @param list<string>|null $only      the members analysed, by directory name or Composer name; null for all
     */
    private function __construct(
        public Package $root,
        public array $members,
        public array $installed,
        private ?array $only,
    ) {
    }

    /**
     * @param list<string>|null $only the members to analyse; null reads `PHIL_DEV_PACKAGES`
     */
    public static function load(string $root, ?array $only = null): self
    {
        $real = realpath($root);

        if (false === $real) {
            throw new WorkspaceNotFound(\sprintf('%s holds no composer.json.', $root));
        }

        $rootPackage = Package::fromDirectory($real, '', PackageRole::Root);
        $members = [];

        foreach (self::globs($rootPackage) as $glob) {
            $found = glob($real.'/'.$glob, \GLOB_ONLYDIR);

            foreach (false === $found ? [] : $found as $dir) {
                if (is_file($dir.'/composer.json')) {
                    $members[] = Package::fromDirectory($dir, substr($dir, \strlen($real) + 1), PackageRole::Member);
                }
            }
        }

        usort($members, static fn (Package $a, Package $b): int => strcmp($a->relative, $b->relative));

        $names = array_map(static fn (Package $package): string => $package->name, [$rootPackage, ...$members]);
        $installed = [];
        $manifests = glob($real.'/vendor/*/*/'.Manifest::FILE);

        foreach (false === $manifests ? [] : $manifests as $manifest) {
            $package = Package::fromDirectory(\dirname($manifest), substr(\dirname($manifest), \strlen($real) + 1), PackageRole::Installed);

            if (!\in_array($package->name, $names, true)) {
                $installed[] = $package;
            }
        }

        return new self($rootPackage, $members, $installed, $only ?? self::fromEnvironment());
    }

    public function isMonorepo(): bool
    {
        return [] !== $this->members;
    }

    /**
     * The packages whose paths the tools read: the root and every member, or the members `PHIL_DEV_PACKAGES` names.
     *
     * @return list<Package>
     */
    public function analysed(): array
    {
        return array_values(array_filter(
            [$this->root, ...$this->members],
            $this->selected(...),
        ));
    }

    /**
     * Every package the generated configurations know: the root, the members and the installed packages.
     *
     * @return list<Package>
     */
    public function known(): array
    {
        return [$this->root, ...$this->members, ...$this->installed];
    }

    /**
     * The members a command works on: those named, else those `PHIL_DEV_PACKAGES` names, else all of them — or the root
     * alone when it is a package on its own.
     *
     * @param list<string> $names
     *
     * @return list<Package>
     */
    public function targets(array $names = []): array
    {
        if (!$this->isMonorepo()) {
            return [$this->root];
        }

        if ([] === $names) {
            return array_values(array_filter($this->members, $this->selected(...)));
        }

        $targets = [];

        foreach ($names as $name) {
            $package = $this->member($name);

            if (!$package instanceof Package) {
                throw new WorkspaceNotFound(\sprintf('No package "%s" in this monorepo.', $name));
            }

            $targets[] = $package;
        }

        return $targets;
    }

    public function member(string $name): ?Package
    {
        foreach ($this->members as $member) {
            if ($member->name === $name || basename($member->dir) === $name || $member->relative === $name) {
                return $member;
            }
        }

        return null;
    }

    public function byName(string $name): ?Package
    {
        foreach ($this->known() as $package) {
            if ($package->name === $name) {
                return $package;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function packageGlobs(): array
    {
        return self::globs($this->root);
    }

    private function selected(Package $package): bool
    {
        if (null === $this->only) {
            return true;
        }

        return array_any($this->only, static fn (string $name): bool => $package->name === $name || (!$package->isRoot() && (basename($package->dir) === $name || $package->relative === $name)));
    }

    /**
     * @return list<string>
     */
    private static function globs(Package $root): array
    {
        return $root->manifest->strings('packages', null, ['packages/*']);
    }

    /**
     * @return list<string>|null
     */
    private static function fromEnvironment(): ?array
    {
        $value = getenv(self::PACKAGES_VARIABLE);

        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        return array_map(trim(...), explode(',', $value));
    }
}
