<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

final class PlainOrder
{
    public int $total = 0;

    public int $paid {
        get => $this->paid;
    }

    public function __construct()
    {
        $this->paid = 0;
    }
}

function acceptedChains(PlainOrder $order, int $amount): void
{
    $a = $b = 0;
    $c = $d = [];
    $first = new PlainOrder();
    $second = $first;
    $e = $order->total = $amount;
    $f = $g = $amount;
    $h = new \ArrayObject();
    unset($a, $b, $c, $d, $second, $e, $f, $g, $h);
}
