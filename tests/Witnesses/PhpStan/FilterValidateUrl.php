<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

/**
 * An address is validated by phil/url, never by `FILTER_VALIDATE_URL`.
 *
 * Expected identifier: `phil.parseUrl`
 */
function filterValidateUrlWitness(string $address): bool
{
    return false !== filter_var($address, \FILTER_VALIDATE_URL);
}
