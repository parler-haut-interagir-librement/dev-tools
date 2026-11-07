<?php

declare(strict_types=1);

namespace Phil\DevTools\Config;

/**
 * Writes the YAML of a generated configuration: maps, lists, strings in single quotes, integers and booleans. It
 * writes nothing else, because the generated configurations hold nothing else.
 */
final class YamlWriter
{
    /**
     * @param array<array-key, mixed> $data
     */
    public static function write(array $data, string $header = ''): string
    {
        $yaml = '';

        foreach ('' === $header ? [] : explode("\n", $header) as $line) {
            $yaml .= rtrim('# '.$line)."\n";
        }

        return $yaml.self::block($data, 0);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function block(array $data, int $depth): string
    {
        $indent = str_repeat('  ', $depth);
        $yaml = '';

        if (array_is_list($data)) {
            foreach ($data as $value) {
                if (\is_array($value) && [] !== $value) {
                    $nested = self::block($value, $depth + 1);
                    $yaml .= $indent.'- '.ltrim($nested);

                    continue;
                }

                $yaml .= $indent.'- '.self::scalar($value)."\n";
            }

            return $yaml;
        }

        foreach ($data as $key => $value) {
            $name = self::key((string) $key);

            if (\is_array($value) && [] !== $value) {
                $yaml .= $indent.$name.":\n".self::block($value, $depth + 1);

                continue;
            }

            $yaml .= $indent.$name.': '.self::scalar($value)."\n";
        }

        return $yaml;
    }

    private static function key(string $key): string
    {
        return 1 === preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $key) ? $key : self::quote($key);
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            null === $value => '~',
            true === $value => 'true',
            false === $value => 'false',
            \is_int($value) => (string) $value,
            \is_array($value) => '[]',
            \is_string($value) => self::quote($value),
            default => self::quote(get_debug_type($value)),
        };
    }

    private static function quote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
