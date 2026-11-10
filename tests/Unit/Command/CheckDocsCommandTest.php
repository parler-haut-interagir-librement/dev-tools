<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\CheckDocsCommand;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CheckDocsCommand::class)]
final class CheckDocsCommandTest extends CommandTestCase
{
    public function testEveryMemberPasses(): void
    {
        $root = $this->monorepo();
        $tester = $this->tester($root, 'check-docs');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("The documentation passes every check.\n", $tester->getDisplay());
    }

    public function testTheFindingsOfEachMember(): void
    {
        $root = $this->monorepo();
        file_put_contents($root.'/packages/cards/src/Card.php', "<?php // for the SecretSite\n");
        unlink($root.'/packages/dice/SECURITY.md');
        $tester = $this->tester($root, 'check-docs');

        self::assertSame(1, $tester->execute(['--forbid' => ['/SecretSite/']]));
        self::assertSame(<<<'TEXT'
            packages/cards: [forbidden-term] src/Card.php:1: "SecretSite" matches /\bSecret\w+/
            packages/cards: [forbidden-term] src/Card.php:1: "SecretSite" matches /SecretSite/
            packages/dice: [missing-file] SECURITY.md: every package has it
            packages/dice: [broken-link] README.md:35: SECURITY.md
            packages/dice: [broken-link] README.fr.md:37: SECURITY.md
            packages/dice: [broken-link] CONTRIBUTING.md:12: SECURITY.md
            6 findings.

            TEXT, $tester->getDisplay());
    }

    public function testOneMemberByName(): void
    {
        $root = $this->monorepo();
        unlink($root.'/packages/dice/SECURITY.md');
        $tester = $this->tester($root, 'check-docs');

        self::assertSame(0, $tester->execute(['packages' => ['phil/cards']]));
        self::assertSame(1, $this->tester($root, 'check-docs')->execute(['packages' => ['dice']]));
    }

    public function testAPackageOnItsOwn(): void
    {
        $root = $this->temporaryDirectory();
        $this->tester($root, 'new')->execute(['package' => 'phil/alone', '--dir' => '.']);
        unlink($root.'/doc/extension-points.md');
        $this->put($root.'/README.md', str_replace('| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |'."\n", '', (string) file_get_contents($root.'/README.md')));
        $tester = $this->tester($root, 'check-docs');

        self::assertSame(1, $tester->execute([]));
        self::assertSame("phil/alone: [missing-file] doc/extension-points.md: every package has it\n1 finding.\n", $tester->getDisplay());
    }

    private function monorepo(): string
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/phil-dev.php', "<?php return ['docs' => ['forbidden' => ['/\\\\bSecret\\\\w+/']]];");
        mkdir($root.'/packages');
        $this->tester($root, 'new')->execute(['package' => 'phil/cards']);
        $this->tester($root, 'new')->execute(['package' => 'phil/dice']);

        return $root;
    }
}
