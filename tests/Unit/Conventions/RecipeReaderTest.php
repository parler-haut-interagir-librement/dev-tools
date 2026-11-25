<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Conventions;

use Phil\DevTools\Conventions\RecipeReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecipeReader::class)]
final class RecipeReaderTest extends TestCase
{
    public function testTheSettingsOfARecipeAreReadUnderItsRootKey(): void
    {
        $yaml = <<<'YAML'
            # A comment
            other:
                a: 1
            phil_x:
                a: 'one'
                b: "two" # a comment
                c: three # a comment
                nested:
                    d: ~
                    e: TRUE
                    deeper:
                        f: 'it''s'
                g: Null
                list: [1, 2]
                map: { a: 1 }
                block: |
                    text
                anchor: &a 1
                h:
                i: '#not a comment'
            next:
                a: 2
            YAML;

        self::assertSame([
            'a' => 'one',
            'b' => 'two',
            'c' => 'three',
            'nested.d' => 'null',
            'nested.e' => 'true',
            'nested.deeper.f' => "it's",
            'g' => 'null',
            'i' => '#not a comment',
        ], new RecipeReader()->values($yaml, 'phil_x'));
    }

    public function testLinesBeforeTheRootKeyAndTextInsideABlockAreLeftAlone(): void
    {
        $yaml = <<<'YAML'
                a: 1
            phil_x:
                block: |
                    some words: here
                z: 2
                # y: 3
            YAML;

        self::assertSame(['z' => '2'], new RecipeReader()->values($yaml, 'phil_x'));
    }

    public function testAQuotedValueKeepsWhatLooksLikeAComment(): void
    {
        $yaml = <<<'YAML'
            phil_x:
                a: 'a # b' # c
                b: "d # e" # f
                c: g # h
                d: ''
                e: FALSE
            YAML;

        self::assertSame(['a' => 'a # b', 'b' => 'd # e', 'c' => 'g', 'd' => '', 'e' => 'false'], new RecipeReader()->values($yaml, 'phil_x'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function scalars(): iterable
    {
        yield 'single quotes' => ["'abc'", 'abc'];
        yield 'two single quotes' => ["''", ''];
        yield 'doubled quote' => ["'it''s'", "it's"];
        yield 'backslashes in single quotes' => ["'a\\\\b'", 'a\\b'];
        yield 'an escaped quote in single quotes' => ["'a\\'b'", "a'b"];
        yield 'escapes in double quotes' => ['"a\\nb"', "a\nb"];
        yield 'an unterminated single quote' => ["'abc", "'abc"];
        yield 'an unterminated double quote' => ['"abc', '"abc'];
        yield 'the same first and last character' => ['aba', 'aba'];
        yield 'one character' => ['a', 'a'];
        yield 'a tilde' => ['~', 'null'];
        yield 'NULL' => ['NULL', 'null'];
        yield 'TRUE' => ['TRUE', 'true'];
        yield 'False' => ['False', 'false'];
        yield 'a word' => ['Word', 'Word'];
    }

    #[DataProvider('scalars')]
    public function testAScalarIsNormalisedAsARecipeWritesIt(string $value, string $expected): void
    {
        self::assertSame($expected, RecipeReader::normalize($value));
    }
}
