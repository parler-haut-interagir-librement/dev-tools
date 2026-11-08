<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\NormalizeCommand;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NormalizeCommand::class)]
final class NormalizeCommandTest extends CommandTestCase
{
    public function testEveryComposerJsonIsChecked(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'normalize', [new ProcessResult(0), new ProcessResult(1)]);

        self::assertSame(1, $this->withEnvironment('COMPOSER_BINARY', '', static fn (): int => $tester->execute([])));

        $prefix = 'composer --working-dir='.$root.'/vendor-bin/composer-normalize normalize --no-check-lock --no-update-lock --indent-size=4 --indent-style=space --dry-run --diff ';
        self::assertSame([
            $prefix.$root.'/composer.json',
            $prefix.$root.'/packages/slug/composer.json',
            $prefix.$root.'/packages/slug-bundle/composer.json',
            $prefix.$root.'/packages/tilttek-dice/composer.json',
        ], $this->runner->commands());
        self::assertSame([$root, $root, $root, $root], array_column($this->runner->runs, 'directory'));
    }

    public function testFix(): void
    {
        $root = $this->package();
        $tester = $this->tester($root, 'normalize');

        self::assertSame(0, $this->withEnvironment('COMPOSER_BINARY', '/opt/composer.phar', static fn (): int => $tester->execute(['--fix' => true])));
        self::assertSame([\PHP_BINARY.' /opt/composer.phar --working-dir='.$root.'/vendor-bin/composer-normalize normalize --no-check-lock --no-update-lock --indent-size=4 --indent-style=space '.$root.'/composer.json'], $this->runner->commands());
    }

    public function testTheGateCanBeTurnedOff(): void
    {
        $root = $this->package(['gates' => ['normalize' => false]]);
        $tester = $this->tester($root, 'normalize');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("The normalize gate is turned off in phil-dev.php.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }
}
