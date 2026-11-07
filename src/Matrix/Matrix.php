<?php

declare(strict_types=1);

namespace Phil\DevTools\Matrix;

use JsonException;
use Phil\DevTools\DevTools;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Files;

/**
 * The versions of PHP and Symfony every package is tested on, from `config/matrix.json`: always the floor the packages
 * enforce, and the next version from its first release candidate. No script names a version; each reads them here.
 */
final readonly class Matrix
{
    /** @var list<string> */
    public const array PRODUCTS = ['php', 'symfony'];

    /**
     * @param array<string, list<Version>> $versions
     */
    private function __construct(private array $versions)
    {
    }

    public static function load(?string $file = null): self
    {
        $file ??= DevTools::directory().'/config/matrix.json';

        try {
            $data = json_decode(Files::read($file), true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InvalidManifest(\sprintf('%s is not valid JSON: %s', $file, $jsonException->getMessage()), $jsonException->getCode(), previous: $jsonException);
        }

        $versions = [];

        foreach (self::PRODUCTS as $product) {
            $entries = \is_array($data) ? ($data[$product] ?? null) : null;

            if (!\is_array($entries)) {
                throw new InvalidManifest(\sprintf('%s lists no %s versions.', $file, $product));
            }

            $versions[$product] = [];

            foreach ($entries as $entry) {
                $versions[$product][] = self::version($file, $product, $entry);
            }

            $statuses = array_map(static fn (Version $version): VersionStatus => $version->status, $versions[$product]);

            if (1 !== \count(array_keys($statuses, VersionStatus::Floor, true)) || \count(array_keys($statuses, VersionStatus::Next, true)) > 1) {
                throw new InvalidManifest(\sprintf('%s must list one floor %s version, and at most one next.', $file, $product));
            }
        }

        return new self($versions);
    }

    /**
     * @return list<Version>
     */
    public function versions(string $product): array
    {
        return $this->versions[$product] ?? [];
    }

    /**
     * A version by its number, or by its status: "floor" or "next".
     */
    public function find(string $product, string $name): Version
    {
        foreach ($this->versions($product) as $version) {
            if ($version->version === $name || $version->status->value === $name) {
                return $version;
            }
        }

        throw new InvalidManifest(\sprintf('The matrix has no %s version "%s".', $product, $name));
    }

    public function floor(string $product): Version
    {
        return $this->find($product, VersionStatus::Floor->value);
    }

    /**
     * The range PHPStan analyses for: from the floor to the last release of the newest version.
     *
     * @return array{min: int, max: int}
     */
    public function phpVersionRange(): array
    {
        $floor = $this->floor('php')->id();
        $newest = $floor;

        foreach ($this->versions('php') as $version) {
            $newest = max($newest, $version->id());
        }

        return ['min' => $floor, 'max' => $newest + 99];
    }

    private static function version(string $file, string $product, mixed $entry): Version
    {
        $version = \is_array($entry) ? ($entry['version'] ?? null) : null;
        $status = \is_array($entry) && \is_string($entry['status'] ?? null) ? VersionStatus::tryFrom($entry['status']) : null;
        $image = \is_array($entry) && \is_string($entry['image'] ?? null) ? $entry['image'] : null;

        if (!\is_string($version) || 1 !== preg_match('/^\d+\.\d+$/', $version) || null === $status || ('php' === $product) !== (null !== $image)) {
            throw new InvalidManifest(\sprintf('%s: each %s version has a "version" such as 8.5 and a "status", floor or next%s.', $file, $product, 'php' === $product ? ', and an "image"' : ''));
        }

        return new Version($version, $status, $image);
    }
}
