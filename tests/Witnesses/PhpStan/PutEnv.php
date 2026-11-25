<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

/**
 * The environment of the process is never changed: it outlives the request in a worker.
 *
 * Expected identifier: `phil.processState`
 */
function putEnvWitness(string $value): void
{
    putenv('APP_FLAG='.$value);
}
