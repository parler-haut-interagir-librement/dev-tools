<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

/**
 * The locale of the process is never changed: it outlives the request in a worker.
 *
 * Expected identifier: `phil.processState`
 */
function setLocaleWitness(string $locale): void
{
    setlocale(\LC_ALL, $locale);
}
