<?php

declare(strict_types=1);

namespace Phil\DevTools\Docs;

use FilesystemIterator;
use Phil\DevTools\Workspace\Package;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The documentation checks every PHIL and Tilttek package passes: the file set, its links, its tables, its index, a
 * CHANGELOG of one "Added" section until the packages are public, the licence holder's wording, and the terms a package never
 * names.
 *
 * It checks the floor, not the content: whether what a document says is true is for a reader.
 */
final readonly class DocsChecker
{
    /**
     * The files every package has.
     *
     * @var list<string>
     */
    public const array REQUIRED_FILES = [
        'README.md',
        'CHANGELOG.md',
        'CODE_OF_CONDUCT.md',
        'CONTRIBUTING.md',
        'LICENSE',
        'SECURITY.md',
        'composer-dependency-analyser.php',
        'doc/extension-points.md',
    ];

    /**
     * The directories every package has.
     *
     * @var list<string>
     */
    public const array REQUIRED_DIRECTORIES = ['doc', 'src', 'tests'];

    /**
     * The wording of the licence holder, by vendor.
     *
     * @var array<string, string>
     */
    public const array HOLDERS = [
        'phil' => 'a Parler Haut Interagir Librement project',
        'tilttek' => 'a Tilttek project',
    ];

    /** @var list<string> */
    private const array README_SECTIONS = ['## Documentation', '## Requirements', '## Contributing', '## License'];

    /** @var list<string> */
    private const array ROOT_DOCUMENTS = ['README.md', 'CHANGELOG.md', 'CONTRIBUTING.md', 'SECURITY.md', 'CODE_OF_CONDUCT.md'];

    /** @var list<string> */
    private const array SCANNED_EXTENSIONS = ['md', 'php', 'json', 'yaml', 'yml', 'twig', 'xml', 'neon', 'txt', 'tpl', 'dist'];

    /**
     * @param list<string> $forbidden regular expressions of the terms a package never names
     */
    public function __construct(private array $forbidden = [])
    {
    }

    /**
     * @return list<Finding>
     */
    public function check(Package $package): array
    {
        $findings = [];

        foreach (self::REQUIRED_FILES as $file) {
            if (!is_file($package->path($file))) {
                $findings[] = new Finding('missing-file', $file, null, 'every package has it');
            }
        }

        foreach (self::REQUIRED_DIRECTORIES as $directory) {
            if (!is_dir($package->path($directory))) {
                $findings[] = new Finding('missing-file', $directory.'/', null, 'every package has it');
            }
        }

        foreach ($this->markdownFiles($package) as $file) {
            array_push($findings, ...$this->checkMarkdown($package, $file));
        }

        array_push(
            $findings,
            ...$this->checkIndex($package),
            ...$this->checkReadme($package),
            ...$this->checkChangelog($package),
            ...$this->checkHolder($package),
            ...$this->checkForbidden($package),
        );

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function markdownFiles(Package $package): array
    {
        $files = [];

        foreach (self::ROOT_DOCUMENTS as $file) {
            if (is_file($package->path($file))) {
                $files[] = $file;
            }
        }

        foreach ($this->filesUnder($package, 'doc') as $file) {
            if (str_ends_with($file, '.md')) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * Every relative link resolves, every table row has as many cells as its header, and `**` pairs balance within
     * each paragraph — code blocks and code spans left out.
     *
     * @return list<Finding>
     */
    private function checkMarkdown(Package $package, string $file): array
    {
        $lines = $this->proseLines($this->read($package->path($file)));

        return [
            ...$this->checkLinks($package, $file, $lines),
            ...$this->checkTables($file, $lines),
            ...$this->checkBold($file, $lines),
        ];
    }

    /**
     * @param array<int, string> $lines
     *
     * @return list<Finding>
     */
    private function checkLinks(Package $package, string $file, array $lines): array
    {
        $findings = [];
        $base = \dirname($package->path($file));

        foreach ($lines as $number => $line) {
            foreach ($this->targets($line) as $target) {
                // A URL with a scheme, or a fragment of this document.
                if (1 === preg_match('#^(?:[a-z][a-z0-9+.-]*:|\#)#i', $target)) {
                    continue;
                }

                if (!file_exists($base.'/'.explode('#', $target)[0])) {
                    $findings[] = new Finding('broken-link', $file, $number, $target);
                }
            }
        }

        return $findings;
    }

    /**
     * @param array<int, string> $lines
     *
     * @return list<Finding>
     */
    private function checkTables(string $file, array $lines): array
    {
        $findings = [];
        $previous = null;
        $width = null;

        foreach ($lines as $number => $line) {
            if (!str_starts_with(ltrim($line), '|')) {
                $width = null;
                $previous = null;

                continue;
            }

            if (null === $width && null !== $previous && 1 === preg_match('/^\s*\|[-:| ]+\|?\s*$/', $line)) {
                $width = $this->cells($previous);
            }

            if (null !== $width && $this->cells($line) !== $width) {
                $findings[] = new Finding('table-shape', $file, $number, \sprintf('%d cells, the header has %d', $this->cells($line), $width));
            }

            $previous = $line;
        }

        return $findings;
    }

    /**
     * @param array<int, string> $lines
     *
     * @return list<Finding>
     */
    private function checkBold(string $file, array $lines): array
    {
        $findings = [];
        $paragraph = [];
        $first = null;

        // A blank line after the last one closes the last paragraph.
        foreach ($lines + [\PHP_INT_MAX => ''] as $number => $line) {
            if ('' !== trim($line)) {
                $first ??= $number;
                $paragraph[] = $line;

                continue;
            }

            if (null !== $first && 0 !== substr_count($this->withoutCode(implode("\n", $paragraph)), '**') % 2) {
                $findings[] = new Finding('unbalanced-bold', $file, $first, mb_substr(trim($paragraph[0]), 0, 70));
            }

            $paragraph = [];
            $first = null;
        }

        return $findings;
    }

    /**
     * Every document of `doc/` is listed by the README.
     *
     * @return list<Finding>
     */
    private function checkIndex(Package $package): array
    {
        $readme = $package->path('README.md');

        if (!is_file($readme)) {
            return [];
        }

        $listed = [];

        foreach (explode("\n", $this->read($readme)) as $line) {
            foreach ($this->targets($line) as $target) {
                $listed[] = explode('#', $target)[0];
            }
        }

        $findings = [];

        foreach ($this->filesUnder($package, 'doc') as $file) {
            if (str_ends_with($file, '.md') && !\in_array($file, $listed, true)) {
                $findings[] = new Finding('doc-index', 'README.md', null, $file.' is not listed');
            }
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private function checkReadme(Package $package): array
    {
        $readme = $package->path('README.md');

        if (!is_file($readme)) {
            return [];
        }

        $lines = array_map(rtrim(...), explode("\n", $this->read($readme)));
        $findings = [];

        foreach (self::README_SECTIONS as $section) {
            if (!\in_array($section, $lines, true)) {
                $findings[] = new Finding('readme-section', 'README.md', null, $section.' is missing');
            }
        }

        return $findings;
    }

    /**
     * Until the packages are launched publicly — split to their public repositories —, a CHANGELOG has one "Added"
     * section: a change is folded into the entry that describes the current state. `docs.public` lifts the rule.
     *
     * @return list<Finding>
     */
    private function checkChangelog(Package $package): array
    {
        $changelog = $package->path('CHANGELOG.md');

        if (!is_file($changelog) || $package->manifest->flag('docs', 'public')) {
            return [];
        }

        $findings = [];
        $added = false;

        foreach ($this->proseLines($this->read($changelog)) as $number => $line) {
            if (1 !== preg_match('/^###\s+(.+?)\s*$/', $line, $heading)) {
                continue;
            }

            if ('Added' === $heading[1]) {
                $added = true;

                continue;
            }

            $findings[] = new Finding('changelog', 'CHANGELOG.md', $number, \sprintf('"%s": a package not yet public lists additions only', $heading[1]));
        }

        if (!$added) {
            $findings[] = new Finding('changelog', 'CHANGELOG.md', null, 'no "### Added" section');
        }

        return $findings;
    }

    /**
     * The LICENSE and the README name the holder with the wording of the package's vendor.
     *
     * @return list<Finding>
     */
    private function checkHolder(Package $package): array
    {
        $holder = self::HOLDERS[$package->vendor()] ?? null;

        if (null === $holder) {
            return [];
        }

        $findings = [];

        foreach (['LICENSE', 'README.md'] as $file) {
            $path = $package->path($file);

            if (is_file($path) && !str_contains($this->read($path), $holder)) {
                $findings[] = new Finding('holder', $file, null, \sprintf('the holder is named "%s"', $holder));
            }
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private function checkForbidden(Package $package): array
    {
        $findings = [];

        foreach ($this->filesUnder($package, '') as $file) {
            if ('LICENSE' !== $file && !\in_array(pathinfo($file, \PATHINFO_EXTENSION), self::SCANNED_EXTENSIONS, true)) {
                continue;
            }

            foreach (explode("\n", $this->read($package->path($file))) as $index => $line) {
                foreach ($this->forbidden as $pattern) {
                    if (1 === preg_match($pattern, $line, $match)) {
                        $findings[] = new Finding('forbidden-term', $file, $index + 1, \sprintf('"%s" matches %s', $match[0], $pattern));
                    }
                }
            }
        }

        return $findings;
    }

    /**
     * The files under a directory of the package, relative to the package, sorted; `vendor/`, `var/` and dot
     * directories left out.
     *
     * @return list<string>
     */
    private function filesUnder(Package $package, string $directory): array
    {
        $root = $package->path($directory);

        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $info */
        foreach ($iterator as $info) {
            $relative = substr($info->getPathname(), \strlen($package->dir) + 1);

            if (1 !== preg_match('#(^|/)(vendor|var|node_modules|\.[^/]+)/#', $relative)) {
                $files[] = $relative;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * The targets of the Markdown links of a line.
     *
     * @return list<string>
     */
    private function targets(string $line): array
    {
        preg_match_all('/\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/u', $line, $matches);

        return $matches[1];
    }

    /**
     * The lines of a Markdown text by number, those of fenced code blocks blanked.
     *
     * @return array<int, string>
     */
    private function proseLines(string $text): array
    {
        $lines = [];
        $fenced = false;

        foreach (explode("\n", $text) as $index => $line) {
            $fence = str_starts_with(ltrim($line), '```');
            $fenced = $fence ? !$fenced : $fenced;
            $lines[$index + 1] = $fence || $fenced ? '' : $line;
        }

        return $lines;
    }

    private function cells(string $line): int
    {
        return substr_count(rtrim(trim(str_replace('\\|', '', $this->withoutCode($line))), '|'), '|');
    }

    private function withoutCode(string $text): string
    {
        return preg_replace('/`[^`]*`/u', '``', $text) ?? $text;
    }

    private function read(string $path): string
    {
        $content = file_get_contents($path);

        return false === $content ? '' : $content;
    }
}
