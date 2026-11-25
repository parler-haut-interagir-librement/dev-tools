<?php

declare(strict_types=1);

namespace Phil\DevTools\Fuzz\Regex;

use Override;
use Random\Randomizer;

/**
 * Parts one after the other; none for an anchor.
 */
final readonly class Sequence implements Node
{
    /**
     * @param list<Node> $items
     */
    public function __construct(public array $items)
    {
    }

    #[Override]
    public function draw(Randomizer $randomizer): string
    {
        $string = '';

        foreach ($this->items as $item) {
            $string .= $item->draw($randomizer);
        }

        return $string;
    }
}
