<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Docs;

use Phil\DevTools\Docs\SourceStamp;
use Phil\DevTools\Docs\TranslationChecker;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\PackageRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationChecker::class)]
#[UsesClass(SourceStamp::class)]
final class TranslationCheckerTest extends TestCase
{
    use TemporaryDirectory;

    private const string ENGLISH = "# Title\n\n## Section\n\n```sh\nrun\n```\n";

    public function testMatchingDocumentsPass(): void
    {
        $dir = $this->package();
        $this->pair($dir, 'README.md', 'README.fr.md', self::ENGLISH, "# Titre\n\n## Partie\n\n```sh\nrun\n```\n");
        $this->pair($dir, 'doc/a/b.md', 'doc/fr/a/b.md', self::ENGLISH, "# Titre\n\n## Partie\n\n```sh\nrun\n```\n");

        self::assertSame([], $this->check($dir));
    }

    public function testADocumentWithoutItsMirror(): void
    {
        $dir = $this->package();
        $this->put($dir.'/README.md', self::ENGLISH);
        $this->put($dir.'/doc/a.md', self::ENGLISH);
        $this->put($dir.'/doc/fr-glossary.md', "# Glossaire\n");

        self::assertSame([
            '[translation-missing] README.md: no translation at README.fr.md',
            '[translation-missing] doc/a.md: no translation at doc/fr/a.md',
        ], $this->check($dir));
    }

    public function testAMirrorWithoutItsDocument(): void
    {
        $dir = $this->package();
        $this->put($dir.'/README.fr.md', '# Titre');
        $this->put($dir.'/doc/fr/a.md', '# Titre');

        self::assertSame([
            '[translation-orphan] README.fr.md: no English document at README.md',
            '[translation-orphan] doc/fr/a.md: no English document at doc/a.md',
        ], $this->check($dir));
    }

    public function testALateTranslation(): void
    {
        $dir = $this->package();
        $this->pair($dir, 'README.md', 'README.fr.md', self::ENGLISH, "# Titre\n\n## Partie\n\n```sh\nrun\n```\n");
        $this->put($dir.'/README.md', self::ENGLISH.'More.');

        self::assertSame(['[translation-late] README.fr.md:1: its English document has changed since'], $this->check($dir));
    }

    public function testAMirrorWithoutAStamp(): void
    {
        $dir = $this->package();
        $this->put($dir.'/README.md', '# Title');
        $this->put($dir.'/README.fr.md', '# Titre');

        self::assertSame(['[translation-late] README.fr.md:1: no source stamp'], $this->check($dir));
    }

    public function testTheHeadingsOfEachLevel(): void
    {
        $dir = $this->package();
        $this->pair($dir, 'README.md', 'README.fr.md', "# T\n## A\n## B\n###### C\n", "# T\n## A\n### B\n");

        self::assertSame([
            '[translation-headings] README.fr.md: 1 of level 2, the English has 2',
            '[translation-headings] README.fr.md: 1 of level 3, the English has 0',
            '[translation-headings] README.fr.md: 0 of level 6, the English has 1',
        ], $this->check($dir));
    }

    public function testTheCodeBlocks(): void
    {
        $dir = $this->package();
        $this->pair($dir, 'README.md', 'README.fr.md', "```\nsame\n```\n\n```\nenglish\n```\n", "Texte\n\n```\nsame\n```\n\n```\nfrançais\n```\n");

        self::assertSame(['[translation-code] README.fr.md:8: differs from the code block at line 5 of the English'], $this->check($dir));
    }

    public function testEveryCodeBlockThatDiffers(): void
    {
        $dir = $this->package();
        $this->pair($dir, 'README.md', 'README.fr.md', "```\na\n```\n\n```\nb\n```\n", "```\nA\n```\n\n```\nB\n```\n");

        self::assertSame([
            '[translation-code] README.fr.md:2: differs from the code block at line 1 of the English',
            '[translation-code] README.fr.md:6: differs from the code block at line 5 of the English',
        ], $this->check($dir));
    }

    public function testTheNumberOfCodeBlocks(): void
    {
        $dir = $this->package();
        $this->pair($dir, 'README.md', 'README.fr.md', "```\na\n```\n", "```\na\n```\n\n```\nb\n```\n");

        self::assertSame(['[translation-code] README.fr.md: 2 code blocks, the English has 1'], $this->check($dir));
    }

    public function testALateTranslationIsStillComparedAndGoesOn(): void
    {
        $dir = $this->package();
        $this->put($dir.'/README.md', "# T\n");
        $this->put($dir.'/README.fr.md', "## T\n");
        $this->put($dir.'/doc/a.md', "# T\n");

        self::assertSame([
            '[translation-late] README.fr.md:1: no source stamp',
            '[translation-headings] README.fr.md: 0 of level 1, the English has 1',
            '[translation-headings] README.fr.md: 1 of level 2, the English has 0',
            '[translation-missing] doc/a.md: no translation at doc/fr/a.md',
        ], $this->check($dir));
    }

    public function testTheModeOfAPackage(): void
    {
        $checker = new TranslationChecker();

        self::assertFalse($checker->blocks($this->withMode(null), $this->withMode(null)));
        self::assertFalse($checker->blocks($this->withMode(null), $this->withMode('advisory')));
        self::assertTrue($checker->blocks($this->withMode(null), $this->withMode('blocking')));
        self::assertTrue($checker->blocks($this->withMode('blocking'), $this->withMode('advisory')));
        self::assertFalse($checker->blocks($this->withMode('advisory'), $this->withMode('blocking')));
    }

