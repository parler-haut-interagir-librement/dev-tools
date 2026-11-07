<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\ServicesCommand;
use Phil\DevTools\Process\ProcessResult;
use Phil\DevTools\Services\TestServices;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ServicesCommand::class)]
final class ServicesCommandTest extends CommandTestCase
{
    public function testUpAndDown(): void
    {
        $root = $this->temporaryDirectory();
        $compose = 'docker compose --file '.TestServices::composeFile().' --project-name phil-dev-tools';

        self::assertSame(2, $this->tester($root, 'services', [new ProcessResult(2)])->execute(['action' => 'up']));
        self::assertSame([$compose.' up --detach --wait'], $this->runner->commands());

        self::assertSame(0, $this->tester($root, 'services')->execute(['action' => 'down']));
        self::assertSame([$compose.' down --volumes'], $this->runner->commands());
    }

    public function testEnv(): void
    {
        putenv("PHIL_GOTENBERG_URL=http://it's:1");

        try {
            $tester = $this->tester($this->temporaryDirectory(), 'services');
            $tester->execute(['action' => 'env']);
        } finally {
            putenv('PHIL_GOTENBERG_URL');
        }

        self::assertStringContainsString("export PHIL_TEST_MYSQL_URL='mysql://", $tester->getDisplay());
        self::assertStringContainsString("export PHIL_GOTENBERG_URL='http://it'\\''s:1'", $tester->getDisplay());
    }

    public function testTest(): void
    {
        $root = $this->temporaryDirectory();

        self::assertSame(0, $this->tester($root, 'services')->execute(['action' => 'test', 'arguments' => ['--filter=Mysql', '--stop-on-failure']]));
        self::assertSame([$root.'/vendor/bin/phpunit --testsuite=Integration --filter=Mysql --stop-on-failure'], $this->runner->commands());
        self::assertSame(TestServices::environment(), $this->runner->runs[0]['environment']);
    }

    public function testRecord(): void
    {
        $root = $this->temporaryDirectory();

        self::assertSame(0, $this->tester($root, 'services')->execute(['action' => 'record', 'arguments' => ['--filter=Gotenberg', '--debug']]));
        self::assertSame([$root.'/vendor/bin/phpunit --testsuite=Integration --filter=Gotenberg --debug'], $this->runner->commands());
        self::assertSame([...TestServices::environment(), 'SYMFONY_HTTP_RECORDER' => 'record'], $this->runner->runs[0]['environment']);
    }

    public function testAnUnknownAction(): void
    {
        $tester = $this->tester($this->temporaryDirectory(), 'services');

        self::assertSame(2, $tester->execute(['action' => 'restart']));
        self::assertSame("The action is one of up, down, env, test, record.\n", $tester->getDisplay());
    }
}
