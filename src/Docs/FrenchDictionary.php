<?php

declare(strict_types=1);

namespace Phil\DevTools\Docs;

use Phil\DevTools\DevTools;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Files;

/**
 * The French dictionary of cspell, `@cspell/dict-fr-fr`: a development dependency of this package in its
 * `package.json`, found in the `node_modules` of the root, or installed on demand under `var/phil-dev/node/` of it.
 *
 * The configuration of cspell imports it by the absolute path of its `cspell-ext.json`, so that cspell, run from the
 * `PATH` or through npx, reads the same dictionary whatever directory it starts from.
 */
final readonly class FrenchDictionary
{
    public const string PACKAGE = '@cspell/dict-fr-fr';

    /** The file of the package cspell imports. */
    public const string EXTENSION = 'cspell-ext.json';

    /** Where the dictionary is installed on demand, relative to the root. */
    public const string DIRECTORY = 'var/phil-dev/node';

    /**
     * @param string $manifest the `package.json` that pins the version: this package's by default
     */
    public function __construct(private string $manifest = '')
    {
    }

    /**
     * The version this package pins in the `devDependencies` of its `package.json`, such as `2.3.2`.
     */
    public function version(): string
    {
        $manifest = '' === $this->manifest ? DevTools::directory().'/package.json' : $this->manifest;
        $data = json_decode(Files::read($manifest), true);
        $version = \is_array($data) && \is_array($data['devDependencies'] ?? null) ? $data['devDependencies'][self::PACKAGE] ?? null : null;

        if (!\is_string($version) || 1 !== preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new InvalidManifest(\sprintf('%s pins %s at an exact version in its devDependencies.', $manifest, self::PACKAGE));
        }

        return $version;
    }

    /**
     * The `cspell-ext.json` of the first directory whose `node_modules` holds the dictionary, or null.
     *
     * @param list<string> $directories the directories that may hold a `node_modules`, in the order they are looked in
     */
    public function find(array $directories): ?string
    {
        foreach ($directories as $directory) {
            $candidate = rtrim($directory, '/').'/node_modules/'.self::PACKAGE.'/'.self::EXTENSION;

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The npm command that installs the pinned version under `$directory`, without scripts, lock file or audit.
     *
     * @return non-empty-list<string>
     */
    public function installCommand(string $npm, string $directory): array
    {
        return [$npm, 'install', '--prefix', $directory, '--no-save', '--no-package-lock', '--ignore-scripts', '--no-audit', '--no-fund', self::PACKAGE.'@'.$this->version()];
    }
}
