<?php

declare(strict_types=1);

namespace Phil\DevTools\Fuzz\Regex;

use Override;
use Random\Randomizer;

/**
 * A part repeated between two bounds, both included.
 */
final readonly class Repetition implements Node
{
    public function __construct(public Node $node, public int $min, public int $max)
    {
    }

    #[Override]
    public function draw(Randomizer $randomizer): string
    {
        $string = '';
        $times = $randomizer->getInt($this->min, $this->max);

        for ($i = 0; $i < $times; ++$i) {
            $string .= $this->node->draw($randomizer);
        }

        return $string;
    }
}
