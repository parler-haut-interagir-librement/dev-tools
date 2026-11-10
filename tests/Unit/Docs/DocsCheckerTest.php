<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Docs;

use Phil\DevTools\Docs\DocsChecker;
use Phil\DevTools\Docs\Finding;
use Phil\DevTools\Skeleton\PackageName;
use Phil\DevTools\Skeleton\SkeletonWriter;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\PackageRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocsChecker::class)]
#[CoversClass(Finding::class)]
final class DocsCheckerTest extends TestCase
{
    use TemporaryDirectory;

    public function testAnEmptyDirectoryMissesEveryFile(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', '{"name": "other/x"}');
        $this->put($dir.'/doc', '');

        self::assertSame([
            '[missing-file] README.md: every package has it',
            '[missing-file] CHANGELOG.md: every package has it',
            '[missing-file] CODE_OF_CONDUCT.md: every package has it',
            '[missing-file] CONTRIBUTING.md: every package has it',
            '[missing-file] LICENSE: every package has it',
            '[missing-file] SECURITY.md: every package has it',
            '[missing-file] composer-dependency-analyser.php: every package has it',
            '[missing-file] doc/extension-points.md: every package has it',
            '[missing-file] doc/: every package has it',
            '[missing-file] src/: every package has it',
            '[missing-file] tests/: every package has it',
        ], $this->check($dir));
    }

    public function testADirectoryIsNotAFile(): void
    {
        $dir = $this->package();
        unlink($dir.'/LICENSE');
        mkdir($dir.'/LICENSE');

        self::assertSame(['[missing-file] LICENSE: every package has it'], $this->check($dir));
    }

    public function testTheFrenchMirrorHasItsOwnIndex(): void
    {
        $dir = $this->package();
        $french = (string) file_get_contents($dir.'/README.fr.md');
        $this->put($dir.'/doc/diagram.png', '');
        $this->put($dir.'/doc/fr/diagram.png', '');
        $this->put($dir.'/doc/fr/topic.md', "# Sujet\n\n[Mort](nulle-part.md)\n");

        self::assertSame(['[broken-link] doc/fr/topic.md:3: nulle-part.md', '[doc-index] README.fr.md: doc/fr/topic.md is not listed'], $this->check($dir));

        $this->put($dir.'/doc/fr/topic.md', "# Sujet\n");
        $this->put($dir.'/README.fr.md', $french."\n[Sujet](doc/fr/topic.md#a) et [lien mort](absent.md)\n");

        self::assertSame(['[broken-link] README.fr.md:'.(substr_count($french, "\n") + 2).': absent.md'], $this->check($dir));

        unlink($dir.'/README.fr.md');
        $this->put($dir.'/doc/fr/orphan.md', '');

        self::assertSame([], $this->check($dir));
    }

