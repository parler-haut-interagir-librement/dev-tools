<?php

declare(strict_types=1);

namespace Phil\DevTools\Fuzz\Regex;

use Override;
use Random\Randomizer;

/**
 * `a|b|c`: one of its branches.
 */
final readonly class Alternatives implements Node
{
    /**
     * @param non-empty-list<Node> $branches
     */
    public function __construct(public array $branches)
    {
    }

    #[Override]
    public function draw(Randomizer $randomizer): string
    {
        return $this->branches[$randomizer->getInt(0, \count($this->branches) - 1)]->draw($randomizer);
    }
}
