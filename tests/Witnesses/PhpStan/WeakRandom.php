<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

/**
 * A predictable random number is refused.
 *
 * Expected identifier: `phil.weakRandom`
 */
function weakRandomWitness(): int
{
    return mt_rand();
}
