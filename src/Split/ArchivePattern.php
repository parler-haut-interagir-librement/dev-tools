<?php

declare(strict_types=1);

namespace Phil\DevTools\Split;

/**
 * One pattern of what a Composer archive leaves out, or takes back, read as git reads the patterns of `.gitignore`: a
 * pattern with a slash, other than a trailing one, is anchored to the package's directory, any other matches at every
 * depth; a trailing slash matches only a directory; `*` and `?` stop at a slash, `**` crosses them; `[a-z]` and
 * `[!a-z]` are classes, and a bracket that opens no valid class is a literal character.
 */
final readonly class ArchivePattern
{
    /**
     * The pattern as written, with its `!` when it takes a path back.
     */
    public string $pattern;

    private string $regex;

    private bool $directoryOnly;

    /**
     * @param string $glob     the pattern without its leading `!`
     * @param bool   $excluded whether the pattern leaves the paths out, rather than taking them back
     * @param string $origin   where the pattern is written: `.gitattributes` or `archive.exclude`
     */
    public function __construct(string $glob, public bool $excluded, public string $origin)
    {
        $body = trim($glob, '/');
        $anchored = str_starts_with($glob, '/') || str_contains($body, '/');

        $this->pattern = ($excluded ? '' : '!').$glob;
        $this->directoryOnly = str_ends_with($glob, '/');
        $this->regex = '#^'.($anchored ? '' : '(?:.*/)?').$this->regex($body).'$#';
    }

    /**
     * Whether the pattern matches the path itself, relative to the package's directory, without `./` nor slashes
     * around it.
     */
    public function matches(string $path, bool $directory): bool
    {
        return ($directory || !$this->directoryOnly) && 1 === preg_match($this->regex, $path);
    }

    /**
     * The regular expression of a glob.
     */
    private function regex(string $glob): string
    {
        $regex = '';
        $length = \strlen($glob);

        for ($index = 0; $index < $length; ++$index) {
            $character = $glob[$index];

            if ('*' === $character && '*' === ($glob[$index + 1] ?? '')) {
                $slash = '/' === ($glob[$index + 2] ?? '');
                $regex .= $slash ? '(?:.*/)?' : '.*';
                $index += $slash ? 2 : 1;
            } elseif ('*' === $character) {
                $regex .= '[^/]*';
            } elseif ('?' === $character) {
                $regex .= '[^/]';
            } elseif ('[' === $character && $index + 2 < $length && false !== ($end = strpos($glob, ']', $index + 2)) && null !== ($class = $this->characterClass(substr($glob, $index + 1, $end - $index - 1)))) {
                $regex .= $class;
                $index = $end;
            } else {
                $regex .= preg_quote($character, '#');
            }
        }

        return $regex;
    }

    /**
     * The regular expression of the inside of a bracket expression — `!` first negates it, `a-z` is a range —, each
     * character escaped; null when a range runs backwards or nothing is left, and the bracket is then a literal.
     */
    private function characterClass(string $inside): ?string
    {
        $negated = str_starts_with($inside, '!');
        $characters = str_split($negated ? substr($inside, 1) : $inside);
        $class = '';
        $count = \count($characters);

        for ($index = 0; $index < $count; ++$index) {
            $first = $characters[$index];

            if ('-' === ($characters[$index + 1] ?? '') && isset($characters[$index + 2])) {
                $last = $characters[$index + 2];

                if (\ord($first[0]) > \ord($last[0])) {
                    return null;
                }

                $class .= preg_quote($first, '#').'-'.preg_quote($last, '#');
                $index += 2;
            } else {
                $class .= preg_quote($first, '#');
            }
        }

        return '' === $class ? null : '['.($negated ? '^' : '').$class.']';
    }
}
