<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Skeleton;

use FilesystemIterator;
use Phil\DevTools\DevTools;
use Phil\DevTools\Docs\DocsChecker;
use Phil\DevTools\Docs\SourceStamp;
use Phil\DevTools\Docs\TranslationChecker;
use Phil\DevTools\Exception\InvalidPackageName;
use Phil\DevTools\Process\LocalProcessRunner;
use Phil\DevTools\Skeleton\PackageName;
use Phil\DevTools\Skeleton\SkeletonWriter;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\PackageRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

#[CoversClass(SkeletonWriter::class)]
final class SkeletonWriterTest extends TestCase
{
    use TemporaryDirectory;

    public function testALibrary(): void
    {
        $dir = $this->temporaryDirectory().'/opening-hours';

        $files = new SkeletonWriter('2027')->write(PackageName::parse('phil/opening-hours'), $dir, 'Opening hours as values.');

        self::assertSame([
            'CHANGELOG.md', 'CODE_OF_CONDUCT.md', 'CONTRIBUTING.md', 'LICENSE', 'README.fr.md', 'README.md', 'SECURITY.md',
            'composer-dependency-analyser.php', 'composer.json',
            'doc/extension-points.md',
            'doc/fr/extension-points.md', 'doc/fr/installation.md', 'doc/fr/reference.md', 'doc/fr/testing.md',
            'doc/installation.md', 'doc/reference.md', 'doc/testing.md',
            'phil-dev.php', 'src/.gitkeep', 'tests/Unit/.gitkeep',
        ], $files);

        $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertSame('phil/opening-hours', $composer['name']);
        self::assertSame('library', $composer['type']);
        self::assertSame('Opening hours as values.', $composer['description']);
        self::assertSame(['psr-4' => ['Phil\\OpeningHours\\' => 'src/']], $composer['autoload']);
        // Composer itself refuses a version with an advisory: no roave/security-advisories.
        self::assertSame(['phpunit/phpunit' => '^13.4'], $composer['require-dev']);
        self::assertSame(['policy' => ['advisories' => ['block' => true]]], $composer['config']);
        // The French mirror only blocks once the package is public.
        self::assertStringNotContainsString('translations', (string) file_get_contents($dir.'/phil-dev.php'));
        self::assertStringContainsString('Copyright (c) 2027 Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project', (string) file_get_contents($dir.'/LICENSE'));
        self::assertStringContainsString('one of the open packages of [Parler Haut Interagir Librement]', (string) file_get_contents($dir.'/CONTRIBUTING.md'));
        self::assertStringNotContainsString('{{', (string) file_get_contents($dir.'/README.md'));

        // The skeleton passes the documentation checks as it is.
        self::assertSame([], new DocsChecker()->check(Package::fromDirectory($dir, '', PackageRole::Root)));
        self::assertSame([], new TranslationChecker()->check(Package::fromDirectory($dir, '', PackageRole::Root)));
        self::assertSame(SourceStamp::fingerprint((string) file_get_contents($dir.'/README.md')), SourceStamp::read((string) file_get_contents($dir.'/README.fr.md')));
    }

    public function testABundle(): void
    {
        $dir = $this->temporaryDirectory().'/tilttek-quests-bundle';

        $files = new SkeletonWriter('2026')->write(PackageName::parse('tilttek/quests', true), $dir, 'Quests for Symfony.', true);

        self::assertContains('src/TilttekQuestsBundle.php', $files);
        self::assertContains('tests/Unit/TilttekQuestsBundleTest.php', $files);
        self::assertContains('doc/configuration.md', $files);
        self::assertNotContains('src/.gitkeep', $files);

        $bundle = (string) file_get_contents($dir.'/src/TilttekQuestsBundle.php');
        self::assertStringContainsString('namespace Tilttek\\QuestsBundle;', $bundle);
        self::assertStringContainsString("protected string \$extensionAlias = 'tilttek_quests';", $bundle);
        self::assertStringContainsString('a Tilttek project', (string) file_get_contents($dir.'/LICENSE'));
        self::assertStringContainsString('open `tilttek/*` packages of Tilttek', (string) file_get_contents($dir.'/CONTRIBUTING.md'));
        self::assertStringContainsString('integrates `tilttek/quests` with Symfony', (string) file_get_contents($dir.'/README.md'));
        self::assertStringContainsString('"type": "symfony-bundle"', (string) file_get_contents($dir.'/composer.json'));
        self::assertSame([], new DocsChecker()->check(Package::fromDirectory($dir, '', PackageRole::Root)));
        self::assertSame([], new TranslationChecker()->check(Package::fromDirectory($dir, '', PackageRole::Root)));
        self::assertStringContainsString('intègre `tilttek/quests` à Symfony', (string) file_get_contents($dir.'/README.fr.md'));
        self::assertFileExists($dir.'/doc/fr/configuration.md');
    }

