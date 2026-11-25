<?php

declare(strict_types=1);

namespace Phil\DevTools\Fuzz\Regex;

/**
 * The characters of a pattern's body, code points under `u`, bytes otherwise, and how far they are read.
 */
final class Cursor
{
    private int $position = 0;

    /**
     * @param list<string> $chars
     */
    public function __construct(private readonly array $chars, public readonly bool $unicode, public readonly bool $caseless)
    {
    }

    /**
     * The characters of a body read under the modifiers of its pattern: bytes, or code points under `u`.
     */
    public static function of(string $body, string $modifiers): self
    {
        $unicode = str_contains($modifiers, 'u');

        return new self($unicode ? mb_str_split($body, 1, 'UTF-8') : str_split($body), $unicode, str_contains($modifiers, 'i'));
    }

    public function peek(int $ahead = 0): ?string
    {
        return $this->chars[$this->position + $ahead] ?? null;
    }

    /**
     * The character at the cursor, read: null at the end.
     *
     * @phpstan-impure
     */
    public function next(): ?string
    {
        $char = $this->chars[$this->position] ?? null;

        if (null !== $char) {
            ++$this->position;
        }

        return $char;
    }

    public function skip(int $count = 1): void
    {
        $this->position += $count;
    }

    /**
     * The next characters, without reading them.
     */
    public function ahead(int $length): string
    {
        return implode('', \array_slice($this->chars, $this->position, $length));
    }

    public function position(): int
    {
        return $this->position;
    }

    public function atEnd(): bool
    {
        return $this->position >= \count($this->chars);
    }

    /**
     * The code point of a character of the pattern.
     */
    public function code(string $char): int
    {
        return $this->unicode ? mb_ord($char, 'UTF-8') : \ord($char[0]);
    }
}
