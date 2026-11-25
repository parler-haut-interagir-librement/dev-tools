<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

abstract class RefusedBase
{
    protected static int $count = 0;

    private int $own = 0;

    private string $label = '';

    public function own(): int
    {
        return $this->own.$this->label === '' ? 0 : 1;
    }
}

abstract class RefusedMiddle extends RefusedBase
{
}

final class RefusedChild extends RefusedMiddle
{
    protected static int $count = 1;

    private int $own = 1;

    public function __construct(private string $label = '')
    {
    }

    public function child(): int
    {
        return $this->own.$this->label === '' ? 0 : 1;
    }
}

abstract class ShadowBase
{
    private int $x = 0;
}

abstract class ShadowMiddle extends ShadowBase
{
    protected int $x = 1;
}

final class ShadowChild extends ShadowMiddle
{
    protected int $x = 2;
}