    public function testABundleWithoutItsLibrary(): void
    {
        $dir = $this->temporaryDirectory().'/x';

        new SkeletonWriter('2026')->write(PackageName::parse('phil/menu-bundle'), $dir, 'Menus.');

        self::assertStringContainsString('phil/menu-bundle is a Symfony bundle.', (string) file_get_contents($dir.'/README.md'));
        self::assertStringContainsString('phil/menu-bundle est un bundle Symfony.', (string) file_get_contents($dir.'/README.fr.md'));
    }

    public function testAPlannedLibrary(): void
    {
        $dir = $this->temporaryDirectory().'/opening-hours';

        $files = new SkeletonWriter('2027')->write(
            PackageName::parse('phil/opening-hours'),
            $dir,
            'Opening hours as values. A week of ranges, its exceptions and the next opening.',
            planned: true,
            descriptionFr: "Des heures d'ouverture en valeurs. Une semaine de plages, ses exceptions et la prochaine ouverture.",
            title: 'Opening hours',
        );

        self::assertContains('tests/Unit/PackageTest.php', $files);
        self::assertNotContains('tests/Unit/.gitkeep', $files);
        self::assertContains('src/.gitkeep', $files);

        $readme = (string) file_get_contents($dir.'/README.md');
        self::assertStringStartsWith("# Opening hours\n\n**Opening hours as values.**\n\nphil/opening-hours is a plain PHP library.\n\n**Being written.** The package holds its skeleton; the code described below is not written yet.\n\nOpening hours as values. A week of ranges, its exceptions and the next opening.\n\n## Status\n", $readme);
        self::assertStringContainsString("| The package skeleton | Implemented |\n| The code described above | Being written |\n", $readme);
        $readmeFr = (string) file_get_contents($dir.'/README.fr.md');
        self::assertStringContainsString("**Des heures d'ouverture en valeurs.**", $readmeFr);
        self::assertStringContainsString("**En cours d'écriture.** Le paquet contient son squelette ; le code décrit ci-dessous n'est pas encore écrit.\n\nDes heures d'ouverture en valeurs. Une semaine de plages", $readmeFr);
        self::assertStringContainsString("| Le code décrit plus haut | En cours d'écriture |", $readmeFr);
        self::assertStringContainsString('| — | The package is being written; it has no public type yet. |', (string) file_get_contents($dir.'/doc/reference.md'));
        self::assertStringContainsString("| — | Le paquet est en cours d'écriture ; il n'a pas encore de type public. |", (string) file_get_contents($dir.'/doc/fr/reference.md'));
        self::assertStringContainsString("    'status' => 'planned',\n", (string) file_get_contents($dir.'/phil-dev.php'));
        self::assertSame('planned', Package::fromDirectory($dir, '', PackageRole::Root)->manifest->get('status'));
        self::assertStringContainsString("### Added\n\n- The description of what the package will do, in the README, while its code is being written.\n", (string) file_get_contents($dir.'/CHANGELOG.md'));

        $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertSame('Opening hours as values.', $composer['description']);

        $test = (string) file_get_contents($dir.'/tests/Unit/PackageTest.php');
        self::assertStringContainsString('namespace Phil\\OpeningHours\\Tests\\Unit;', $test);
        self::assertStringContainsString("self::assertSame(['psr-4' => ['Phil\\\\OpeningHours\\\\' => 'src/']], \$autoload);", $test);

        self::assertSame([], new DocsChecker()->check(Package::fromDirectory($dir, '', PackageRole::Root)));
        self::assertSame([], new TranslationChecker()->check(Package::fromDirectory($dir, '', PackageRole::Root)));
    }

