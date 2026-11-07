<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Skeleton;

use FilesystemIterator;
use Phil\DevTools\DevTools;
use Phil\DevTools\Docs\DocsChecker;
use Phil\DevTools\Exception\InvalidPackageName;
use Phil\DevTools\Skeleton\PackageName;
use Phil\DevTools\Skeleton\SkeletonWriter;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\PackageRole;
use PHPUnit\Framework\Attributes\CoversClass;
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
            'CHANGELOG.md', 'CODE_OF_CONDUCT.md', 'CONTRIBUTING.md', 'LICENSE', 'README.md', 'SECURITY.md',
            'composer-dependency-analyser.php', 'composer.json',
            'doc/extension-points.md', 'doc/installation.md', 'doc/reference.md', 'doc/testing.md',
            'phil-dev.php', 'src/.gitkeep', 'tests/Unit/.gitkeep',
        ], $files);

        $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertSame('phil/opening-hours', $composer['name']);
        self::assertSame('library', $composer['type']);
        self::assertSame('Opening hours as values.', $composer['description']);
        self::assertSame(['psr-4' => ['Phil\\OpeningHours\\' => 'src/']], $composer['autoload']);
        self::assertStringContainsString('Copyright (c) 2027 Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project', (string) file_get_contents($dir.'/LICENSE'));
        self::assertStringContainsString('one of the open packages of [Parler Haut Interagir Librement]', (string) file_get_contents($dir.'/CONTRIBUTING.md'));
        self::assertStringNotContainsString('{{', (string) file_get_contents($dir.'/README.md'));

        // The skeleton passes the documentation checks as it is.
        self::assertSame([], new DocsChecker()->check(Package::fromDirectory($dir, '', PackageRole::Root)));
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
    }

    public function testABundleWithoutItsLibrary(): void
    {
        $dir = $this->temporaryDirectory().'/x';

        new SkeletonWriter('2026')->write(PackageName::parse('phil/menu-bundle'), $dir, 'Menus.');

        self::assertStringContainsString('phil/menu-bundle is a Symfony bundle.', (string) file_get_contents($dir.'/README.md'));
    }

    public function testNoTemplateIsLeftUnfilled(): void
    {
        $dir = $this->temporaryDirectory();
        $written = [
            'bundle' => new SkeletonWriter('2026')->write(PackageName::parse('phil/x-bundle'), $dir.'/bundle', 'X.'),
            'library' => new SkeletonWriter('2026')->write(PackageName::parse('phil/x'), $dir.'/library', 'X.'),
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
