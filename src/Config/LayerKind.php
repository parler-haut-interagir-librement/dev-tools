<?php

declare(strict_types=1);

namespace Phil\DevTools\Config;

/**
 * What a Deptrac layer holds.
 */
enum LayerKind
{
    /** A library, or a layer a manifest declares. */
    case Library;

    case Domain;

    case Application;

    case Infrastructure;

    /** The classes at the root of a bundle's namespace: the bundle class. */
    case Bundle;
}