    public function testAPublicPackageBlocksByDefault(): void
    {
        $checker = new TranslationChecker();

        self::assertTrue($checker->blocks($this->withMode(null, true), $this->withMode(null)));
        self::assertTrue($checker->blocks($this->withMode(null, true), $this->withMode(null, false)));
        self::assertFalse($checker->blocks($this->withMode(null, false), $this->withMode(null, true)));
        self::assertFalse($checker->blocks($this->withMode(null, true), $this->withMode('advisory')));
        self::assertFalse($checker->blocks($this->withMode('advisory', true), $this->withMode(null)));
    }

    public function testAnUnknownModeIsRefused(): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('docs.translations of phil/x is "blocking" or "advisory".');

        new TranslationChecker()->blocks($this->withMode('off'), $this->withMode(null));
    }

    public function testTheTwinsAtTheRootOfAPackage(): void
    {
        $dir = $this->package();
        $this->put($dir.'/README.md', self::ENGLISH);
        $this->put($dir.'/AGENTS.md', self::ENGLISH);
        $this->put($dir.'/LICENSE.md', self::ENGLISH);
        $this->put($dir.'/CONTRIBUTING.md', self::ENGLISH);
        $this->pair($dir, 'CHANGELOG.md', 'CHANGELOG.fr.md', self::ENGLISH, "# Titre\n\n## Partie\n\n```sh\nrun\n```\n");
        $this->pair($dir, 'SECURITY.md', 'SECURITY.fr.md', self::ENGLISH, "# Titre\n\n```sh\nrun\n```\n");
        $this->put($dir.'/UPGRADE.fr.md', '# Mise à niveau');

        self::assertSame([
            '[translation-twin-missing] CONTRIBUTING.md: no translation at CONTRIBUTING.fr.md',
            '[translation-headings] SECURITY.fr.md: 0 of level 2, the English has 1',
            '[translation-orphan] UPGRADE.fr.md: no English document at UPGRADE.md',
        ], $this->checkTwins($dir));
        self::assertSame(['[translation-missing] README.md: no translation at README.fr.md'], $this->check($dir));
    }

    public function testTheTwinsAtTheRootOfARepositoryIncludeItsReadme(): void
    {
        $dir = $this->package();
        $this->put($dir.'/README.md', self::ENGLISH);
        $this->pair($dir, 'CHANGELOG.md', 'CHANGELOG.fr.md', self::ENGLISH, self::ENGLISH);
        $this->put($dir.'/CHANGELOG.md', self::ENGLISH.'More.');

        self::assertSame([
            '[translation-late] CHANGELOG.fr.md:1: its English document has changed since',
            '[translation-twin-missing] README.md: no translation at README.fr.md',
        ], array_map(strval(...), new TranslationChecker()->checkTwins(Package::fromDirectory($dir, '', PackageRole::Root), readme: true)));
    }

    public function testTheModeOfTheTwins(): void
    {
        $checker = new TranslationChecker();

        self::assertFalse($checker->twinsBlock($this->withTwins(null), $this->withTwins(null)));
        self::assertTrue($checker->twinsBlock($this->withTwins(null), $this->withTwins('blocking')));
        self::assertTrue($checker->twinsBlock($this->withTwins('blocking'), $this->withTwins('advisory')));
        self::assertFalse($checker->twinsBlock($this->withTwins('advisory'), $this->withTwins('blocking')));
        self::assertFalse($checker->twinsBlock($this->withMode('blocking', true), $this->withMode('blocking')));
    }

    public function testAnUnknownModeOfTheTwinsIsRefused(): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('docs.twins of phil/x is "blocking" or "advisory".');

        new TranslationChecker()->twinsBlock($this->withTwins('on'), $this->withTwins(null));
    }

    /**
     * @return list<string>
     */
    private function checkTwins(string $dir): array
    {
        return array_map(strval(...), new TranslationChecker()->checkTwins(Package::fromDirectory($dir, '', PackageRole::Root)));
    }

    private function withTwins(?string $mode): Package
    {
        $dir = $this->package();

        if (null !== $mode) {
            $this->put($dir.'/phil-dev.php', '<?php return '.var_export(['docs' => ['twins' => $mode]], true).';');
        }

        return Package::fromDirectory($dir, '', PackageRole::Root);
    }

    /**
     * @return list<string>
     */
    private function check(string $dir): array
    {
        return array_map(strval(...), new TranslationChecker()->check(Package::fromDirectory($dir, '', PackageRole::Root)));
    }

    private function package(): string
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', '{"name": "phil/x"}');

        return $dir;
    }

    private function pair(string $dir, string $source, string $mirror, string $english, string $french): void
    {
        $this->put($dir.'/'.$source, $english);
        $this->put($dir.'/'.$mirror, SourceStamp::apply($french, $english));
    }

    private function withMode(?string $mode, ?bool $public = null): Package
    {
        $dir = $this->package();
        $docs = array_filter(['translations' => $mode, 'public' => $public], static fn (bool|string|null $value): bool => null !== $value);

        if ([] !== $docs) {
            $this->put($dir.'/phil-dev.php', '<?php return '.var_export(['docs' => $docs], true).';');
        }

        return Package::fromDirectory($dir, '', PackageRole::Root);
    }
}
