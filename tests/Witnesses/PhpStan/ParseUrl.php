<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

/**
 * An address is parsed by phil/url, never by `parse_url()`.
 *
 * Expected identifier: `phil.parseUrl`
 */
function parseUrlWitness(string $address): ?string
{
    $host = parse_url($address, \PHP_URL_HOST);

    return \is_string($host) ? $host : null;
}
