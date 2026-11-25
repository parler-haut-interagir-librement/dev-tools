<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Fuzz;

use Phil\DevTools\Exception\RejectedCaseAccepted;
use Phil\DevTools\Exception\UnsupportedPattern;
use Phil\DevTools\Fuzz\Regex\Alternatives;
use Phil\DevTools\Fuzz\Regex\CharacterSet;
use Phil\DevTools\Fuzz\Regex\ClassParser;
use Phil\DevTools\Fuzz\Regex\Cursor;
use Phil\DevTools\Fuzz\Regex\Literal;
use Phil\DevTools\Fuzz\Regex\RegexParser;
use Phil\DevTools\Fuzz\Regex\Repetition;
use Phil\DevTools\Fuzz\Regex\Sequence;
use Phil\DevTools\Fuzz\RegexGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

#[CoversClass(RegexGenerator::class)]
#[CoversClass(RegexParser::class)]
#[CoversClass(ClassParser::class)]
#[CoversClass(Cursor::class)]
#[CoversClass(Alternatives::class)]
#[CoversClass(CharacterSet::class)]
#[CoversClass(Literal::class)]
#[CoversClass(Repetition::class)]
#[CoversClass(Sequence::class)]
final class RegexGeneratorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function patterns(): iterable
    {
        yield 'a slug' => ['/^[a-z0-9]+(?:-[a-z0-9]+)*$/', ['', '-a', 'a-', 'A']];
        yield 'a phone number' => ['/^\+?[1-9]\d{1,14}$/', ['', '+0', '12a']];
        yield 'a dice notation' => ['/^(\d+)?d(\d+|%)([+-]\d+)?$/i', ['', 'd', '2x6']];
        yield 'an address' => ['~^https?://[a-z0-9.-]+\.[a-z]{2,}(/[^\s]*)?$~', ['ftp://a.bc', 'https://a']];
        yield 'an email address' => ['/^[\p{L}\p{N}_.-]{1,64}@[\w-]+(\.[\w-]+)+$/u', ['@a.b', 'a@b']];
        yield 'a caseless negated class' => ['/^[^aeiou]{3}$/i', ['abc', 'xAx']];
        yield 'named groups and POSIX classes' => ['/^(?<y>\d{4})-(?P<m>0[1-9]|1[0-2])\x2D(?\'d\'[[:digit:]]{2})$/', ['2020-13-01']];
        yield 'brackets as members' => ['/^a{,3}\.\t[]x-]{2}[-a]$/', ['b']];
        yield 'escapes' => ['/^\A\x41\x{42}\e\a\f\v\r\n\/\\\\\Z$/', ['x']];
        yield 'negated escapes' => ['/^\D\W\S[\D][\W][\S][[:^alpha:]][^\d]$/', ['aaaaaaaa']];
        yield 'properties without u' => ['/^\p{Lu}\pN\p{Ll}\p{L}\p{Nd}$/', ['x']];
        yield 'unicode' => ['/^[\x{263A}-\x{263C}é]\x{1F600}.$/u', ['x']];
        yield 'lazy quantifiers and alternatives' => ['/^(?:ab|cd|e)+?x*?y??z{2,}?$/m', ['q']];
        yield 'other delimiters and modifiers' => ['{^[a-c]{1,2}$}sDUAn', ['d']];
        yield 'a literal brace' => ['/^a{b}{,}c{1,2}$/', ['ac']];
        yield 'an empty pattern' => ['/^$/', ['a']];
        yield 'a backspace in a class' => ['/^[\b]$/', ['b']];
    }

    /**
     * @param list<string> $rejected
     */
    #[DataProvider('patterns')]
    public function testEveryStringDrawnMatches(string $pattern, array $rejected): void
    {
        $strings = new RegexGenerator(7)->generate($pattern, $rejected, 64);

        self::assertCount(64, $strings);

        foreach ($strings as $string) {
            self::assertMatchesRegularExpression($pattern, $string);
        }
    }

    public function testTheSameSeedDrawsTheSameStrings(): void
    {
        $pattern = '/^[a-c]{1,3}(x|y)$/';

        self::assertSame(new RegexGenerator(3)->generate($pattern, ['z'], 10), new RegexGenerator(3)->generate($pattern, ['z'], 10));
        self::assertNotSame(new RegexGenerator(3)->generate($pattern, ['z'], 10), new RegexGenerator(4)->generate($pattern, ['z'], 10));
        self::assertSame(['aby', 'ccby', 'acx', 'cx', 'bx', 'caby', 'cacy', 'ay'], new RegexGenerator(1)->generate($pattern, ['z'], 8));
    }

    public function testAnUnboundedQuantifierRepeatsUpToItsMinimumAndTheMaximum(): void
    {
        $lengths = array_map(strlen(...), new RegexGenerator(1, 2)->generate('/^a{3,}$/', ['aa'], 200));

        self::assertSame(3, min([1000, ...$lengths]));
        self::assertSame(5, max([0, ...$lengths]));
        self::assertEqualsCanonicalizing(['', 'a'], array_values(array_unique(new RegexGenerator(1)->generate('/^a?$/', ['b'], 50))));
        self::assertSame([], new RegexGenerator(1)->generate('/a/', ['b'], 0));
    }

    public function testTheRejectedCasesAreRequiredAndRefused(): void
    {
        try {
            new RegexGenerator()->generate('/^a$/', []);
            self::fail('No rejected case was accepted.');
        } catch (RejectedCaseAccepted $rejectedCaseAccepted) {
            self::assertSame('The strings of /^a$/ come with rejected cases written by hand: give one at least.', $rejectedCaseAccepted->getMessage());
        }

        $this->expectException(RejectedCaseAccepted::class);
        $this->expectExceptionMessageIsOrContains('/^a+$/ accepts the rejected case "aa".');

        new RegexGenerator()->generate('/^a+$/', ['b', 'aa']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupported(): iterable
    {
        yield 'invalid' => ['/(a/', '/(a/ is not a valid regular expression.'];
        yield 'a lookahead' => ['/(?=a)a/', 'The group "(?=a" (lookaround, atomic group, inline flag, recursion or condition) is not supported.'];
        yield 'a negative lookbehind' => ['/(?<!a)b/', 'The group "(?<!" (lookaround, atomic group, inline flag, recursion or condition) is not supported.'];
        yield 'an atomic group' => ['/(?>a)/', 'The group "(?>a" (lookaround, atomic group, inline flag, recursion or condition) is not supported.'];
        yield 'an inline flag' => ['/(?i)a/', 'The group "(?i)" (lookaround, atomic group, inline flag, recursion or condition) is not supported.'];
        yield 'a back-reference' => ['/(a)\1/', 'The escape "\1" (back-reference, word boundary, octal or other) is not supported.'];
        yield 'a word boundary' => ['/\ba/', 'The escape "\b" (back-reference, word boundary, octal or other) is not supported.'];
        yield 'a possessive quantifier' => ['/a++/', 'A possessive quantifier is not supported.'];
        yield 'the x modifier' => ['/a/x', 'The modifier "x" of /a/x is not supported: only imsuDUAn.'];
        yield 'a quantifier too high' => ['/a{1001}/', 'A quantifier above 1000.'];
        yield 'a minimum too high' => ['/a{1001,}/', 'A quantifier above 1000.'];
        yield 'a property not read' => ['/\p{Greek}/u', 'The property \p{Greek} is not supported: only L, Lu, Ll, N, Nd.'];
        yield 'a short property not read' => ['/\pM/u', 'The property \p{M} is not supported: only L, Lu, Ll, N, Nd.'];
        yield 'a POSIX class not read' => ['/[[:graph:]]/', 'The POSIX class "[:graph:]" is not supported.'];
        yield 'a class with no printable character' => ['/[^\x00-\x7F]/', 'A character class that matches no printable character.'];
        yield 'an anchor inside' => ['/a^b/', '/a^b/ does not accept "ab", which the generator drew from it: a construct it reads otherwise than PCRE.'];
        yield 'a character beyond a byte' => ['/\x{100}/', '/\x{100}/ is not a valid regular expression.'];
    }

    #[DataProvider('unsupported')]
    public function testWhatTheGeneratorCannotHonourIsRefused(string $pattern, string $message): void
    {
        $this->expectException(UnsupportedPattern::class);
        $this->expectExceptionMessageIsOrContains($message);

        new RegexGenerator()->generate($pattern, ['zzzz']);
    }

    public function testATooLongOrTooDeepPatternIsRefused(): void
    {
        try {
            new RegexGenerator()->generate('/'.str_repeat('a', RegexParser::MAX_PATTERN).'/', ['b']);
            self::fail('A long pattern was accepted.');
        } catch (UnsupportedPattern $unsupportedPattern) {
            self::assertSame('A pattern of 4098 bytes is too long: 4096 at most.', $unsupportedPattern->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('Groups nested deeper than 64.');

        new RegexGenerator()->generate('/'.str_repeat('(', 65).'a'.str_repeat(')', 65).'/', ['b']);
    }

    public function testTheNodesDrawWhatTheyHold(): void
    {
        $randomizer = new Randomizer(new Mt19937(1));

        self::assertSame('x', new Literal('x')->draw($randomizer));
        self::assertSame('', new Sequence([])->draw($randomizer));
        self::assertSame('ab', new Sequence([new Literal('a'), new Literal('b')])->draw($randomizer));
        self::assertSame('aaa', new Repetition(new Literal('a'), 3, 3)->draw($randomizer));
        self::assertSame('q', new Alternatives([new Literal('q')])->draw($randomizer));
        self::assertSame("\u{D7FF}", new CharacterSet([[0xD7FF, 0xD7FF]], true)->draw($randomizer));
        self::assertSame("\u{E000}", new CharacterSet([[0xE000, 0xE000]], true)->draw($randomizer));

        for ($i = 0; $i < 50; ++$i) {
            self::assertContains(new CharacterSet([[0xD7FE, 0xDFFF]], true)->draw($randomizer), ["\u{D7FE}", "\u{D7FF}"]);
            self::assertContains(new CharacterSet([[0xD800, 0xE000]], true)->draw($randomizer), ["\u{E000}"]);
        }

        self::assertSame("\xFF", CharacterSet::character(0xFF, false));
        self::assertSame("\x00", CharacterSet::character(0, false));

        $this->expectExceptionMessageIsOrContains('The character U+0100 needs the u modifier.');

        CharacterSet::character(0x100, false);
    }

    public function testACursorReadsThePattern(): void
    {
        $cursor = new Cursor(['a', 'é'], true, true);

        self::assertTrue($cursor->caseless);
        self::assertSame('a', $cursor->peek());
        self::assertSame('é', $cursor->peek(1));
        self::assertSame('aé', $cursor->ahead(5));
        self::assertSame(0xE9, $cursor->code('é'));
        self::assertSame('a', $cursor->next());
        self::assertSame(1, $cursor->position());
        self::assertFalse($cursor->atEnd());
        self::assertSame('é', $cursor->next());
        self::assertNull($cursor->next());
        self::assertSame(2, $cursor->position());
        self::assertTrue($cursor->atEnd());
        self::assertSame(0xC3, new Cursor([], false, false)->code("\xC3"));
    }
}
