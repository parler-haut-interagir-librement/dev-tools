<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

use ArrayObject;
use DateTimeImmutable;
use DateTimeZone;

/**
 * What the forbidden calls and the rules of this package accept: an address checked without parse_url() nor
 * FILTER_VALIDATE_URL, a time zone and a locale passed where they are used, a key derived per use, one assignment per
 * target, the parts of a loop merged once.
 *
 * Expected identifier: `none`
 *
 * @param list<list<int>> $parts
 *
 * @return list<int>
 */
function acceptedWitness(string $email, string $zone, string $secret, array $parts): array
{
    $valid = false !== filter_var($email, \FILTER_VALIDATE_EMAIL);
    $now = new DateTimeImmutable('now', new DateTimeZone($zone));
    $key = hash_hkdf('sha256', $secret, 32, 'phil_example.digest');
    $digest = hash_hmac('sha256', $now->format('c'), $key);
    $first = new ArrayObject();
    $second = new ArrayObject();
    $collected = [];

    foreach ($parts as $part) {
        $collected[] = $part;
    }

    unset($first, $second, $digest, $valid);

    return array_merge(...$collected);
}
