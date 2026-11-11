<?php

declare(strict_types=1);

namespace Phil\DevTools\Workspace;

/**
 * Where a package stands, from the `status` of its `phil-dev.php`.
 *
 * - **Active**, the default: the package has its code, and every gate checks it.
 * - **Planned**: the package is being written: its skeleton, its documentation of what it will do and a smoke test.
 *   The gates check it like any other package, and leave it out when asked to (`--skip-planned`,
 *   `PHIL_DEV_SKIP_PLANNED=1`).
 */
enum PackageStatus: string
{
    case Active = 'active';
    case Planned = 'planned';
}
