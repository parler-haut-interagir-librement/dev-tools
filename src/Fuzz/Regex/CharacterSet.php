<?php

declare(strict_types=1);

namespace Phil\DevTools\Fuzz\Regex;

use Override;
use Phil\DevTools\Exception\UnsupportedPattern;
use Random\Randomizer;

/**
 * A class of characters, as ranges of code points: one range drawn, then one code point in it.
 */
final readonly class CharacterSet implements Node
{
    /**
     * @param list<array{int, int}> $ranges
     * @param bool                  $unicode whether the code points are written as UTF-8, else as bytes
     */
    public function __construct(public array $ranges, public bool $unicode)
    {
    }

    #[Override]
    public function draw(Randomizer $randomizer): string
    {
        if ([] === $this->ranges) {
            throw new UnsupportedPattern('A character class that matches no printable character.');
        }

        [$low, $high] = $this->ranges[$randomizer->getInt(0, \count($this->ranges) - 1)];
        $code = $randomizer->getInt($low, $high);

        // A surrogate is no character: the range gives the character just below or just above them instead. PCRE
        // refuses a range made of surrogates alone.
        if ($code >= 0xD800 && $code <= 0xDFFF) {
            $code = $low < 0xD800 ? 0xD7FF : 0xE000;
        }

        return self::character($code, $this->unicode);
    }

    /**
     * A code point as UTF-8, or as a byte without the `u` modifier.
     */
    public static function character(int $code, bool $unicode): string
    {
        if ($unicode) {
            return mb_chr($code, 'UTF-8');
        }

        if ($code < 0 || $code > 0xFF) {
            throw new UnsupportedPattern(\sprintf('The character U+%04X needs the u modifier.', $code));
        }

        return \chr($code);
    }
}
