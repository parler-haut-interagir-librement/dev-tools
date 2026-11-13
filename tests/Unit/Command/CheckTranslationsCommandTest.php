<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\CheckTranslationsCommand;
use Phil\DevTools\Workspace\Workspace;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(CheckTranslationsCommand::class)]
final class CheckTranslationsCommandTest extends CommandTestCase
{
    public function testAPackageFreshFromTheSkeletonPasses(): void
    {
        $tester = $this->tester($this->monorepo(), 'check-translations');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("The translations pass every check.\n", $tester->getDisplay());
    }

    public function testAPackageFreshFromTheSkeletonPassesBeforeItsFrenchDocumentation(): void
    {
        $root = $this->monorepo(null, skeleton: true);
        unlink($root.'/packages/dice/README.fr.md');
        unlink($root.'/packages/dice/doc/fr/testing.md');
        $tester = $this->tester($root, 'check-translations');

        self::assertSame(0, $tester->execute([]));
        self::assertSame(<<<'TEXT'
            packages/dice: advisory, 2 translation-missing
            0 blocking, 0 late, 2 advisory.

            TEXT, $tester->getDisplay());
    }

    public function testAPackageBlocksOnceItIsPublic(): void
    {
        $root = $this->monorepo(null, skeleton: true);
        unlink($root.'/packages/dice/README.fr.md');
        $this->put($root.'/packages/dice/phil-dev.php', "<?php return ['paths' => ['src', 'tests'], 'docs' => ['public' => true]];");
        $tester = $this->tester($root, 'check-translations');

        self::assertSame(1, $tester->execute([]));
        self::assertStringEndsWith("1 blocking, 0 late, 0 advisory.\n", $tester->getDisplay());
    }

    public function testABlockingPackageFails(): void
    {
        $root = $this->monorepo();
        unlink($root.'/packages/dice/doc/fr/testing.md');
        $this->breakCode($root.'/packages/cards/doc/fr/testing.md');
        $tester = $this->tester($root, 'check-translations');

        self::assertSame(1, $tester->execute([]));
        self::assertSame(<<<'TEXT'
            packages/cards: [translation-code] doc/fr/testing.md:6: differs from the code block at line 5 of the English
            packages/dice: [translation-missing] doc/testing.md: no translation at doc/fr/testing.md
            2 blocking, 0 late, 0 advisory.

            TEXT, $tester->getDisplay());
    }

    public function testALateTranslationIsReportedAndPasses(): void
    {
        $root = $this->monorepo();
        file_put_contents($root.'/packages/dice/doc/testing.md', "\nMore.\n", \FILE_APPEND);
        $tester = $this->tester($root, 'check-translations');

        self::assertSame(0, $tester->execute([]));
        self::assertSame(<<<'TEXT'
            packages/dice: [translation-late] doc/fr/testing.md:1: its English document has changed since
            0 blocking, 1 late, 0 advisory.

            TEXT, $tester->getDisplay());
    }

    public function testAnAdvisoryPackageIsSummedUpAndPasses(): void
    {
        $root = $this->monorepo('advisory');
        unlink($root.'/packages/dice/doc/fr/extension-points.md');
        unlink($root.'/packages/dice/doc/fr/installation.md');
        file_put_contents($root.'/packages/dice/doc/testing.md', "\nMore.\n", \FILE_APPEND);
        $tester = $this->tester($root, 'check-translations');

        self::assertSame(0, $tester->execute([]));
        self::assertSame(<<<'TEXT'
            packages/dice: advisory, 1 translation-late, 2 translation-missing
            0 blocking, 0 late, 3 advisory.

            TEXT, $tester->getDisplay());
    }

    public function testTheAdvisoryFindingsOfEveryPackageAreCounted(): void
    {
        $root = $this->monorepo('advisory');
        unlink($root.'/packages/cards/README.fr.md');
        unlink($root.'/packages/dice/README.fr.md');
        $tester = $this->tester($root, 'check-translations');

        self::assertSame(0, $tester->execute([]));
        self::assertStringEndsWith("0 blocking, 0 late, 2 advisory.\n", $tester->getDisplay());
    }

