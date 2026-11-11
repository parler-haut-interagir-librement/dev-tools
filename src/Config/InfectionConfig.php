<?php

declare(strict_types=1);

namespace Phil\DevTools\Config;

use JsonException;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Workspace\Workspace;

/**
 * The Infection configuration of a workspace, generated because Infection reads one file and has no includes.
 *
 * The source directories are those of every package analysed, or the manifest's `infection.directories`; each
 * manifest adds its `infection.excludes` and its `infection.mutators`, the named exceptions of a package, merged with
 * the others. The bar is one hundred: a surviving mutant is a missing test or code nothing can reach, and an exception
 * is written by name in a manifest, never by lowering the bar.
 */
final readonly class InfectionConfig
{
    public const int MINIMUM_MSI = 100;

    /**
     * @param string|null $phpUnitDirectory the directory of the PHPUnit configuration Infection reads; the root's by default
     */
    public function __construct(private Workspace $workspace, private ?string $phpUnitDirectory = null)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $root = $this->workspace->root;
        $directories = [];
        $excludes = [];
        $mutators = ['@default' => true];

        foreach ($this->workspace->analysed() as $package) {
            $declared = $package->manifest->strings('infection', 'directories');

            foreach ([] === $declared ? $package->sourceDirectories() : array_map($package->path(...), $declared) as $directory) {
                $directories[] = $directory;
            }

            array_push($excludes, ...$package->manifest->strings('infection', 'excludes'));
            $mutators = self::merge($mutators, $package->manifest->map('infection', 'mutators'));
        }

        $options = $root->manifest->get('infection', 'testFrameworkOptions');
        $schema = $root->path('vendor-bin/infection/vendor/infection/infection/resources/schema.json');

        return array_filter([
            '$schema' => is_file($schema) ? $schema : null,
            'source' => array_filter([
                'directories' => array_values(array_unique($directories)),
                'excludes' => array_values(array_unique($excludes)),
            ], static fn (array $value): bool => [] !== $value),
            'phpUnit' => ['configDir' => $this->phpUnitDirectory ?? $root->dir],
            'testFrameworkOptions' => \is_string($options) ? $options : '--testsuite=Unit',
            'minMsi' => self::MINIMUM_MSI,
            'minCoveredMsi' => self::MINIMUM_MSI,
            'logs' => [
                'text' => $root->path('var/infection/infection.log'),
                'summary' => $root->path('var/infection/summary.log'),
                'html' => $root->path('var/infection/infection.html'),
            ],
            'tmpDir' => $root->path('var/infection/tmp'),
            'mutators' => $mutators,
        ], static fn (mixed $value): bool => null !== $value);
    }

    public function json(): string
    {
        try {
            return json_encode($this->build(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
        } catch (JsonException $jsonException) {
            throw new InvalidManifest('The infection sections cannot be written as JSON: '.$jsonException->getMessage(), $jsonException->getCode(), previous: $jsonException);
        }
    }

    /**
     * Maps merged key by key, lists concatenated without duplicates, scalars replaced.
     *
     * @param array<array-key, mixed> $into
     * @param array<array-key, mixed> $from
     *
     * @return array<array-key, mixed>
     */
    public static function merge(array $into, array $from): array
    {
        if (array_is_list($into) && array_is_list($from)) {
            return array_values(array_unique([...$into, ...$from], \SORT_REGULAR));
        }

        foreach ($from as $key => $value) {
            $existing = $into[$key] ?? null;
            $into[$key] = \is_array($existing) && \is_array($value) ? self::merge($existing, $value) : $value;
        }

        return $into;
    }
}
