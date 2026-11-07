<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

/**
 * A dump left in production code is refused.
 *
 * Expected identifier: `phil.dump`
 */
function dumpWitness(mixed $value): void
{
    var_dump($value);
}
