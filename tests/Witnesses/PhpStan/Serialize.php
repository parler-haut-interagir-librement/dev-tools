<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

/**
 * Native serialisation is refused outside the test suites.
 *
 * Expected identifier: `phil.serialize`
 */
function serialiseWitness(mixed $value): string
{
    return serialize($value);
}
