<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\TestCommand;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TestCommand::class)]
final class TestCommandTest extends CommandTestCase
{
    public function testParaTestWhenItIsInstalled(): void
    {
        $root = $this->package();
        $this->put($root.'/vendor/bin/paratest', '');
        $tester = $this->tester($root, 'test', [new ProcessResult(2)]);

        self::assertSame(2, $tester->execute(['--suite' => 'Unit', 'arguments' => ['--filter=X']]));
        self::assertSame([$root.'/vendor/bin/paratest --testsuite=Unit --filter=X'], $this->runner->commands());
        self::assertSame($root, $this->runner->runs[0]['directory']);
    }

    public function testPhpUnitWithoutParaTest(): void
    {
        $root = $this->package();

        self::assertSame(0, $this->tester($root, 'test')->execute(['--suite' => '']));
        self::assertSame([$root.'/vendor/bin/phpunit'], $this->runner->commands());
    }

    public function testPhpUnitWhenTheManifestTurnsParallelismOff(): void
    {
        $root = $this->package(['phpunit' => ['parallel' => false]]);
        $this->put($root.'/vendor/bin/paratest', '');

        $this->tester($root, 'test')->execute([]);

        self::assertSame([$root.'/vendor/bin/phpunit'], $this->runner->commands());
    }
}
