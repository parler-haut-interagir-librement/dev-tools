<?php

declare(strict_types=1);

namespace Phil\DevTools\Docs;

use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Files;
use Phil\DevTools\Workspace\Package;

/**
 * Compares the English documentation of a package with its French mirror: a document without its mirror, a mirror
 * without its document, the headings of each level and the code blocks fail; a translation behind its source is
 * reported. Whether a failure blocks is the choice of `docs.translations`, else of `docs.public`.
 *
 * The other documents at the root — `CHANGELOG.md`, `CONTRIBUTING.md`, `SECURITY.md`… — are compared with their French
 * twins the same way, but a twin still to write is reported apart, and blocks only where `docs.twins` asks for it.
 */
final readonly class TranslationChecker
{
    public const string MISSING = 'translation-missing';

    public const string TWIN_MISSING = 'translation-twin-missing';

    public const string ORPHAN = 'translation-orphan';

    public const string LATE = 'translation-late';

    public const string HEADINGS = 'translation-headings';

    public const string CODE = 'translation-code';

    public const string BLOCKING = 'blocking';

    public const string ADVISORY = 'advisory';

    /**
     * @return list<Finding>
     */
    public function check(Package $package): array
    {
        return $this->pairs($package, Mirrors::sources($package), Mirrors::mirrors($package), self::MISSING);
    }

    /**
     * Compares the documents at the root of a package, `README.md` left to `check()`, or of a repository, `README.md`
     * included, with their French twins.
     *
     * @return list<Finding>
     */
    public function checkTwins(Package $package, bool $readme = false): array
    {
        return $this->pairs($package, Mirrors::twinSources($package, $readme), Mirrors::twins($package, $readme), self::TWIN_MISSING);
    }

    /**
     * Whether a twin still to write stops the gate: `docs.twins` of the package, else of the root, is `blocking`; it is
     * advisory by default.
     */
    public function twinsBlock(Package $package, Package $root): bool
    {
        $mode = $package->manifest->get('docs', 'twins') ?? $root->manifest->get('docs', 'twins') ?? self::ADVISORY;

        if (self::BLOCKING !== $mode && self::ADVISORY !== $mode) {
            throw new InvalidManifest(\sprintf('docs.twins of %s is "%s" or "%s".', $package->name, self::BLOCKING, self::ADVISORY));
        }

        return self::BLOCKING === $mode;
    }

    /**
     * Whether the failures of a package stop the gate: its `docs.translations`, else the root's, else they do once the
     * package is public (`docs.public`) and are advisory until then, so that a new package passes before its French
     * documentation is written.
     */
    public function blocks(Package $package, Package $root): bool
    {
        $mode = $package->manifest->get('docs', 'translations')
            ?? $root->manifest->get('docs', 'translations')
            ?? ($package->manifest->flag('docs', 'public') ? self::BLOCKING : self::ADVISORY);

        if (self::BLOCKING !== $mode && self::ADVISORY !== $mode) {
            throw new InvalidManifest(\sprintf('docs.translations of %s is "%s" or "%s".', $package->name, self::BLOCKING, self::ADVISORY));
        }

        return self::BLOCKING === $mode;
    }

    /**
     * @param list<string> $sources the English documents
     * @param list<string> $mirrors the French documents that exist
     *
     * @return list<Finding>
     */
    private function pairs(Package $package, array $sources, array $mirrors, string $missing): array
    {
        $findings = [];

        foreach ($sources as $source) {
            $mirror = Mirrors::mirrorOf($source);

            if (!\in_array($mirror, $mirrors, true)) {
                $findings[] = new Finding($missing, $source, null, 'no translation at '.$mirror);

                continue;
            }

            array_push($findings, ...$this->compare($mirror, Files::read($package->path($source)), Files::read($package->path($mirror))));
        }

        foreach ($mirrors as $mirror) {
            if (!is_file($package->path(Mirrors::sourceOf($mirror)))) {
                $findings[] = new Finding(self::ORPHAN, $mirror, null, 'no English document at '.Mirrors::sourceOf($mirror));
            }
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private function compare(string $mirror, string $english, string $french): array
    {
        $source = MarkdownStructure::parse($english);
        $translation = MarkdownStructure::parse($french);

        return [
            ...$this->late($mirror, $english, $french),
            ...$this->headings($mirror, $source, $translation),
            ...$this->code($mirror, $source, $translation),
        ];
    }

    /**
     * @return list<Finding>
     */
    private function late(string $mirror, string $english, string $french): array
    {
        $stamp = SourceStamp::read($french);

        if (null === $stamp) {
            return [new Finding(self::LATE, $mirror, 1, 'no source stamp')];
        }

        return $stamp === SourceStamp::fingerprint($english) ? [] : [new Finding(self::LATE, $mirror, 1, 'its English document has changed since')];
    }

    /**
     * @return list<Finding>
     */
    private function headings(string $mirror, MarkdownStructure $source, MarkdownStructure $translation): array
    {
        $findings = [];

        foreach ($source->headings as $level => $expected) {
            $actual = $translation->headings[$level];

            if ($expected !== $actual) {
                $findings[] = new Finding(self::HEADINGS, $mirror, null, \sprintf('%d of level %d, the English has %d', $actual, $level, $expected));
            }
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private function code(string $mirror, MarkdownStructure $source, MarkdownStructure $translation): array
    {
        if (\count($source->blocks) !== \count($translation->blocks)) {
            return [new Finding(self::CODE, $mirror, null, \sprintf('%d code blocks, the English has %d', \count($translation->blocks), \count($source->blocks)))];
        }

        $findings = [];

        foreach ($source->blocks as $index => $block) {
            if ($block !== $translation->blocks[$index]) {
                $findings[] = new Finding(self::CODE, $mirror, $translation->blockLines[$index], \sprintf('differs from the code block at line %d of the English', $source->blockLines[$index]));
            }
        }

        return $findings;
    }
}
