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

final class AcceptedOddTargets
{
    /** @var list<int> */
    public array $list = [];

    /** @var list<int> */
    public static array $shared = [];
}

/**
 * Targets and calls the rule leaves alone: an element of an array, a property of an element, a property of a class
 * named by a variable, a call through a variable, a first-class callable and a constant that is named as a function.
 *
 * @param list<list<int>>                     $parts
 * @param array<int, AcceptedOddTargets>      $items
 * @param array<int, list<int>>               $table
 * @param class-string<AcceptedOddTargets>    $class
 * @param callable(list<int>, list<int>): list<int> $fn
 * @param non-empty-string                         $dynamic
 */
function acceptedOddMerges(array $parts, array $items, array $table, string $class, callable $fn, string $dynamic): void
{
    $all = [];

    foreach ($parts as $part) {
        $table[0] = array_merge($table[0], $part);
        $items[0]->list = array_merge($items[0]->list, $part);
        $class::$shared = array_merge($class::$shared, $part);
        $all = $fn($all, $part);
        $$dynamic = array_merge($$dynamic, $part);
        $all = array_merge(...);
        $all = ARRAY_MERGE;
    }
}
