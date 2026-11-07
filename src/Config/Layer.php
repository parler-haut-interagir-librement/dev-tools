<?php

declare(strict_types=1);

namespace Phil\DevTools\Config;

/**
 * One Deptrac layer of a package: its name, the regular expressions of its classes, and its kind, which decides what
 * the layers of the packages that require it may see of it.
 */
final readonly class Layer
{
    /**
     * @param list<string> $regexes
     */
    public function __construct(public string $name, public array $regexes, public LayerKind $kind)
    {
    }

    /**
     * Whether a domain that requires this layer's package may see it: a library or another domain.
     */
    public function seenByDomains(): bool
    {
        return LayerKind::Library === $this->kind || LayerKind::Domain === $this->kind;
    }

    /**
     * Whether an application layer that requires this layer's package may see it: anything but an infrastructure.
     */
    public function seenByApplications(): bool
    {
        return LayerKind::Infrastructure !== $this->kind;
    }
}
