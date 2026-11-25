<?php

declare(strict_types=1);

namespace Phil\DevTools\Fuzz\Regex;

use Phil\DevTools\Exception\UnsupportedPattern;

/**
 * The characters of a pattern: escapes, character classes, POSIX classes and Unicode properties, read into ranges of
 * code points. A negated class draws from printable ASCII, without both cases of a letter when the pattern ignores
 * case.
 */
final readonly class ClassParser
{
    public const int FIRST = 0x20;

    public const int LAST = 0x7E;

    /**
     * The Unicode properties read, by name: ASCII, and beyond it under `u` only.
     */
    private const array PROPERTIES = [
        'L' => [[0x41, 0x5A], [0x61, 0x7A], [0xE0, 0xF6], [0x430, 0x44F]],
        'Lu' => [[0x41, 0x5A], [0xC0, 0xD6], [0x410, 0x42F]],
        'Ll' => [[0x61, 0x7A], [0xE0, 0xF6], [0x430, 0x44F]],
        'N' => [[0x30, 0x39], [0x660, 0x669]],
        'Nd' => [[0x30, 0x39], [0x660, 0x669]],
    ];

    /**
     * How far a POSIX class reaches: `:^xdigit:]`, the longest, and a margin that names what is refused.
     */
    private const int POSIX_LENGTH = 12;

    private const array POSIX = [
        'alpha' => [[0x41, 0x5A], [0x61, 0x7A]],
        'digit' => [[0x30, 0x39]],
        'alnum' => [[0x30, 0x39], [0x41, 0x5A], [0x61, 0x7A]],
        'upper' => [[0x41, 0x5A]],
        'lower' => [[0x61, 0x7A]],
        'space' => [[0x09, 0x0D], [0x20, 0x20]],
        'blank' => [[0x09, 0x09], [0x20, 0x20]],
        'xdigit' => [[0x30, 0x39], [0x41, 0x46], [0x61, 0x66]],
        'punct' => [[0x21, 0x2F], [0x3A, 0x40], [0x5B, 0x60], [0x7B, 0x7E]],
        'word' => [[0x30, 0x39], [0x41, 0x5A], [0x5F, 0x5F], [0x61, 0x7A]],
    ];

    private const array CLASS_ESCAPES = [
        'd' => [[0x30, 0x39]],
        'w' => [[0x30, 0x39], [0x41, 0x5A], [0x5F, 0x5F], [0x61, 0x7A]],
        's' => [[0x09, 0x0D], [0x20, 0x20]],
    ];

    private const array CHARACTER_ESCAPES = ['t' => 0x09, 'n' => 0x0A, 'r' => 0x0D, 'f' => 0x0C, 'v' => 0x0B, 'e' => 0x1B, 'a' => 0x07];

    public function __construct(private Cursor $cursor)
    {
    }

    /**
     * What follows a backslash outside a class: a class, an anchor (an empty sequence) or a character.
     */
    public function escape(): Node
    {
        $char = $this->cursor->next() ?? throw new UnsupportedPattern('A pattern ends with a backslash.');
        $ranges = $this->classEscape($char);

        if (null !== $ranges) {
            return new CharacterSet($ranges, $this->cursor->unicode);
        }

        if (\in_array($char, ['A', 'z', 'Z'], true)) {
            return new Sequence([]);
        }

        return new Literal(CharacterSet::character($this->escapedCharacter($char), $this->cursor->unicode));
    }

    /**
     * A class, after its opening bracket.
     */
    public function characterClass(): CharacterSet
    {
        $negated = '^' === $this->cursor->peek();

        if ($negated) {
            $this->cursor->skip();
        }

        $ranges = [];
        $first = true;

        while (']' !== ($char = $this->cursor->next() ?? throw new UnsupportedPattern('A character class is not closed.')) || $first) {
            $first = false;
            array_push($ranges, ...$this->member($char));
        }

        return new CharacterSet($negated ? $this->complement($ranges, $this->cursor->caseless) : $ranges, $this->cursor->unicode);
    }

    /**
     * Printable ASCII, for `.`.
     */
    public function any(): CharacterSet
    {
        return new CharacterSet([[self::FIRST, self::LAST]], $this->cursor->unicode);
    }

    /**
     * The ranges of one member of a class: a POSIX class, an escape, a character or a range.
     *
     * @return list<array{int, int}>
     */
    private function member(string $char): array
    {
        if ('[' === $char && ':' === $this->cursor->peek()) {
            return $this->posix();
        }

        $low = $this->single($char);

        if (\is_array($low)) {
            return $low;
        }

        if ('-' !== $this->cursor->peek() || ']' === $this->cursor->peek(1)) {
            return [[$low, $low]];
        }

        $this->cursor->skip();
        $high = $this->single($this->cursor->next() ?? throw new UnsupportedPattern('A character class is not closed.'));

        if (\is_array($high) || $high < $low) {
            throw new UnsupportedPattern('A range of a character class that ends on a class.');
        }

        return [[$low, $high]];
    }

    /**
     * The code point of a character of a class, or the ranges of an escaped class.
     *
     * @return int|list<array{int, int}>
     */
    private function single(string $char): int|array
    {
        if ('\\' !== $char) {
            return $this->cursor->code($char);
        }

        $escaped = $this->cursor->next() ?? throw new UnsupportedPattern('A character class ends with a backslash.');

        return $this->classEscape($escaped) ?? ('b' === $escaped ? 0x08 : $this->escapedCharacter($escaped));
    }

    /**
     * The ranges of `\d`, `\w`, `\s`, their negations and `\p{…}`, or null for another escape.
     *
     * @return list<array{int, int}>|null
     */
    private function classEscape(string $char): ?array
    {
        if (isset(self::CLASS_ESCAPES[$char])) {
            return self::CLASS_ESCAPES[$char];
        }

        if (isset(self::CLASS_ESCAPES[strtolower($char)])) {
            return $this->complement(self::CLASS_ESCAPES[strtolower($char)], $this->cursor->caseless);
        }

        return 'p' === $char ? $this->property() : null;
    }

    private function escapedCharacter(string $char): int
    {
        if (isset(self::CHARACTER_ESCAPES[$char])) {
            return self::CHARACTER_ESCAPES[$char];
        }

        if ('x' === $char) {
            return $this->hexadecimal();
        }

        if (ctype_alnum($char)) {
            throw new UnsupportedPattern(\sprintf('The escape "\\%s" (back-reference, word boundary, octal or other) is not supported.', $char));
        }

        return $this->cursor->code($char);
    }

    private function hexadecimal(): int
    {
        if ('{' === $this->cursor->peek()) {
            $this->cursor->skip();
            $digits = '';

            while (null !== ($char = $this->cursor->next()) && '}' !== $char) {
                $digits .= $char;
            }

            return (int) hexdec($digits);
        }

        $digits = '';

        while (\strlen($digits) < 2 && null !== ($char = $this->cursor->peek()) && ctype_xdigit($char)) {
            $digits .= $char;
            $this->cursor->skip();
        }

        return \intval($digits, 16);
    }

    /**
     * @return list<array{int, int}>
     */
    private function property(): array
    {
        $name = $this->cursor->next() ?? '';

        if ('{' === $name) {
            $name = '';

            while (null !== ($char = $this->cursor->next()) && '}' !== $char) {
                $name .= $char;
            }
        }

        $ranges = self::PROPERTIES[$name] ?? throw new UnsupportedPattern(\sprintf('The property \p{%s} is not supported: only %s.', $name, implode(', ', array_keys(self::PROPERTIES))));
        $kept = [];

        foreach ($ranges as $range) {
            if ($this->cursor->unicode || $range[1] <= self::LAST) {
                $kept[] = $range;
            }
        }

        return $kept;
    }

    /**
     * @return list<array{int, int}>
     */
    private function posix(): array
    {
        if (1 !== preg_match('/^:(\^?)([a-z]+):\]/', $this->cursor->ahead(self::POSIX_LENGTH), $match) || !isset(self::POSIX[$match[2]])) {
            throw new UnsupportedPattern(\sprintf('The POSIX class "[%s]" is not supported.', explode(']', $this->cursor->ahead(self::POSIX_LENGTH))[0]));
        }

        $this->cursor->skip(\strlen($match[0]));

        return '^' === $match[1] ? $this->complement(self::POSIX[$match[2]], $this->cursor->caseless) : self::POSIX[$match[2]];
    }

    /**
     * The printable ASCII characters outside the ranges, as ranges; outside both cases of a letter when asked.
     *
     * @param list<array{int, int}> $ranges
     *
     * @return list<array{int, int}>
     */
    private function complement(array $ranges, bool $caseless): array
    {
        $outside = [];

        for ($code = self::FIRST; $code <= self::LAST; ++$code) {
            $variants = $caseless && ctype_alpha(\chr($code)) ? [$code, $code ^ 0x20] : [$code];

            if (!$this->inside($variants, $ranges)) {
                $outside[] = [$code, $code];
            }
        }

        return $outside;
    }

    /**
     * @param list<int>             $codes
     * @param list<array{int, int}> $ranges
     */
    private function inside(array $codes, array $ranges): bool
    {
        foreach ($ranges as [$low, $high]) {
            foreach ($codes as $code) {
                if ($code >= $low && $code <= $high) {
                    return true;
                }
            }
        }

        return false;
    }
}
