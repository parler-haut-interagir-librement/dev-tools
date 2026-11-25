<?php

declare(strict_types=1);

namespace Phil\DevTools\Fuzz\Regex;

use Override;
use Random\Randomizer;

/**
 * One character, as written or escaped.
 */
final readonly class Literal implements Node
{
    public function __construct(public string $character)
    {
    }

    #[Override]
    public function draw(Randomizer $randomizer): string
    {
        return $this->character;
    }
}
