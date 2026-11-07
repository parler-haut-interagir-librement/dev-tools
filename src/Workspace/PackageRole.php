<?php

declare(strict_types=1);

namespace Phil\DevTools\Workspace;

/**
 * Where a package stands in a workspace.
 */
enum PackageRole: string
{
    /** The directory the tools run from: a package on its own, or the root of a monorepo. */
    case Root = 'root';

    /** A package of the monorepo, under one of the root's `packages` globs. */
    case Member = 'member';

    /** A package installed under `vendor/` that ships a `phil-dev.php`: known, never analysed. */
    case Installed = 'installed';
}
