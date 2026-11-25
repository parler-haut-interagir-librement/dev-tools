<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

/**
 * The default time zone of the process is never changed: it outlives the request in a worker.
 *
 * Expected identifier: `phil.processState`
 */
function defaultTimezoneWitness(string $zone): void
{
    date_default_timezone_set($zone);
}
