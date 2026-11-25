<?php

declare(strict_types=1);

namespace Phil\DevTools\Conventions;

/**
 * Reads the scalar settings a Flex recipe writes under a root key, from the YAML text, by indentation: a key with a
 * scalar value, under the keys that hold it. Flow collections, block scalars, anchors and tags are left out.
 */
final class RecipeReader
{
    /**
     * @return array<string, string> the settings by dotted path, normalised
     */
    public function values(string $yaml, string $root): array
    {
        $values = [];
        $inside = false;
        /** @var list<array{int, string}> $stack */
        $stack = [];

        $lines = preg_split('/\R/', $yaml);

        foreach (false === $lines ? [] : $lines as $line) {
            if (1 !== preg_match('/^( *)([A-Za-z0-9_.-]+):(?:[ \t]+(.*?))?[ \t]*$/', $line, $match)) {
                continue;
            }

            $indent = \strlen($match[1]);

            if (0 === $indent) {
                $inside = $root === $match[2];
                $stack = [];

                continue;
            }

            if (!$inside) {
                continue;
            }

            while ([] !== $stack && $stack[\count($stack) - 1][0] >= $indent) {
                array_pop($stack);
            }

            $value = $this->withoutComment($match[3] ?? '');

            if ('' === $value) {
                $stack[] = [$indent, $match[2]];
            } elseif (!\in_array($value[0], ['{', '[', '|', '>', '&', '*', '!'], true)) {
                $values[implode('.', [...array_column($stack, 1), $match[2]])] = self::normalize($value);
            }
        }

        return $values;
    }

    /**
     * A scalar as a recipe would write it: quotes taken off, `~` and the keywords in lowercase.
     */
    public static function normalize(string $value): string
    {
        if (\strlen($value) >= 2 && ("'" === $value[0] || '"' === $value[0]) && $value[-1] === $value[0]) {
            $inner = substr($value, 1, -1);

            return '"' === $value[0] ? stripcslashes($inner) : str_replace(['\\\\', '\\\'', "''"], ['\\', "'", "'"], $inner);
        }

        $lower = strtolower($value);

        return match ($lower) {
            '~', 'null' => 'null',
            'true', 'false' => $lower,
            default => $value,
        };
    }

    private function withoutComment(string $value): string
    {
        if (1 === preg_match('/^(?:\'(?:[^\']|\'\')*\'|"(?:[^"\\\\]|\\\\.)*")/', $value, $quoted)) {
            return $quoted[0];
        }

        return preg_replace('/\s+#.*$/', '', $value) ?? $value;
    }
}
