<?php

declare(strict_types=1);

namespace Phil\DevTools\Workspace;

use Phil\DevTools\Exception\InvalidManifest;

/**
 * The small configuration file every package keeps at its root, `phil-dev.php`: a PHP file that returns an array.
 *
 * It lists the package's paths and its exceptions to the shared configurations, nothing else: what every package
 * shares lives in the configurations of this package. A monorepo root keeps one too, which also names the globs of its
 * packages under `packages`.
 *
 * ```php
 * return [
 *     'status' => 'planned',
 *     'paths' => ['src', 'tests'],
 *     'phpstan' => ['ignoreErrors' => [['identifier' => 'argument.type', 'path' => 'src/Legacy.php']]],
 *     'infection' => ['mutators' => ['IncrementInteger' => ['ignoreSourceCodeByRegex' => ['private const int MAX_.*']]]],
 *     'deptrac' => ['allow' => ['^League\\\\Uri\\\\.*']],
 * ];
 * ```
 */
final readonly class Manifest
{
    public const string FILE = 'phil-dev.php';

    /** @var list<string> */
    public const array SECTIONS = [
        'paths', 'packages', 'phpstan', 'rector', 'cs', 'infection', 'deptrac', 'arkitect', 'docs', 'phpunit',
        'gates', 'psalm', 'fuzz', 'bench', 'bc', 'split', 'twig', 'js', 'unused', 'hotspots', 'status', 'apps',
    ];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(public array $data = [])
    {
        foreach (array_keys($data) as $key) {
            if (!\in_array($key, self::SECTIONS, true)) {
                throw new InvalidManifest(\sprintf('Unknown section "%s"; the sections are %s.', $key, implode(', ', self::SECTIONS)));
            }
        }
    }

    public static function load(string $file): self
    {
        if (!is_file($file)) {
            return new self();
        }

        $data = (static fn (string $path): mixed => require $path)($file);

        if (!\is_array($data)) {
            throw new InvalidManifest(\sprintf('%s must return an array.', $file));
        }

        $sections = [];

        foreach ($data as $key => $value) {
            if (!\is_string($key)) {
                throw new InvalidManifest(\sprintf('%s must return an array keyed by section.', $file));
            }

            $sections[$key] = $value;
        }

        return new self($sections);
    }

    /**
     * A section, or one value inside it: `get('phpstan', 'ignoreErrors')`.
     */
    public function get(string $section, ?string $key = null): mixed
    {
        $value = $this->data[$section] ?? null;

        if (null === $key) {
            return $value;
        }

        return \is_array($value) ? ($value[$key] ?? null) : null;
    }

    /**
     * A list of strings, or the default when the value is absent.
     *
     * @param list<string> $default
     *
     * @return list<string>
     */
    public function strings(string $section, ?string $key = null, array $default = []): array
    {
        $value = $this->get($section, $key);

        if (null === $value) {
            return $default;
        }

        if (!\is_array($value)) {
            throw new InvalidManifest(\sprintf('"%s" must be a list of strings.', null === $key ? $section : $section.'.'.$key));
        }

        $strings = [];

        foreach ($value as $item) {
            if (!\is_string($item)) {
                throw new InvalidManifest(\sprintf('"%s" must be a list of strings.', null === $key ? $section : $section.'.'.$key));
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * An array value, or an empty array when the value is absent.
     *
     * @return array<array-key, mixed>
     */
    public function map(string $section, ?string $key = null): array
    {
        $value = $this->get($section, $key);

        if (null === $value) {
            return [];
        }

        if (!\is_array($value)) {
            throw new InvalidManifest(\sprintf('"%s" must be an array.', null === $key ? $section : $section.'.'.$key));
        }

        return $value;
    }

    public function flag(string $section, string $key, bool $default = false): bool
    {
        $value = $this->get($section, $key);

        if (null === $value) {
            return $default;
        }

        if (!\is_bool($value)) {
            throw new InvalidManifest(\sprintf('"%s.%s" must be a boolean.', $section, $key));
        }

        return $value;
    }

    /**
     * Where the package stands: `status`, `active` when it is absent.
     */
    public function status(): PackageStatus
    {
        $value = $this->get('status');

        if (null === $value) {
            return PackageStatus::Active;
        }

        if (!\is_string($value)) {
            throw new InvalidManifest('"status" is "active" or "planned".');
        }

        return PackageStatus::tryFrom($value) ?? throw new InvalidManifest(\sprintf('"status" is "active" or "planned", not "%s".', $value));
    }
}
