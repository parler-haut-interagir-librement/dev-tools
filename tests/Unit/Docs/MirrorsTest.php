<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Docs;

use Phil\DevTools\Docs\Mirrors;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\PackageRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Mirrors::class)]
final class MirrorsTest extends TestCase
{
    use TemporaryDirectory;

    public function testThePaths(): void
    {
        self::assertSame('README.fr.md', Mirrors::mirrorOf('README.md'));
        self::assertSame('doc/fr/a/b.md', Mirrors::mirrorOf('doc/a/b.md'));
        self::assertSame('README.md', Mirrors::sourceOf('README.fr.md'));
        self::assertSame('doc/a/b.md', Mirrors::sourceOf('doc/fr/a/b.md'));
        self::assertSame('CHANGELOG.fr.md', Mirrors::mirrorOf('CHANGELOG.md'));
        self::assertSame('CHANGELOG.md', Mirrors::sourceOf('CHANGELOG.fr.md'));
    }

    public function testWhatIsATwin(): void
    {
        self::assertTrue(Mirrors::isTwin('CHANGELOG.fr.md'));
        self::assertTrue(Mirrors::isTwin('README.fr.md'));
        self::assertTrue(Mirrors::isMirror('SECURITY.fr.md'));
        self::assertTrue(Mirrors::isFrench('SECURITY.fr.md'));
        self::assertFalse(Mirrors::isTwin('AGENTS.fr.md'));
        self::assertFalse(Mirrors::isTwin('LICENSE.fr.md'));
        self::assertFalse(Mirrors::isTwin('.fr.md'));
        self::assertFalse(Mirrors::isTwin('CHANGELOG.md'));
        self::assertFalse(Mirrors::isTwin('doc/CHANGELOG.fr.md'));
        self::assertFalse(Mirrors::isMirror('doc/a.fr.md'));
        self::assertTrue(Mirrors::hasTwin('CHANGELOG.md'));
        self::assertTrue(Mirrors::hasTwin('README.md'));
        self::assertFalse(Mirrors::hasTwin('AGENTS.md'));
        self::assertFalse(Mirrors::hasTwin('LICENSE.md'));
        self::assertFalse(Mirrors::hasTwin('CHANGELOG.fr.md'));
        self::assertFalse(Mirrors::hasTwin('doc/a.md'));
        self::assertFalse(Mirrors::hasTwin('LICENSE'));
    }

    public function testTheTwinsOfAPackage(): void
    {
        $package = $this->packageWith(['README.md', 'README.fr.md', 'SECURITY.md', 'CHANGELOG.md', 'CHANGELOG.fr.md', 'AGENTS.md', 'LICENSE', 'LICENSE.md', 'ORPHAN.fr.md', 'doc/a.md', 'doc/fr/a.md', 'notes.txt']);
        mkdir($package->path('folder.md'));

        self::assertSame(['CHANGELOG.md', 'SECURITY.md'], Mirrors::twinSources($package));
        self::assertSame(['CHANGELOG.md', 'README.md', 'SECURITY.md'], Mirrors::twinSources($package, readme: true));
        self::assertSame(['CHANGELOG.fr.md', 'ORPHAN.fr.md'], Mirrors::twins($package));
        self::assertSame(['CHANGELOG.fr.md', 'ORPHAN.fr.md', 'README.fr.md'], Mirrors::twins($package, readme: true));
        self::assertSame(['README.md', 'doc/a.md'], Mirrors::sources($package));
        self::assertSame(['README.fr.md', 'doc/fr/a.md'], Mirrors::mirrors($package));
        self::assertSame(['README.fr.md', 'doc/fr/a.md', 'CHANGELOG.fr.md', 'ORPHAN.fr.md'], Mirrors::french($package));
    }

    public function testWhatIsAMirror(): void
    {
        self::assertTrue(Mirrors::isMirror('README.fr.md'));
        self::assertTrue(Mirrors::isMirror('doc/fr/a.md'));
        self::assertFalse(Mirrors::isMirror('README.md'));
        self::assertFalse(Mirrors::isMirror('doc/a.md'));
        self::assertFalse(Mirrors::isMirror('doc/fr/a.png'));
        self::assertFalse(Mirrors::isMirror('x/doc/fr/a.md'));
        self::assertFalse(Mirrors::isMirror('doc/fr-glossary.md'));
        self::assertTrue(Mirrors::isFrench('doc/fr-glossary.md'));
        self::assertTrue(Mirrors::isFrench('doc/fr/a.md'));
        self::assertFalse(Mirrors::isFrench('doc/a.md'));
    }

    public function testTheDocumentsOfAPackage(): void
    {
        $package = $this->packageWith(['README.md', 'README.fr.md', 'doc/a.md', 'doc/sub/b.md', 'doc/image.png', 'doc/fr/a.md', 'doc/fr/image.png', 'doc/fr-glossary.md', 'CHANGELOG.md']);

        self::assertSame(['README.md', 'doc/a.md', 'doc/sub/b.md'], Mirrors::sources($package));
        self::assertSame(['README.fr.md', 'doc/fr/a.md'], Mirrors::mirrors($package));
        self::assertSame(['README.fr.md', 'doc/fr/a.md', 'doc/fr-glossary.md'], Mirrors::french($package));
    }

    public function testAPackageWithoutTheRootDocuments(): void
    {
        $package = $this->packageWith(['doc/a.md']);

        self::assertSame(['doc/a.md'], Mirrors::sources($package));
        self::assertSame([], Mirrors::mirrors($package));
        self::assertSame([], Mirrors::french($package));
    }

    /**
     * @param list<string> $files
     */
    private function packageWith(array $files): Package
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', '{"name": "phil/x"}');

        foreach ($files as $file) {
            $this->put($dir.'/'.$file, '');
        }

        return Package::fromDirectory($dir, '', PackageRole::Root);
    }
}
