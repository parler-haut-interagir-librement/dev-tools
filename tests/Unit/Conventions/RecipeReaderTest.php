<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Conventions;

use Phil\DevTools\Conventions\RecipeReader;
use PHPUnit\Framework\Attributes\CoversClass;
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
}
