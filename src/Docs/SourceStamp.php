<?php

declare(strict_types=1);

namespace Phil\DevTools\Docs;

/**
 * The first line of a French mirror: an HTML comment, invisible once rendered, with the SHA-256 of the English file the
 * translation was made from.
 *
 * A checkout rewrites the dates of files, so they tell nothing about which file is the newer. The content does, and a
 * stamp needs no history, unlike a comparison of commits, which a shallow clone or a split repository would lack. Line
 * endings are normalized first, so that a checkout that converts them changes nothing.
 */
final class SourceStamp
{
    private const string PATTERN = '/\A<!-- phil-dev source-sha256=([0-9a-f]{64}) -->\r?\n/';

    /**
     * The fingerprint of an English document.
     */
    public static function fingerprint(string $source): string
    {
        return hash('sha256', str_replace("\r\n", "\n", $source));
    }

    /**
     * The fingerprint a French document carries, or null when it has none.
     */
    public static function read(string $mirror): ?string
    {
        return 1 === preg_match(self::PATTERN, $mirror, $match) ? $match[1] : null;
    }

    /**
     * The French document, its first line stamped with the English document it now follows.
     */
    public static function apply(string $mirror, string $source): string
    {
        return \sprintf("<!-- phil-dev source-sha256=%s -->\n", self::fingerprint($source)).(preg_replace(self::PATTERN, '', $mirror) ?? $mirror);
    }
}
