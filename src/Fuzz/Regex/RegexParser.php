<?php

declare(strict_types=1);

namespace Phil\DevTools\Fuzz\Regex;

use Phil\DevTools\Exception\UnsupportedPattern;

/**
 * Reads a delimited regular expression into the parts a string is drawn from: alternatives, groups, quantifiers and
 * anchors here, the characters in {@see ClassParser}.
 */
final class RegexParser
{
    public const int MAX_PATTERN = 4096;

    public const int MAX_DEPTH = 64;

    public const int MAX_BOUND = 1000;

    /**
     * The modifiers that change nothing the generator draws, or that it honours.
     */
    public const string MODIFIERS = 'imsuDUAn';

    private Cursor $cursor;

    private ClassParser $classes;

    /**
     * @param int $maxRepeat how many more times than its minimum an unbounded quantifier repeats, at most
     */
    public function __construct(private readonly int $maxRepeat)
    {
        $this->cursor = Cursor::of('', '');
        $this->classes = new ClassParser($this->cursor);
    }

    /**
     * @param string $pattern a valid delimited regular expression
     */
    public function parse(string $pattern): Node
    {
        if (\strlen($pattern) > self::MAX_PATTERN) {
            throw new UnsupportedPattern(\sprintf('A pattern of %d bytes is too long: %d at most.', \strlen($pattern), self::MAX_PATTERN));
        }

        [$body, $modifiers] = $this->split($pattern);

        foreach (str_split($modifiers) as $modifier) {
            if (!str_contains(self::MODIFIERS, $modifier)) {
                throw new UnsupportedPattern(\sprintf('The modifier "%s" of %s is not supported: only %s.', $modifier, $pattern, self::MODIFIERS));
            }
        }

        $this->cursor = Cursor::of($body, $modifiers);
        $this->classes = new ClassParser($this->cursor);
        $tree = $this->alternatives(0);

        if (!$this->cursor->atEnd()) {
            throw new UnsupportedPattern(\sprintf('An unmatched ")" at offset %d.', $this->cursor->position()));
        }

        return $tree;
    }

    /**
     * The body and the modifiers of a delimited pattern.
     *
     * @return array{string, string}
     */
    private function split(string $pattern): array
    {
        $open = $pattern[0] ?? '/';
        $close = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'][$open] ?? $open;
        $end = strrpos($pattern, $close);

        if (false === $end || 0 === $end) {
            throw new UnsupportedPattern(\sprintf('%s has no closing delimiter.', $pattern));
        }

        return [substr($pattern, 1, $end - 1), substr($pattern, $end + 1)];
    }

    private function alternatives(int $depth): Node
    {
        if ($depth > self::MAX_DEPTH) {
            throw new UnsupportedPattern(\sprintf('Groups nested deeper than %d.', self::MAX_DEPTH));
        }

        $branches = [$this->sequence($depth)];

        while ('|' === $this->cursor->peek()) {
            $this->cursor->skip();
            $branches[] = $this->sequence($depth);
        }

        return 1 === \count($branches) ? $branches[0] : new Alternatives($branches);
    }

    private function sequence(int $depth): Sequence
    {
        $items = [];

        while (null !== ($char = $this->cursor->peek()) && '|' !== $char && ')' !== $char) {
            $items[] = $this->quantified($this->atom($depth));
        }

        return new Sequence($items);
    }

    private function atom(int $depth): Node
    {
        $char = $this->cursor->next() ?? '';

        return match ($char) {
            '(' => $this->group($depth),
            '[' => $this->classes->characterClass(),
            '.' => $this->classes->any(),
            '^', '$' => new Sequence([]),
            '\\' => $this->classes->escape(),
            '*', '+', '?' => throw new UnsupportedPattern(\sprintf('A quantifier "%s" with nothing to repeat.', $char)),
            default => new Literal($char),
        };
    }

    private function group(int $depth): Node
    {
        if ('?' === $this->cursor->peek()) {
            $this->cursor->skip();
            $this->groupKind((string) $this->cursor->next());
        }

        $inner = $this->alternatives($depth + 1);

        if (')' !== $this->cursor->next()) {
            throw new UnsupportedPattern('A group is not closed.');
        }

        return $inner;
    }

    /**
     * Reads what follows `(?`: `:`, or the name of a named group; refuses the rest.
     */
    private function groupKind(string $kind): void
    {
        if (':' === $kind) {
            return;
        }

        $named = ('<' === $kind && !\in_array($this->cursor->peek(), ['=', '!'], true)) || "'" === $kind;

        if ('P' === $kind && '<' === $this->cursor->peek()) {
            $named = true;
            $kind = '<';
        }

        if (!$named) {
            throw new UnsupportedPattern(\sprintf('The group "(?%s" (lookaround, atomic group, inline flag, recursion or condition) is not supported.', $kind.$this->cursor->peek()));
        }

        $close = '<' === $kind ? '>' : "'";

        while (null !== ($char = $this->cursor->next()) && $close !== $char) {
            // The name of a group draws nothing.
        }
    }

    private function quantified(Node $atom): Node
    {
        $bounds = $this->bounds();

        if (null === $bounds) {
            return $atom;
        }

        $this->cursor->skip($bounds[2]);

        if ('+' === $this->cursor->peek()) {
            throw new UnsupportedPattern('A possessive quantifier is not supported.');
        }

        if ('?' === $this->cursor->peek()) {
            $this->cursor->skip();
        }

        if (null !== $this->bounds()) {
            throw new UnsupportedPattern('Two quantifiers in a row.');
        }

        return new Repetition($atom, $bounds[0], $bounds[1] ?? $bounds[0] + $this->maxRepeat);
    }

    /**
     * The bounds of the quantifier at the cursor, with their length, without reading them; null when none is there,
     * a `{` that quantifies nothing being a literal.
     *
     * @return array{int, int|null, int}|null
     */
    private function bounds(): ?array
    {
        return match ($this->cursor->peek()) {
            '*' => [0, null, 1],
            '+' => [1, null, 1],
            '?' => [0, 1, 1],
            '{' => $this->braces(),
            default => null,
        };
    }

    /**
     * The bounds of `{n}`, `{n,}`, `{,m}` or `{n,m}` at the cursor, with their length.
     *
     * @return array{int, int|null, int}|null
     */
    private function braces(): ?array
    {
        if (1 !== preg_match('/^\{\s*(\d*)\s*(,?)\s*(\d*)\s*\}/', $this->cursor->ahead(16), $match) || ('' === $match[1] && '' === $match[3])) {
            return null;
        }

        $min = (int) $match[1];
        $max = match (true) {
            '' === $match[2] => $min,
            '' === $match[3] => null,
            default => (int) $match[3],
        };

        if (max($min, $max ?? $min) > self::MAX_BOUND) {
            throw new UnsupportedPattern(\sprintf('A quantifier above %d.', self::MAX_BOUND));
        }

        return [$min, $max, \strlen($match[0])];
    }
}