    public function testLateAndBlockingFindingsTogether(): void
    {
        $root = $this->monorepo();
        unlink($root.'/packages/cards/doc/fr/testing.md');
        file_put_contents($root.'/packages/cards/doc/installation.md', "\nMore.\n", \FILE_APPEND);
        $tester = $this->tester($root, 'check-translations');

        self::assertSame(1, $tester->execute([]));
        self::assertStringEndsWith("1 blocking, 1 late, 0 advisory.\n", $tester->getDisplay());
    }

    public function testLateAndAdvisoryFindingsTogether(): void
    {
        $root = $this->monorepo('advisory');
        $this->put($root.'/packages/cards/phil-dev.php', "<?php return ['docs' => ['translations' => 'blocking']];");
        file_put_contents($root.'/packages/cards/doc/installation.md', "\nMore.\n", \FILE_APPEND);
        unlink($root.'/packages/dice/doc/fr/testing.md');
        $tester = $this->tester($root, 'check-translations');

        self::assertSame(0, $tester->execute([]));
        self::assertStringEndsWith("0 blocking, 1 late, 1 advisory.\n", $tester->getDisplay());
    }

    public function testAnAdvisoryPackageListsItsFindingsWhenVerbose(): void
    {
        $root = $this->monorepo('advisory');
        unlink($root.'/packages/dice/doc/fr/testing.md');
        $tester = $this->tester($root, 'check-translations');

        self::assertSame(0, $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));
        self::assertSame(<<<'TEXT'
            packages/dice: advisory [translation-missing] doc/testing.md: no translation at doc/fr/testing.md
            0 blocking, 0 late, 1 advisory.

            TEXT, $tester->getDisplay());
    }

    public function testAPackageTranslatedInAnAdvisoryRootBlocks(): void
    {
        $root = $this->monorepo('advisory');
        unlink($root.'/packages/cards/doc/fr/testing.md');
        $this->put($root.'/packages/cards/phil-dev.php', "<?php return ['docs' => ['translations' => 'blocking']];");

        self::assertSame(1, $this->tester($root, 'check-translations')->execute([]));
    }

    public function testNarrowedByNameAndByTheEnvironment(): void
    {
        $root = $this->monorepo();
        unlink($root.'/packages/dice/doc/fr/testing.md');

        self::assertSame(0, $this->tester($root, 'check-translations')->execute(['packages' => ['phil/cards']]));
        self::assertSame(1, $this->tester($root, 'check-translations')->execute(['packages' => ['dice']]));
        self::assertSame(0, $this->withEnvironment(Workspace::PACKAGES_VARIABLE, 'cards', fn (): int => $this->tester($root, 'check-translations')->execute([])));
        self::assertSame(1, $this->withEnvironment(Workspace::PACKAGES_VARIABLE, 'dice', fn (): int => $this->tester($root, 'check-translations')->execute([])));
    }

    public function testAPackageOnItsOwnIsNamed(): void
    {
        $root = $this->temporaryDirectory();
        $this->tester($root, 'new')->execute(['package' => 'phil/alone', '--dir' => '.']);
        unlink($root.'/README.fr.md');
        $tester = $this->tester($root, 'check-translations');

        self::assertSame(0, $tester->execute([]));

        $this->put($root.'/phil-dev.php', "<?php return ['paths' => ['src', 'tests'], 'docs' => ['public' => true]];");
        $tester = $this->tester($root, 'check-translations');

        self::assertSame(1, $tester->execute([]));
        self::assertSame("phil/alone: [translation-missing] README.md: no translation at README.fr.md\n1 blocking, 0 late, 0 advisory.\n", $tester->getDisplay());
    }

    private function breakCode(string $file): void
    {
        file_put_contents($file, str_replace('composer install', 'composer update', (string) file_get_contents($file)));
    }

    /**
     * @param string|null $mode     the root's docs.translations, none when null
     * @param bool        $skeleton whether the packages keep the phil-dev.php the skeleton wrote
     */
    private function monorepo(?string $mode = 'blocking', bool $skeleton = false): string
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/phil-dev.php', null === $mode ? '<?php return [];' : "<?php return ['docs' => ['translations' => '".$mode."']];");
        mkdir($root.'/packages');

        foreach (['cards', 'dice'] as $name) {
            $this->tester($root, 'new')->execute(['package' => 'phil/'.$name]);

            if (!$skeleton) {
                $this->put($root.'/packages/'.$name.'/phil-dev.php', "<?php return ['paths' => ['src', 'tests']];");
            }
        }

        return $root;
    }
}
