<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\StampTranslationsCommand;
use Phil\DevTools\Docs\SourceStamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(StampTranslationsCommand::class)]
#[UsesClass(SourceStamp::class)]
final class StampTranslationsCommandTest extends CommandTestCase
{
    public function testItStampsThePackageNamed(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/packages/cards/README.md', '# New');
        $this->put($root.'/packages/dice/README.md', '# New');
        $tester = $this->tester($root, 'stamp-translations');

        self::assertSame(0, $tester->execute(['packages' => ['phil/cards']]));
        self::assertSame("packages/cards: README.fr.md\n1 stamped.\n", $tester->getDisplay());
        self::assertSame(SourceStamp::fingerprint('# New'), SourceStamp::read((string) file_get_contents($root.'/packages/cards/README.fr.md')));
        self::assertNotSame(SourceStamp::fingerprint('# New'), SourceStamp::read((string) file_get_contents($root.'/packages/dice/README.fr.md')));
    }

    public function testAPackageMustBeNamed(): void
    {
        $this->expectExceptionMessageIsOrContains('Not enough arguments');

        $this->tester($this->monorepo(), 'stamp-translations')->execute([]);
    }

    public function testItNarrowsToAFileByEitherPath(): void
    {
        $root = $this->monorepo();

        foreach (['README.md', 'doc/testing.md', 'doc/reference.md'] as $file) {
            file_put_contents($root.'/packages/cards/'.$file, "\nMore.\n", \FILE_APPEND);
        }

        $tester = $this->tester($root, 'stamp-translations');

        self::assertSame(0, $tester->execute(['packages' => ['cards'], '--file' => ['doc/testing.md', 'README.fr.md']]));
        self::assertSame("packages/cards: README.fr.md\npackages/cards: doc/fr/testing.md\n2 stamped.\n", $tester->getDisplay());
    }

    public function testAnOrphanIsLeftAlone(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/packages/cards/doc/fr/orphan.md', '# Orphelin');
        $tester = $this->tester($root, 'stamp-translations');

        self::assertSame(0, $tester->execute(['packages' => ['cards']]));
        self::assertSame("0 stamped.\n", $tester->getDisplay());
        self::assertSame('# Orphelin', file_get_contents($root.'/packages/cards/doc/fr/orphan.md'));
    }

    public function testAPackageOnItsOwn(): void
    {
        $root = $this->temporaryDirectory();
        $this->tester($root, 'new')->execute(['package' => 'phil/alone', '--dir' => '.']);
        $this->put($root.'/README.md', '# New');
        $tester = $this->tester($root, 'stamp-translations');

        self::assertSame(0, $tester->execute(['packages' => ['phil/alone']]));
        self::assertSame("phil/alone: README.fr.md\n1 stamped.\n", $tester->getDisplay());
    }

    public function testItStampsTheTwinsAtTheRootOfAPackage(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/packages/cards/CHANGELOG.fr.md', '# Journal');
        $this->put($root.'/packages/cards/ORPHAN.fr.md', '# Orphelin');
        $tester = $this->tester($root, 'stamp-translations');

        self::assertSame(0, $tester->execute(['packages' => ['cards']]));
        self::assertSame("packages/cards: CHANGELOG.fr.md\n1 stamped.\n", $tester->getDisplay());
        self::assertSame(SourceStamp::fingerprint((string) file_get_contents($root.'/packages/cards/CHANGELOG.md')), SourceStamp::read((string) file_get_contents($root.'/packages/cards/CHANGELOG.fr.md')));
        self::assertSame('# Orphelin', file_get_contents($root.'/packages/cards/ORPHAN.fr.md'));
    }

    public function testItStampsTheRootOfAMonorepoWhenNamed(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/README.md', '# Root');
        $this->put($root.'/README.fr.md', '# Racine');
        $this->put($root.'/SECURITY.md', '# Security');
        $this->put($root.'/SECURITY.fr.md', '# Sécurité');
        $this->put($root.'/packages/cards/CHANGELOG.fr.md', '# Journal');

        $tester = $this->tester($root, 'stamp-translations');
        self::assertSame(0, $tester->execute(['packages' => ['.'], '--file' => ['SECURITY.md']]));
        self::assertSame("phil/root: SECURITY.fr.md\n1 stamped.\n", $tester->getDisplay());
        self::assertNull(SourceStamp::read((string) file_get_contents($root.'/packages/cards/CHANGELOG.fr.md')));

        $tester = $this->tester($root, 'stamp-translations');
        self::assertSame(0, $tester->execute(['packages' => ['phil/root', 'cards']]));
        self::assertSame("phil/root: README.fr.md\npackages/cards: CHANGELOG.fr.md\n2 stamped.\n", $tester->getDisplay());
        self::assertSame(SourceStamp::fingerprint('# Root'), SourceStamp::read((string) file_get_contents($root.'/README.fr.md')));
    }

    public function testTheRootOfAMonorepoIsLeftAloneWhenOnlyAMemberIsNamed(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/README.md', '# Root');
        $this->put($root.'/README.fr.md', '# Racine');
        $this->put($root.'/packages/cards/CHANGELOG.fr.md', '# Journal');

        $tester = $this->tester($root, 'stamp-translations');

        self::assertSame(0, $tester->execute(['packages' => ['cards']]));
        self::assertSame("packages/cards: CHANGELOG.fr.md\n1 stamped.\n", $tester->getDisplay());
        self::assertNull(SourceStamp::read((string) file_get_contents($root.'/README.fr.md')));
    }

    private function monorepo(): string
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/phil-dev.php', '<?php return [];');
        mkdir($root.'/packages');

        foreach (['cards', 'dice'] as $name) {
            $this->tester($root, 'new')->execute(['package' => 'phil/'.$name]);
        }

        return $root;
    }
}
