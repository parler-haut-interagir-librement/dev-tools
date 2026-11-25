<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

abstract class PropertyRedeclarationParent
{
    private int $count = 0;

    public function count(): int
    {
        return $this->count;
    }
}

/**
 * A private property of a parent is never declared again.
 *
 * Expected identifier: `phil.propertyRedeclaration`
 */
final class PropertyRedeclaration extends PropertyRedeclarationParent
{
    private int $count = 1;

    public function own(): int
    {
        return $this->count;
    }
}
