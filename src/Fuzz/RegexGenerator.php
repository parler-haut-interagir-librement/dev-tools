<?php

declare(strict_types=1);

namespace Phil\DevTools\Fuzz;

use Phil\DevTools\Exception\RejectedCaseAccepted;
use Phil\DevTools\Exception\UnsupportedPattern;
use Phil\DevTools\Fuzz\Regex\RegexParser;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Strings a regular expression must accept, drawn from the expression itself, for the valid paths of a parser: a slug,
 * a phone number, an address, a dice notation.
 *
 * The generator reads character classes (ranges, `\d`, `\w`, `\s` and their negations, POSIX classes, `\p{L}`,
 * `\p{Lu}`, `\p{Ll}`, `\p{N}`, `\p{Nd}`), `.`, escapes (`\t`, `\xhh`, `\x{hhhh}`…), groups (capturing, `(?:…)`,
 * named), alternatives, the quantifiers `*`, `+`, `?`, `{n}`, `{n,}`, `{,m}` and `{n,m}`, greedy or lazy, and the
 * anchors `^`, `$`, `\A`, `\z`, `\Z`. It refuses what it cannot honour: lookarounds, back-references, atomic groups,
 * possessive quantifiers, word boundaries, inline flags, recursion and the `x` modifier. `.` and the negated classes
 * draw from printable ASCII. The draw is the same for the same seed, on every PHP version: a failure found once is
 * found again.
 *
 * Every string drawn is checked against the expression, and so is every rejected case the caller writes by hand: the
 * generator proves what a pattern accepts, the rejected cases what it refuses, and it draws nothing without them.
 */
final readonly class RegexGenerator
{
    private Randomizer $randomizer;

    /**
     * @param int $seed      the seed of the draw: the same seed draws the same strings
     * @param int $maxRepeat how many more times than its minimum an unbounded quantifier repeats, at most
     */
    public function __construct(int $seed = 1, private int $maxRepeat = 8)
    {
        $this->randomizer = new Randomizer(new Mt19937($seed));
    }

    /**
     * Strings the pattern accepts, each checked against it, once the rejected cases are checked to be refused.
     *
     * @param string       $pattern  a delimited regular expression, as preg_match() takes it
     * @param list<string> $rejected strings the pattern must refuse, written by hand: one at least
     * @param int          $count    how many strings to draw
     *
     * @return list<string>
     *
     * @throws UnsupportedPattern   when the pattern is invalid, too long, or uses what the generator cannot honour
     * @throws RejectedCaseAccepted when a rejected case is accepted, or none is given
     */
    public function generate(string $pattern, array $rejected, int $count = 32): array
    {
        if (false === $this->match($pattern, '')) {
            throw new UnsupportedPattern(\sprintf('%s is not a valid regular expression.', $pattern));
        }

        $tree = new RegexParser($this->maxRepeat)->parse($pattern);

        if ([] === $rejected) {
            throw new RejectedCaseAccepted(\sprintf('The strings of %s come with rejected cases written by hand: give one at least.', $pattern));
        }

        foreach ($rejected as $case) {
            if (0 !== $this->match($pattern, $case)) {
                throw new RejectedCaseAccepted(\sprintf('%s accepts the rejected case "%s".', $pattern, $case));
            }
        }

        $strings = [];

        for ($i = 0; $i < $count; ++$i) {
            $string = $tree->draw($this->randomizer);

            if (1 !== $this->match($pattern, $string)) {
                throw new UnsupportedPattern(\sprintf('%s does not accept "%s", which the generator drew from it: a construct it reads otherwise than PCRE.', $pattern, $string));
            }

            $strings[] = $string;
        }

        return $strings;
    }

    /**
     * preg_match() without its warning: 1, 0, or false for an invalid pattern or subject.
     */
    private function match(string $pattern, string $subject): int|false
    {
        set_error_handler(static fn (): bool => true);

        try {
            return preg_match($pattern, $subject);
        } finally {
            restore_error_handler();
        }
    }
}
