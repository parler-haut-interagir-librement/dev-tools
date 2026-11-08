<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\AuditCommand;
use Phil\DevTools\Command\WorkspaceCommand;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AuditCommand::class)]
#[CoversClass(WorkspaceCommand::class)]
final class AuditCommandTest extends CommandTestCase
{
    public function testTheComposerOfTheScript(): void
    {
        $root = $this->package();
        $tester = $this->tester($root, 'audit', [new ProcessResult(1)]);

        self::assertSame(1, $this->withEnvironment('COMPOSER_BINARY', '/usr/local/bin/composer.phar', static fn (): int => $tester->execute([])));
        self::assertSame([\PHP_BINARY.' /usr/local/bin/composer.phar audit --no-interaction --abandoned=report'], $this->runner->commands());
        self::assertSame($root, $this->runner->runs[0]['directory']);
    }

    public function testTheComposerOfThePath(): void
    {
        $root = $this->package();
        $tester = $this->tester($root, 'audit');

        self::assertSame(0, $this->withEnvironment('COMPOSER_BINARY', '', static fn (): int => $tester->execute([])));
        self::assertSame(['composer audit --no-interaction --abandoned=report'], $this->runner->commands());
    }

    public function testTheGateCanBeTurnedOff(): void
    {
        $root = $this->package(['gates' => ['audit' => false]]);
        $tester = $this->tester($root, 'audit');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("The audit gate is turned off in phil-dev.php.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }
}
