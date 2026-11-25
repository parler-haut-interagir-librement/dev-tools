<?php

declare(strict_types=1);

namespace Phil\DevTools\Conventions;

/**
 * A builder of a configuration tree open while its tokens are read, until its `->end()`.
 */
final class TreeFrame
{
    public bool $hasDefault = false;

    public ?string $default = null;

    public bool $required = false;

    /**
     * @param TreeFrameKind $kind what the builder is
     * @param string        $name the node's name, `*` when it is not a literal, empty for another builder
     */
    public function __construct(public readonly TreeFrameKind $kind, public readonly string $name = '')
    {
    }
}
