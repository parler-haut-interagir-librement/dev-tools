<?php

declare(strict_types=1);

namespace Phil\ExampleBundle;

final class Plain
{
    public function scalarNode(string $name): string
    {
        return $name;
    }
}
