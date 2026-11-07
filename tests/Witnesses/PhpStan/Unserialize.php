<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

/**
 * Native deserialisation is refused everywhere.
 *
 * Expected identifier: `phil.unserialize`
 */
function unserialiseWitness(string $payload): mixed
{
    return unserialize($payload);
}
