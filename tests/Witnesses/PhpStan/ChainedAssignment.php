<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

use ArrayObject;

/**
 * A chained assignment never shares a new object.
 *
 * Expected identifier: `phil.chainedAssignment`
 */
function chainedAssignmentWitness(): void
{
    $first = $second = new ArrayObject();
    unset($first, $second);
}