    public function testLinks(): void
    {
        $dir = $this->package();
        $this->put($dir.'/doc/topic.md', <<<'MD'
            # Topic

            [Fine](installation.md), [anchor](#here), [mail](mailto:a@b.c), [with fragment](installation.md#x), [Up](../README.md).
            [Web](HTTPS://ph-il.ca) then [Broken](nowhere.md) and [titled](gone.md "Title") and [fragment](missing.md#a:b).
            MD);
        $this->put($dir.'/README.md', str_replace('## Requirements', "[Mine](mine.md)\n\n## Requirements", (string) file_get_contents($dir.'/README.md')).'[Topic](doc/topic.md)');
        $this->put($dir.'/CHANGELOG.md', file_get_contents($dir.'/CHANGELOG.md')."\n[Old](old.md)\n");

        self::assertSame([
            '[broken-link] README.md:28: mine.md',
            '[broken-link] CHANGELOG.md:16: old.md',
            '[broken-link] doc/topic.md:4: nowhere.md',
            '[broken-link] doc/topic.md:4: gone.md',
            '[broken-link] doc/topic.md:4: missing.md#a:b',
        ], $this->check($dir));
    }

    public function testTables(): void
    {
        $dir = $this->package();
        $this->put($dir.'/doc/tables.md', $this->spaces(<<<'MD'
            | A | B |
            |---|---|---|
            | one | two |

              | A | B |
              |---|---|
              | one | two | three |
            | `a|b` | c \| d |
            | e | f |~~

            | A | B |
            | x |-|
            | a | b | c |

            | A | B |
            |---|---| x
            | a | b | c |
            MD));
        $this->put($dir.'/README.md', file_get_contents($dir.'/README.md').'[Tables](doc/tables.md)');

        self::assertSame([
            '[table-shape] doc/tables.md:2: 3 cells, the header has 2',
            '[table-shape] doc/tables.md:7: 3 cells, the header has 2',
        ], $this->check($dir));
    }

    public function testTablesAfterAWiderRow(): void
    {
        $dir = $this->package();
        $this->put($dir.'/doc/tables.md', "| A | B |\n|---|---|\n| a | b | c |\n|---|---|\n");
        $this->put($dir.'/README.md', file_get_contents($dir.'/README.md').'[Tables](doc/tables.md)');

        self::assertSame(['[table-shape] doc/tables.md:3: 3 cells, the header has 2'], $this->check($dir));
    }

    public function testBold(): void
    {
        $dir = $this->package();
        $long = str_repeat('é', 80);
        $this->put($dir.'/doc/bold.md', $this->spaces(<<<MD
              **Open bold over
            two lines, and `**` in code.

            **One**
            ~~~
            **Two

            ```
            **
            ```

            **{$long}
            MD));
        $this->put($dir.'/README.md', file_get_contents($dir.'/README.md').'[Bold](doc/bold.md)');

        self::assertSame([
            '[unbalanced-bold] doc/bold.md:1: **Open bold over',
            '[unbalanced-bold] doc/bold.md:6: **Two',
            '[unbalanced-bold] doc/bold.md:12: **'.mb_substr($long, 0, 68),
        ], $this->check($dir));
    }

    public function testFencesAreLeftOut(): void
    {
        $dir = $this->package();
        $this->put($dir.'/doc/fences.md', "[Before](before.md)\n  ```\n[Inside](inside.md)\n  ```\n[After](after.md)\n");
        $this->put($dir.'/README.md', file_get_contents($dir.'/README.md').'[Fences](doc/fences.md)');

        self::assertSame([
            '[broken-link] doc/fences.md:1: before.md',
            '[broken-link] doc/fences.md:5: after.md',
        ], $this->check($dir));
    }

    public function testCodeSpansAreLeftOutOfLinks(): void
    {
        $dir = $this->package();
        $this->put($dir.'/doc/spans.md', <<<'MD'
            `[text](address)`, ``[a `b`](two.md)``, ```` ``` [c](three.md) ```` and ![a](a.png) in prose.
            `` `x` `` then [Real](real.md), and an unclosed `` [d](open.md) run.
            MD);
        $this->put($dir.'/README.md', file_get_contents($dir.'/README.md').'[Spans](doc/spans.md)');

        self::assertSame([
            '[broken-link] doc/spans.md:1: a.png',
            '[broken-link] doc/spans.md:2: real.md',
            '[broken-link] doc/spans.md:2: open.md',
        ], $this->check($dir));
    }

    public function testCodeSpansOfSeveralBackticksAreLeftOutOfBold(): void
    {
        $dir = $this->package();
        $this->put($dir.'/doc/spans.md', <<<'MD'
            One `` `**` `` and ```` ``` ** ```` here.

            | Markdown | UJO |
            |---|---|
            | `` `**` `` | ```` ``**`` ```` |

            Open ``**`` and **here.
            MD);
        $this->put($dir.'/README.md', file_get_contents($dir.'/README.md').'[Spans](doc/spans.md)');

        self::assertSame(['[unbalanced-bold] doc/spans.md:7: Open ``**`` and **here.'], $this->check($dir));
    }

    public function testCodeSpansOfSeveralBackticksAreLeftOutOfTables(): void
    {
        $dir = $this->package();
        $this->put($dir.'/doc/spans.md', <<<'MD'
            | Markdown | UJO |
            |---|---|
            | ```` ``` ```` or `~~~` fence | `code` |
            | `` `a|b` `` | `` c|d `` |
            | ``a`|`b`` | three | cells |
            MD);
        $this->put($dir.'/README.md', file_get_contents($dir.'/README.md').'[Spans](doc/spans.md)');

        self::assertSame(['[table-shape] doc/spans.md:5: 3 cells, the header has 2'], $this->check($dir));
    }

    public function testAnEscapedPipeIsNotACellSeparator(): void
    {
        $dir = $this->package();
        $this->put($dir.'/doc/pipes.md', <<<'MD'
            | Syntax | Meaning |
            |---|---|
            | `{漢字\|かんじ}` | a ruby |
            | `[[Page\|text]]`, `{{Mark\|…}}` | a link \| a mark |
            | `\` before a character | `\\` at the end of a line |
            | a \\| b | c |
            MD);
        $this->put($dir.'/README.md', file_get_contents($dir.'/README.md').'[Pipes](doc/pipes.md)');

        // An escaped backslash does not escape the pipe after it.
        self::assertSame(['[table-shape] doc/pipes.md:6: 3 cells, the header has 2'], $this->check($dir));
    }

    public function testTheReadmeSectionsAndIndex(): void
    {
        $dir = $this->package();
        $this->put($dir.'/README.md', "# phil/x\n\nSee [Testing](doc/testing.md#the-tests), doc/reference.md) and (doc/installation.md#.\n\n## License  \n\na Parler Haut Interagir Librement project\n");

        self::assertSame([
            '[doc-index] README.md: doc/extension-points.md is not listed',
            '[doc-index] README.md: doc/installation.md is not listed',
            '[doc-index] README.md: doc/reference.md is not listed',
            '[readme-section] README.md: ## Documentation is missing',
            '[readme-section] README.md: ## Requirements is missing',
            '[readme-section] README.md: ## Contributing is missing',
        ], $this->check($dir));
    }

    public function testWithoutAReadme(): void
    {
        $dir = $this->package();
        unlink($dir.'/README.md');

        self::assertSame(['[missing-file] README.md: every package has it'], $this->check($dir));
    }

    public function testTheChangelogBeforeThePublicLaunch(): void
    {
        $dir = $this->package();
        $this->put($dir.'/CHANGELOG.md', "# Changelog\n\n## NEXT\n\nNot a ### Fixed heading.\n\n### Added\n- x\n\n### Fixed  \n- y\n\n```\n### Changed\n```\n");

        self::assertSame(['[changelog] CHANGELOG.md:10: "Fixed": a package not yet public lists additions only'], $this->check($dir));
    }

    public function testAChangelogWithoutAdditions(): void
    {
        $dir = $this->package();
        $this->put($dir.'/CHANGELOG.md', "# Changelog\n\n### Changed\n");

        self::assertSame([
            '[changelog] CHANGELOG.md:3: "Changed": a package not yet public lists additions only',
            '[changelog] CHANGELOG.md: no "### Added" section',
        ], $this->check($dir));
    }

    public function testTheChangelogOfAPublicPackage(): void
    {
        $dir = $this->package();
        $this->put($dir.'/CHANGELOG.md', "# Changelog\n\n### Fixed\n- x\n");
        $this->put($dir.'/phil-dev.php', "<?php return ['docs' => ['public' => true]];");

        self::assertSame([], $this->check($dir));
    }

    public function testTheHolderOfTheVendor(): void
    {
        $dir = $this->package('tilttek/x');
        $this->put($dir.'/LICENSE', 'MIT, a Parler Haut Interagir Librement project');
        $this->put($dir.'/README.md', str_replace('a Tilttek project', 'someone', (string) file_get_contents($dir.'/README.md')));

        self::assertSame([
            '[holder] LICENSE: the holder is named "a Tilttek project"',
            '[holder] README.md: the holder is named "a Tilttek project"',
        ], $this->check($dir));
    }

    public function testAnotherVendorHasNoHolderWording(): void
    {
        $dir = $this->package();
        $this->put($dir.'/composer.json', '{"name": "other/x"}');
        $this->put($dir.'/LICENSE', 'MIT');

        self::assertSame([], $this->check($dir));
    }

    public function testForbiddenTerms(): void
    {
        $dir = $this->package();
        $this->put($dir.'/src/Thing.php', "<?php\n// Built for the SecretSite.\n");
        $this->put($dir.'/LICENSE', file_get_contents($dir.'/LICENSE').'OtherSite');
        $this->put($dir.'/vendor/a/b.php', 'SecretSite');
        $this->put($dir.'/var/cache.php', 'SecretSite');
        $this->put($dir.'/.git/config', 'SecretSite');
        $this->put($dir.'/image.png', 'SecretSite');

        self::assertSame([
            '[forbidden-term] LICENSE:22: "OtherSite" matches /\b(Secret|Other)Site\b/',
            '[forbidden-term] src/Thing.php:2: "SecretSite" matches /\b(Secret|Other)Site\b/',
            '[forbidden-term] src/Thing.php:2: "Site." matches /Site\./',
        ], $this->check($dir, ['/\b(Secret|Other)Site\b/', '/Site\./']));
    }

    public function testTheSkeletonPasses(): void
    {
        self::assertSame([], $this->check($this->package(), ['/\bnever-there\b/']));
    }

    public function testAFindingAsText(): void
    {
        self::assertSame('[code] file:3: detail', (string) new Finding('code', 'file', 3, 'detail'));
        self::assertSame('[code] file:0: detail', (string) new Finding('code', 'file', 0, 'detail'));
        self::assertSame('[code] file: detail', (string) new Finding('code', 'file', null, 'detail'));
    }

    /**
     * Each `~` is a space, which an editor would trim at the end of a line.
     */
    private function spaces(string $text): string
    {
        return str_replace('~', ' ', $text);
    }

    private function package(string $name = 'phil/x'): string
    {
        $dir = $this->temporaryDirectory().'/package';
        new SkeletonWriter('2026')->write(PackageName::parse($name), $dir, 'X.');

        return $dir;
    }

    /**
     * @param list<string> $forbidden
     *
     * @return list<string>
     */
    private function check(string $dir, array $forbidden = []): array
    {
        return array_map(strval(...), new DocsChecker($forbidden)->check(Package::fromDirectory($dir, '', PackageRole::Root)));
    }
}
