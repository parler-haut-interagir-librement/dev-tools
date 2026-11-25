<?php

declare(strict_types=1);

namespace Phil\DevTools\Fuzz\Regex;

use Random\Randomizer;

/**
 * A part of a regular expression the generator draws a string from.
 */
interface Node
{
    public function draw(Randomizer $randomizer): string;
}
