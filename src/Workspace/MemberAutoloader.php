<?php

declare(strict_types=1);

namespace Phil\DevTools\Workspace;

use Closure;
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
 */
final readonly class MemberAutoloader
{
    /** The autoloader as registered, so that it can be unregistered: each `load(...)` is a new closure. */
    public Closure $loader;

    /**
     * @param array<string, list<string>> $prefixes namespace prefixes, with a trailing backslash, to absolute directories
     */
    private function __construct(private array $prefixes)
    {
        $this->loader = $this->load(...);
    }

    public static function register(string $root): self
    {
        $autoloader = self::forWorkspace(Workspace::load($root, []));
        spl_autoload_register($autoloader->loader);

        return $autoloader;
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
                            $prefixes[$namespace][] = $member->path($directory);
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
                $file = rtrim($directory, '/').'/'.str_replace('\\', '/', substr($class, \strlen($prefix))).'.php';

                if (is_file($file)) {
                    return $file;
                }
            }
        }

        return null;
    }

    public function unregister(): void
    {
        spl_autoload_unregister($this->loader);
    }

    public function load(string $class): void
    {
        $file = $this->find($class);

        if (null !== $file) {
            require_once $file;
        }
    }
}
