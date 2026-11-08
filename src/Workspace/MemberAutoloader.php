<?php

declare(strict_types=1);

namespace Phil\DevTools\Workspace;

use Closure;
use Composer\Autoload\ClassLoader;
use Phil\DevTools\Files;

/**
 * Loads the classes of every member of a monorepo, production and tests, from the PSR-4 namespaces each member's own
 * `composer.json` declares — so that the root's `composer.json` does not list them, and adding a package touches no
 * file of the root.
 *
 * The root registers it once, among the files of its `autoload-dev`:
 *
 * ```json
 * "autoload-dev": {"files": ["vendor/phil/dev-tools/resources/autoload-members.php"]}
 * ```
 *
 * The prefixes go to Composer's `ClassLoader` of the root, as if the root's `composer.json` listed them: the tools that
 * ask it where a class lives — `findFile()`, `getPrefixesPsr4()` — find the members' classes too. Without a Composer
 * loader, a loader of its own is registered instead.
 */
final readonly class MemberAutoloader
{
    /** The autoloader of its own, so that it can be unregistered: each `load(...)` is a new closure. */
    public Closure $loader;

    /**
     * @param array<string, list<string>> $prefixes namespace prefixes, with a trailing backslash, to absolute directories without a trailing slash
     */
    private function __construct(public array $prefixes, private ?ClassLoader $classLoader = null)
    {
        $this->loader = $this->load(...);
    }

    /**
     * Adds the members' prefixes to the Composer loader of `$root/vendor` — or to the one given, else the first one
     * registered — or registers a loader of its own when Composer has none.
     */
    public static function register(string $root, ?ClassLoader $classLoader = null): self
    {
        $prefixes = self::forWorkspace(Workspace::load($root, []))->prefixes;
        $registered = ClassLoader::getRegisteredLoaders();
        $classLoader ??= $registered[$root.'/vendor'] ?? reset($registered);

        if (!$classLoader instanceof ClassLoader) {
            $autoloader = new self($prefixes);
            spl_autoload_register($autoloader->loader);

            return $autoloader;
        }

        foreach ($prefixes as $prefix => $directories) {
            $classLoader->addPsr4($prefix, $directories);
        }

        return new self($prefixes, $classLoader);
    }

    public static function forWorkspace(Workspace $workspace): self
    {
        $prefixes = [];

        foreach ($workspace->members as $member) {
            $composer = json_decode(Files::read($member->path('composer.json')), true);
            $composer = \is_array($composer) ? $composer : [];

            foreach (['autoload', 'autoload-dev'] as $section) {
                $autoload = $composer[$section] ?? null;
                $psr4 = \is_array($autoload) && \is_array($autoload['psr-4'] ?? null) ? $autoload['psr-4'] : [];

                foreach ($psr4 as $namespace => $directories) {
                    foreach ((array) $directories as $directory) {
                        if (\is_string($namespace) && \is_string($directory)) {
                            $prefixes[$namespace][] = rtrim($member->path($directory), '/');
                        }
                    }
                }
            }
        }

        return new self($prefixes);
    }

    /**
     * The file a class would be loaded from, when one of the members declares its namespace and the file exists.
     */
    public function find(string $class): ?string
    {
        foreach ($this->prefixes as $prefix => $directories) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            foreach ($directories as $directory) {
                $file = $directory.'/'.str_replace('\\', '/', substr($class, \strlen($prefix))).'.php';

                if (is_file($file)) {
                    return $file;
                }
            }
        }

        return null;
    }

    /**
     * Takes the members' directories back out of the Composer loader they were added to, or unregisters the loader of
     * its own.
     */
    public function unregister(): void
    {
        if (!$this->classLoader instanceof ClassLoader) {
            spl_autoload_unregister($this->loader);

            return;
        }

        $current = $this->classLoader->getPrefixesPsr4();

        foreach ($this->prefixes as $prefix => $directories) {
            $this->classLoader->setPsr4($prefix, array_values(array_diff($current[$prefix] ?? [], $directories)));
        }
    }

    public function load(string $class): void
    {
        $file = $this->find($class);

        if (null !== $file) {
            require_once $file;
        }
    }
}
