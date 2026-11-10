<?php

declare(strict_types=1);

namespace Phil\DevTools\Docs;

/**
 * What a translation must keep of its source: how many headings of each level, and every fenced code block.
 */
final readonly class MarkdownStructure
{
    /**
     * @param array<int, int> $headings   the number of headings of each level, 1 to 6
     * @param list<string>    $blocks     each fenced block: its info string, then its lines without the fence's indent
     * @param list<int>       $blockLines the line each block opens on
     */
    private function __construct(
        public array $headings,
        public array $blocks,
        public array $blockLines,
    ) {
    }

    public static function parse(string $text): self
    {
        $headings = array_fill(1, 6, 0);
        $blocks = [];
        $blockLines = [];
        $fence = null;
        $body = [];

        foreach (explode("\n", str_replace("\r\n", "\n", $text)) as $index => $line) {
            if (null !== $fence) {
                $closing = trim($line, " \t");

                if (str_starts_with($closing, $fence['marker']) && '' === trim($closing, $fence['marker'])) {
                    $blocks[] = implode("\n", $body);
                    $fence = null;

                    continue;
                }

                $body[] = substr($line, strspn($line, " \t", 0, $fence['indent']));

                continue;
            }

            if (1 === preg_match('/^([ \t]*)(`{3,}(?=[^`]*$)|~{3,})(.*)$/', $line, $match)) {
                $fence = ['marker' => $match[2], 'indent' => \strlen($match[1])];
                $body = [trim($match[3])];
                $blockLines[] = $index + 1;

                continue;
            }

            if (1 === preg_match('/^ {0,3}(#{1,6})(?:[ \t]|$)/', $line, $match)) {
                ++$headings[\strlen($match[1])];
            }
        }

        // A fence left open runs to the end of the document.
        if (null !== $fence) {
            $blocks[] = implode("\n", $body);
        }

        return new self($headings, $blocks, $blockLines);
    }
}
