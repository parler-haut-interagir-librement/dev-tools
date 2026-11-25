<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

final class RefusedMerges
{
    /** @var list<int> */
    private array $items = [];

    /** @var list<int> */
    private static array $shared = [];

    /**
     * @param list<list<int>> $parts
     *
     * @return list<int>
     */
    public function merge(array $parts): array
    {
        $all = [];

        foreach ($parts as $part) {
            $all = array_merge($all, $part);

            if ([] !== $part) {
                $this->items = array_merge($this->items, $part);
            }
        }

        for ($i = 0; $i < 3; ++$i) {
            self::$shared = array_merge_recursive(self::$shared, [$i]);

            foreach ($parts as $part) {
                $all = array_merge($part, $all);
            }
        }

        $n = 0;

        while ($n < 2) {
            $all = ARRAY_MERGE($all, [$n++]);
        }

        do {
            $all = array_merge($all, []);
        } while (false);

        return $all;
    }

    /**
     * @param list<list<int>> $parts
     *
     * @return list<int>
     */
    public function more(array $parts): array
    {
        $all = [];

        foreach ($parts as $part) {
            $count = 5;
            $all = array_merge($all, $all);
        }

        return $all;
    }
}
