<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

/**
 * An array is never merged into itself in a loop.
 *
 * Expected identifier: `phil.arrayMergeInLoop`
 *
 * @param list<list<int>> $parts
 *
 * @return list<int>
 */
function arrayMergeInLoopWitness(array $parts): array
{
    $all = [];

    foreach ($parts as $part) {
        $all = array_merge($all, $part);
    }

    return $all;
}