    public function testThePlannedSmokeTestPasses(): void
    {
        $dir = $this->temporaryDirectory().'/smoke';
        new SkeletonWriter('2026')->write(PackageName::parse('phil/smoke'), $dir, 'Smoke.', planned: true);

        $result = new LocalProcessRunner()->capture([\PHP_BINARY, \dirname(__DIR__, 3).'/vendor/bin/phpunit', '--no-configuration', $dir.'/tests'], $dir);

        self::assertSame(0, $result->exitCode, $result->output);
        self::assertStringContainsString('OK (1 test, 3 assertions)', $result->output);
    }

    public function testAPlannedBundleKeepsItsBundleClassAsItsTest(): void
    {
        $dir = $this->temporaryDirectory().'/quests-bundle';

        $files = new SkeletonWriter('2026')->write(PackageName::parse('tilttek/quests-bundle'), $dir, 'Quests for Symfony.', planned: true);

        self::assertContains('tests/Unit/TilttekQuestsBundleTest.php', $files);
        self::assertNotContains('tests/Unit/PackageTest.php', $files);
        self::assertStringContainsString("| `TilttekQuestsBundle` and its configuration `tilttek_quests` | Implemented and tested |\n| The code described above | Being written |\n", (string) file_get_contents($dir.'/README.md'));
        self::assertStringContainsString("tilttek/quests-bundle is a Symfony bundle.\n\n**Being written.**", (string) file_get_contents($dir.'/README.md'));
        self::assertStringContainsString("**En cours d'écriture.** Le paquet contient son squelette ; le code décrit ci-dessous n'est pas encore écrit.\n\nQuests for Symfony.\n", (string) file_get_contents($dir.'/README.fr.md'));
        self::assertSame([], new DocsChecker()->check(Package::fromDirectory($dir, '', PackageRole::Root)));
        self::assertSame([], new TranslationChecker()->check(Package::fromDirectory($dir, '', PackageRole::Root)));
    }

    public function testThePackagesItRequires(): void
    {
        $dir = $this->temporaryDirectory().'/x';

        new SkeletonWriter('2026')->write(PackageName::parse('tilttek/x'), $dir, 'X.', requires: ['tilttek/dice', 'phil/geo', 'symfony/uid:^8.2']);

        $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertSame(['php' => '>=8.5', 'phil/geo' => '^0.1@dev', 'symfony/uid' => '^8.2', 'tilttek/dice' => '^0.1@dev'], $composer['require']);
        self::assertStringEndsWith("\n}\n", (string) file_get_contents($dir.'/composer.json'));
        self::assertStringContainsString('"Tilttek\\\\X\\\\": "src/"', (string) file_get_contents($dir.'/composer.json'));
        self::assertStringContainsString(<<<'PHP'
            use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
            use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
            PHP, (string) file_get_contents($dir.'/composer-dependency-analyser.php'));
        self::assertStringContainsString(<<<'PHP'
                ->addPathToScan(__DIR__.'/tests', isDev: true)
                // The packages the code described in the README will use, required before it uses them.
                ->ignoreErrorsOnPackages(['tilttek/dice', 'phil/geo', 'symfony/uid'], [ErrorType::UNUSED_DEPENDENCY]);
            PHP, (string) file_get_contents($dir.'/composer-dependency-analyser.php'));
    }

