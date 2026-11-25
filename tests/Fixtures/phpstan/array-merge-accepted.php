<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

/**
 * @param list<list<int>> $parts
 *
 * @return list<int>
 */
function acceptedMerges(array $parts): array
{
    $all = [];
    $collected = [];

    foreach ($parts as $part) {
        $collected[] = $part;
        $copy = array_merge($part, [1]);
        $all[] = $copy[0];
        $fn = static function (array $acc) use ($part): array {
            $acc = array_merge($acc, $part);

            return $acc;
        };
        unset($fn);
    }

    $all = array_merge($all, ...$collected);
    $all = array_merge($all, [2]);

    return $all;
}
