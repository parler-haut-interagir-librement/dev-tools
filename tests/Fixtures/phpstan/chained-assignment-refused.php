<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

final class HookedOrder
{
    public int $total = 0 {
        set => max(0, $value);
    }
}

function refusedChains(HookedOrder $order, ?HookedOrder $maybe, int $amount): void
{
    $a = $b = new \ArrayObject();
    $c = $d = $e = new \ArrayObject();
    $f = $order->total = $amount;
    $g = $maybe->total = $amount;
    $h = $i = $order->total = $amount;
    $j = $k = $l = $m = new \ArrayObject();
    $n = $o = $p = $order->total = $amount;
    $q = $order->total = $r = $amount;
    unset($a, $b, $c, $d, $e, $f, $g, $h, $i, $j, $k, $l, $m, $n, $o, $p, $q, $r);
}
