<?php

declare(strict_types=1);

namespace Phil\DevTools\Skeleton;

use Phil\DevTools\Exception\InvalidPackageName;

/**
 * The name of a new package, `vendor/name`, and everything derived from it: its namespace, its bundle class, its
 * configuration alias and its directory in the monorepo.
 *
 * Only the two vendors whose wording the packages use are accepted: `phil` and `tilttek`.
 */
final readonly class PackageName
{
    /** @var array<string, string> */
    public const array VENDORS = ['phil' => 'Phil', 'tilttek' => 'Tilttek'];

    private function __construct(public string $vendor, public string $name, public bool $bundle)
    {
    }

    /**
     * A bundle's name ends with `-bundle`: `--bundle` adds it when it is missing, and a name that ends with it is a
     * bundle without the option.
     */
    public static function parse(string $package, bool $bundle = false): self
    {
        if (1 !== preg_match('#^([a-z0-9]+)/([a-z0-9]+(?:-[a-z0-9]+)*)$#', $package, $parts)) {
            throw new InvalidPackageName(\sprintf('"%s" is not a package name such as phil/example or tilttek/example-bundle.', $package));
        }

        if (!isset(self::VENDORS[$parts[1]])) {
            throw new InvalidPackageName(\sprintf('The vendor "%s" is not one of %s.', $parts[1], implode(', ', array_keys(self::VENDORS))));
        }

        $name = $parts[2];

        if ('bundle' === $name) {
            throw new InvalidPackageName('A bundle needs a name before "-bundle".');
        }

        $isBundle = $bundle || str_ends_with($name, '-bundle');

        if ($isBundle && !str_ends_with($name, '-bundle')) {
            $name .= '-bundle';
        }

        return new self($parts[1], $name, $isBundle);
    }

    public function package(): string
    {
        return $this->vendor.'/'.$this->name;
    }

    /**
     * `Tilttek\QuestsBundle` for `tilttek/quests-bundle`.
     */
    public function namespace(): string
    {
        return self::VENDORS[$this->vendor].'\\'.$this->studly($this->name);
    }

    /**
     * `TilttekQuestsBundle` for `tilttek/quests-bundle`.
     */
    public function bundleClass(): string
    {
        return self::VENDORS[$this->vendor].$this->studly($this->base()).'Bundle';
    }

    /**
     * `tilttek_quests` for `tilttek/quests-bundle`.
     */
    public function alias(): string
    {
        return $this->vendor.'_'.str_replace('-', '_', $this->base());
    }

    /**
     * The library a bundle integrates, by convention: `tilttek/quests` for `tilttek/quests-bundle`.
     */
    public function library(): string
    {
        return $this->vendor.'/'.$this->base();
    }

    /**
     * The directory in a monorepo's `packages/`: the name for `phil`, prefixed with the vendor otherwise.
     */
    public function directory(): string
    {
        return 'phil' === $this->vendor ? $this->name : $this->vendor.'-'.$this->name;
    }

    private function base(): string
    {
        return $this->bundle ? substr($this->name, 0, -\strlen('-bundle')) : $this->name;
    }

    private function studly(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
    }
}
