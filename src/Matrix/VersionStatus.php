<?php

declare(strict_types=1);

namespace Phil\DevTools\Matrix;

/**
 * Where a version stands in the matrix.
 */
enum VersionStatus: string
{
    /** The version the packages require: `>=8.5`, `^8.2`. */
    case Floor = 'floor';

    /** The version they already support, from its first release candidate, before it becomes the floor. */
    case Next = 'next';
}
