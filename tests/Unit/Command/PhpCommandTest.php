<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\MatrixCommand;
use Phil\DevTools\Command\PhpCommand;
use Phil\DevTools\DevTools;
use Phil\DevTools\Matrix\Matrix;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PhpCommand::class)]
#[CoversClass(MatrixCommand::class)]
final class PhpCommandTest extends CommandTestCase
{
    public function testItRunsInTheImageOfTheVersion(): void
    {
        $root = $this->project();
        $floor = Matrix::load()->floor('php');

        self::assertSame(3, $this->tester($root, 'php', [new ProcessResult(0), new ProcessResult(3)])->execute(['version' => 'floor', 'arguments' => ['php', '-v']]));
        self::assertSame([
            'docker image inspect phil-dev/php:'.$floor->version,
            'docker run --rm --volume '.$root.':/app --workdir /app phil-dev/php:'.$floor->version.' php -v',
        ], $this->runner->commands());
        self::assertTrue($this->runner->runs[0]['capture']);
    }

    public function testItBuildsTheImageWhenMissing(): void
    {
        $root = $this->project();
        $floor = Matrix::load()->floor('php');

        self::assertSame(0, $this->tester($root, 'php', [new ProcessResult(1)])->execute(['version' => $floor->version]));
        self::assertSame([
            'docker image inspect phil-dev/php:'.$floor->version,
            'docker build --build-arg PHP_IMAGE='.$floor->image.' --tag phil-dev/php:'.$floor->version.' '.DevTools::resource('php'),
            'docker run --rm --volume '.$root.':/app --workdir /app phil-dev/php:'.$floor->version.' php vendor/bin/phpunit',
        ], $this->runner->commands());
    }

    public function testItBuildsOnRequestAndStopsWhenTheBuildFails(): void
    {
        $root = $this->project();

        self::assertSame(4, $this->tester($root, 'php', [new ProcessResult(4)])->execute(['version' => 'floor', '--build' => true]));
        self::assertCount(1, $this->runner->runs);
        self::assertStringStartsWith('docker build ', $this->runner->commands()[0]);
    }

    public function testTheMatrix(): void
    {
        $tester = $this->tester($this->temporaryDirectory(), 'matrix');
        $floor = Matrix::load()->floor('php');

        self::assertSame(0, $tester->execute([]));
        self::assertStringStartsWith(\sprintf("php      %-5s floor  %s\n", $floor->version, $floor->image), $tester->getDisplay());
        self::assertStringContainsString(\sprintf("symfony  %-5s floor\n", Matrix::load()->floor('symfony')->version), $tester->getDisplay());
    }

    private function project(): string
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x"}');

        return $root;
    }
}
