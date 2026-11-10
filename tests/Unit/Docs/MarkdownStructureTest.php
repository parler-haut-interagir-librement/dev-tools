<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Docs;

use Phil\DevTools\Docs\MarkdownStructure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownStructure::class)]
final class MarkdownStructureTest extends TestCase
{
    public function testTheHeadingsByLevel(): void
    {
        $structure = MarkdownStructure::parse(<<<'MD'
            # One
            ## Two
            ## Two again
            ###### Six
            ####### Seven is a paragraph
            #NoSpace is a paragraph
               ### Indented three
                #### Indented four is code
            ##
            #	Tab

            ```
            # Not a heading
            ```
            MD);

        self::assertSame([1 => 2, 2 => 3, 3 => 1, 4 => 0, 5 => 0, 6 => 1], $structure->headings);
    }

    public function testTheBlocks(): void
    {
        $structure = MarkdownStructure::parse("Text\r\n\r\n```php title\r\necho 1;\r\n\r\necho 2;\r\n```\r\n\r\n~~~\r\nx\r\n~~~\r\n");

        self::assertSame(["php title\necho 1;\n\necho 2;", "\nx"], $structure->blocks);
        self::assertSame([3, 9], $structure->blockLines);
    }

    public function testAFenceClosesOnlyOnALineOfAtLeastItsLengthAndKind(): void
    {
        $structure = MarkdownStructure::parse(<<<'MD'
            ````md
            ```
            ~~~~
            ```` x
            `````
            MD);

        self::assertSame(["md\n```\n~~~~\n```` x"], $structure->blocks);
    }

    public function testAClosingFenceMayBeIndentedAndFollowedByBlanks(): void
    {
        self::assertSame(["\nx", 'after'], MarkdownStructure::parse("```\nx\n \t``` \t\n```after")->blocks);
    }

    public function testTheIndentOfAFenceInAListIsLeftOut(): void
    {
        $structure = MarkdownStructure::parse("1. Item\n\n   ```sh\n   run\n     deep\n  shallow\n\tx\n   ```\n");

        self::assertSame(["sh\nrun\n  deep\nshallow\nx"], $structure->blocks);
    }

    public function testAnIndentOfAnotherWidthThanTheFence(): void
    {
        self::assertSame(["sh\nrun\n x"], MarkdownStructure::parse("- Item\n\n  ```sh\n  run\n   x\n  ```\n")->blocks);
    }

    public function testAFenceLeftOpenRunsToTheEnd(): void
    {
        self::assertSame(["\nx\n# y\n"], MarkdownStructure::parse("```\nx\n# y\n")->blocks);
    }

    public function testBackticksInTheInfoStringOpenNoFence(): void
    {
        $structure = MarkdownStructure::parse("```inline``` and\n# Heading\n``");

        self::assertSame([], $structure->blocks);
        self::assertSame([1 => 1, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0], $structure->headings);
    }

    public function testAnInfoStringIsTrimmedAndTildesMayCarryBackticks(): void
    {
        self::assertSame(["a`b\nx"], MarkdownStructure::parse("~~~  a`b  \nx\n~~~")->blocks);
    }

    public function testTwoFencesAreNeeded(): void
    {
        self::assertSame([], MarkdownStructure::parse("``\n~~\n")->blocks);
    }
}
