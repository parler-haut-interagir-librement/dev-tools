<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

abstract class AcceptedBase
{
    protected string $name = '';

    public static int $count = 0;

    private int $own = 0;

    public function own(): int
    {
        return $this->own;
    }
}

final class AcceptedChild extends AcceptedBase
{
    protected string $name = 'child';

    private int $mine = 0;

    public function __construct(private readonly string $label = '')
    {
    }

    public function mine(): int
    {
        return $this->mine.$this->label === '' ? 0 : 1;
    }
}

final class Standalone
{
    private int $own = 0;

    public function own(): int
    {
        return $this->own;
    }
}
