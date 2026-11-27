<?php

declare(strict_types=1);

namespace Phil\DevTools\Conventions;

use Phil\DevTools\Workspace\Package;

/**
 * Finds the Doctrine mappings a package ships as XML or YAML files: entities and embeddable classes are mapped by
 * PHP attributes (`#[ORM\Entity]`, `#[ORM\Embeddable]`…), which sit beside the code they describe and need no loader.
 */
final class DoctrineMapping
{
    /**
     * The endings of the mapping files of the ORM's XML and YAML drivers, and of its older `.dcm.xml` naming.
     *
     * @var list<string>
     */
    public const array SUFFIXES = ['.orm.xml', '.orm.yml', '.orm.yaml', '.dcm.xml'];

    /**
     * The mapping files of a package, relative to it, sorted; `vendor/`, `var/` and dot directories left out.
     *
     * @return list<string>
     */
    public function files(Package $package): array
    {
        return array_values(array_filter($package->filesUnder(''), self::isMapping(...)));
    }

    public static function isMapping(string $file): bool
    {
        $name = strtolower(basename($file));

        return array_any(self::SUFFIXES, static fn (string $suffix): bool => str_ends_with($name, $suffix) && $name !== $suffix);
    }
}
