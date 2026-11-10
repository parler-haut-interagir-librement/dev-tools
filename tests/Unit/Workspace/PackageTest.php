<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Workspace;

use Phil\DevTools\Exception\WorkspaceNotFound;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\PackageRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Package::class)]
final class PackageTest extends TestCase
{
    use TemporaryDirectory;

    public function testTheFilesUnderADirectory(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', '{"name": "phil/x"}');

        foreach (['doc/b.md', 'doc/a/c.md', 'doc/vendor/no.md', 'doc/var/no.md', 'doc/node_modules/no.md', 'doc/.hidden/no.md', 'vendor/no.md', 'doc/.dotfile', 'doc/my-var/yes.md', 'doc/xvendor/yes.md'] as $file) {
            $this->put($dir.'/'.$file, '');
        }

        $package = Package::fromDirectory($dir, '', PackageRole::Root);

        self::assertSame(['doc/.dotfile', 'doc/a/c.md', 'doc/b.md', 'doc/my-var/yes.md', 'doc/xvendor/yes.md'], $package->filesUnder('doc'));
        self::assertSame(['composer.json', 'doc/.dotfile', 'doc/a/c.md', 'doc/b.md', 'doc/my-var/yes.md', 'doc/xvendor/yes.md'], $package->filesUnder(''));
        self::assertSame([], $package->filesUnder('missing'));
    }

    public function testItReadsItsComposerJson(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', json_encode([
            'name' => 'tilttek/example-bundle',
            'type' => 'symfony-bundle',
            'require' => ['php' => '>=8.5', 'ext-intl' => '*', 'phil/resource' => '^0.1', '0' => 'odd', 'psr/clock' => '^1.0'],
            'require-dev' => ['phpunit/phpunit' => '^13.4'],
            'autoload' => ['psr-4' => ['Tilttek\\ExampleBundle\\' => 'src/', 'Tilttek\\Extra\\' => ['lib/', 'other/', 3], '' => 'root/', 'Tilttek\\Root\\' => '', '\\' => 'ignored/']],
        ], \JSON_THROW_ON_ERROR));
        mkdir($dir.'/src');
        mkdir($dir.'/lib');
        $this->put($dir.'/phil-dev.php', "<?php return ['paths' => ['src', 'lib', 'missing']];");

        $package = Package::fromDirectory($dir.'/', 'packages/example-bundle', PackageRole::Member);

        self::assertSame($dir, $package->dir);
        self::assertSame('tilttek', $package->vendor());
        self::assertTrue($package->isBundle());
        self::assertSame('symfony-bundle', $package->type);
        self::assertFalse($package->isRoot());
        self::assertSame(['phil/resource', 'psr/clock'], $package->requires);
        self::assertSame(['phpunit/phpunit'], $package->devRequires);
        self::assertSame(['Tilttek\\ExampleBundle' => ['src'], 'Tilttek\\Extra' => ['lib', 'other'], 'Tilttek\\Root' => ['.']], $package->namespaces);
        self::assertSame([$dir.'/src', $dir.'/lib', $dir], $package->sourceDirectories());
        self::assertSame([$dir.'/src', $dir.'/lib'], $package->paths());
        self::assertSame($dir, $package->path(''));
        self::assertSame($dir, $package->path('.'));
        self::assertSame($dir.'/a/b', $package->path('/a/b'));
        self::assertSame($dir.'/src', $package->directory('src'));
        self::assertNull($package->directory('tests'));
    }

    public function testDefaultsWhenComposerJsonIsSparse(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', '{"name": 3, "type": [], "autoload": {"psr-4": "x"}, "require": "x"}');

        $package = Package::fromDirectory($dir, '', PackageRole::Root);

        self::assertSame(basename($dir), $package->name);
        self::assertSame('library', $package->type);
        self::assertSame([], $package->namespaces);
        self::assertSame([], $package->requires);
        self::assertFalse($package->isBundle());
        self::assertTrue($package->isRoot());
    }

    public function testANamespaceEndingWithBundleIsABundle(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', '{"name": "phil/x", "autoload": {"psr-4": {"Phil\\\\XBundle\\\\": "src/"}}}');

        self::assertTrue(Package::fromDirectory($dir, '', PackageRole::Root)->isBundle());
    }

    public function testTheDefaultPaths(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', '{"name": "phil/x"}');
        mkdir($dir.'/src');
        mkdir($dir.'/tests');

        self::assertSame([$dir.'/src', $dir.'/tests'], Package::fromDirectory($dir, '', PackageRole::Root)->paths());
    }

    public function testATypeMakesABundle(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', '{"name": "phil/x", "type": "symfony-bundle", "autoload": {"psr-4": {"Phil\\\\X\\\\": "src/"}}}');

        self::assertTrue(Package::fromDirectory($dir, '', PackageRole::Root)->isBundle());
    }

    public function testALibraryIsNoBundle(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', '{"name": "phil/x", "autoload": {"psr-4": {"Phil\\\\X\\\\": "src/", "Phil\\\\Bundles\\\\": "b/"}}}');

        self::assertFalse(Package::fromDirectory($dir, '', PackageRole::Root)->isBundle());
    }

    public function testItNeedsAComposerJson(): void
    {
        $this->expectException(WorkspaceNotFound::class);

        Package::fromDirectory($this->temporaryDirectory(), '', PackageRole::Root);
    }

    public function testItNeedsValidJson(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', '{');

        $this->expectException(WorkspaceNotFound::class);
        $this->expectExceptionMessageIsOrContains('is not valid JSON');

        Package::fromDirectory($dir, '', PackageRole::Root);
    }

    public function testItNeedsAnObject(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', '"text"');

        $this->expectException(WorkspaceNotFound::class);
        $this->expectExceptionMessageIsOrContains('is not a JSON object.');

        Package::fromDirectory($dir, '', PackageRole::Root);
    }
}
