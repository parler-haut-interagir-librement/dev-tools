<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use DateTimeImmutable;
use Phil\DevTools\Command\NewCommand;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NewCommand::class)]
final class NewCommandTest extends CommandTestCase
{
    public function testALibraryInAMonorepo(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root.'/packages');
        $tester = $this->tester($root, 'new');

        self::assertSame(0, $tester->execute(['package' => 'tilttek/cards', '--description' => 'Decks of cards.']));
        self::assertStringStartsWith("tilttek/cards written in packages/tilttek-cards:\n  CHANGELOG.md\n  CODE_OF_CONDUCT.md\n", $tester->getDisplay());
        self::assertStringEndsWith("\n  src/.gitkeep\n  tests/Unit/.gitkeep\n", $tester->getDisplay());
        self::assertStringContainsString('Decks of cards.', (string) file_get_contents($root.'/packages/tilttek-cards/README.md'));
        self::assertStringContainsString('Copyright (c) '.new DateTimeImmutable()->format('Y').' ', (string) file_get_contents($root.'/packages/tilttek-cards/LICENSE'));
    }

    public function testABundleBesideItsLibrary(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root.'/packages/cards', 0o777, true);

        $this->tester($root, 'new')->execute(['package' => 'phil/cards', '--bundle' => true, '--year' => '2030']);

        self::assertStringContainsString('integrates `phil/cards` with Symfony', (string) file_get_contents($root.'/packages/cards-bundle/README.md'));
        self::assertStringContainsString('To be described.', (string) file_get_contents($root.'/packages/cards-bundle/composer.json'));
        self::assertStringContainsString('Copyright (c) 2030 ', (string) file_get_contents($root.'/packages/cards-bundle/LICENSE'));
    }

    public function testABundleWithoutItsLibrary(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root.'/packages');

        $this->tester($root, 'new')->execute(['package' => 'phil/cards-bundle']);

        self::assertStringContainsString('phil/cards-bundle is a Symfony bundle.', (string) file_get_contents($root.'/packages/cards-bundle/README.md'));
    }

    public function testADirectoryOfItsOwn(): void
    {
        $root = $this->temporaryDirectory();

        $this->tester($root, 'new')->execute(['package' => 'phil/cards', '--dir' => 'lib/cards']);
        $this->tester($root, 'new')->execute(['package' => 'phil/dice']);

        self::assertFileExists($root.'/lib/cards/composer.json');
        self::assertFileExists($root.'/dice/composer.json');
    }

    public function testAPlannedPackage(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root.'/packages');

        self::assertSame(0, $this->tester($root, 'new')->execute([
            'package' => 'tilttek/cards',
            '--planned' => true,
            '--requires' => 'tilttek/dice, phil/geo, ',
            '--title' => 'Cards',
            '--description' => 'Decks of cards. Shuffled, dealt and drawn.',
            '--description-fr' => 'Des paquets de cartes. Mêlés, distribués et piochés.',
        ]));

        $dir = $root.'/packages/tilttek-cards';
        self::assertStringStartsWith("# Cards\n\n**Decks of cards.**\n", (string) file_get_contents($dir.'/README.md'));
        self::assertStringContainsString('Des paquets de cartes. Mêlés, distribués et piochés.', (string) file_get_contents($dir.'/README.fr.md'));
        self::assertStringContainsString("'status' => 'planned'", (string) file_get_contents($dir.'/phil-dev.php'));
        self::assertStringContainsString('"tilttek/dice": "^0.1@dev"', (string) file_get_contents($dir.'/composer.json'));
        self::assertStringContainsString('"phil/geo": "^0.1@dev"', (string) file_get_contents($dir.'/composer.json'));
        self::assertFileExists($dir.'/tests/Unit/PackageTest.php');
    }

    public function testAnActivePackageByDefault(): void
    {
        $root = $this->temporaryDirectory();

        $this->tester($root, 'new')->execute(['package' => 'phil/cards', '--requires' => '']);

        self::assertStringNotContainsString("'status'", (string) file_get_contents($root.'/cards/phil-dev.php'));
        self::assertStringStartsWith("# phil/cards\n", (string) file_get_contents($root.'/cards/README.md'));
        self::assertFileExists($root.'/cards/tests/Unit/.gitkeep');
    }
}