    public function testABundleKeepsTheRequiresOfItsSkeleton(): void
    {
        $dir = $this->temporaryDirectory().'/x';

        new SkeletonWriter('2026')->write(PackageName::parse('phil/x-bundle'), $dir, 'X.', requires: ['phil/x']);

        $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertSame(['php', 'phil/x', 'symfony/config', 'symfony/dependency-injection'], array_keys((array) $composer['require']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wrongRequires(): iterable
    {
        yield 'capitals' => ['Phil/Geo'];
        yield 'something before' => ['-phil/geo'];
        yield 'something after' => ['phil/geo extra'];
    }

    #[DataProvider('wrongRequires')]
    public function testAWrongPackageToRequireIsRefused(string $require): void
    {
        $this->expectException(InvalidPackageName::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a package to require, such as phil/geo or symfony/uid:^8.2.', $require));

        new SkeletonWriter('2026')->write(PackageName::parse('phil/x'), $this->temporaryDirectory().'/x', 'X.', requires: [$require]);
    }

    public function testTheTextOfComposerJsonKeepsItsAccentsAndSlashes(): void
    {
        $dir = $this->temporaryDirectory().'/x';

        new SkeletonWriter('2026')->write(PackageName::parse('phil/x'), $dir, 'Des cartes à jouer, en/hors ligne.', requires: ['phil/geo']);

        self::assertStringContainsString('"description": "Des cartes à jouer, en/hors ligne.",', (string) file_get_contents($dir.'/composer.json'));
    }

    public function testThePlannedSummaryIsTheFirstSentence(): void
    {
        $dir = $this->temporaryDirectory().'/x';

        new SkeletonWriter('2026')->write(PackageName::parse('phil/x'), $dir, "  First, à/b.  Second! Third?\nFourth.", planned: true);

        self::assertStringContainsString('"description": "First, à/b.",', (string) file_get_contents($dir.'/composer.json'));
        self::assertStringStartsWith("# phil/x\n\n**First, à/b.**\n", (string) file_get_contents($dir.'/README.md'));
    }

    public function testNoTemplateIsLeftUnfilled(): void
    {
        $dir = $this->temporaryDirectory();
        $written = [
            'bundle' => new SkeletonWriter('2026')->write(PackageName::parse('phil/x-bundle'), $dir.'/bundle', 'X.'),
            'library' => new SkeletonWriter('2026')->write(PackageName::parse('phil/x'), $dir.'/library', 'X.'),
            'planned' => new SkeletonWriter('2026')->write(PackageName::parse('phil/x'), $dir.'/planned', 'X.', planned: true, requires: ['phil/y']),
        ];

        foreach ($written as $set => $files) {
            foreach ($files as $file) {
                self::assertStringNotContainsString('{{', (string) file_get_contents($dir.'/'.$set.'/'.$file), $set.'/'.$file);
            }
        }

        self::assertDirectoryExists(DevTools::resource('skeleton/common'));
    }

    public function testANonEmptyDirectoryIsRefused(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/README.md', '');

        $this->expectException(InvalidPackageName::class);
        $this->expectExceptionMessageIsOrContains('already exists and is not empty.');

        new SkeletonWriter('2026')->write(PackageName::parse('phil/x'), $dir, 'X.');
    }

    public function testAnEmptyDirectoryIsUsed(): void
    {
        $dir = $this->temporaryDirectory();

        self::assertNotSame([], new SkeletonWriter('2026')->write(PackageName::parse('phil/x'), $dir, 'X.'));
    }

    public function testTheLibraryFiles(): void
    {
        $dir = $this->temporaryDirectory().'/library';
        $files = new SkeletonWriter('2026')->write(PackageName::parse('phil/example'), $dir, 'An example.');

        $this->assertSameFiles(\dirname(__DIR__, 2).'/Fixtures/skeleton/library', $dir, $files);
    }

    public function testTheBundleFiles(): void
    {
        $dir = $this->temporaryDirectory().'/bundle';
        $files = new SkeletonWriter('2026')->write(PackageName::parse('tilttek/example-bundle'), $dir, 'An example bundle.', true);

        $this->assertSameFiles(\dirname(__DIR__, 2).'/Fixtures/skeleton/bundle', $dir, $files);
    }

    /**
     * @param list<string> $files
     */
    private function assertSameFiles(string $expected, string $actual, array $files): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($expected, FilesystemIterator::SKIP_DOTS));
        $golden = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $golden[] = substr($file->getPathname(), \strlen($expected) + 1);
        }

        sort($golden);
        self::assertSame($golden, $files);

        foreach ($files as $file) {
            self::assertFileEquals($expected.'/'.$file, $actual.'/'.$file, $file);
        }
    }
}
