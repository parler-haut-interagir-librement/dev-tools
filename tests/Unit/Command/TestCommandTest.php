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
        self::assertSame(['TMPDIR' => realpath($root).'/var/tmp'], $this->runner->runs[0]['environment']);
        self::assertDirectoryExists($root.'/var/tmp');
    }

    public function testATemporaryDirectoryInsideTheRootIsKept(): void
    {
        $root = $this->package();
        mkdir($root.'/var/tmp/mine', recursive: true);
        $before = getenv('TMPDIR');
        putenv('TMPDIR='.$root.'/var/tmp/mine/');

        try {
            $this->tester($root, 'test')->execute([]);
        } finally {
            putenv(false === $before ? 'TMPDIR' : 'TMPDIR='.$before);
        }

        self::assertSame([], $this->runner->runs[0]['environment']);
    }

    public function testTheRootItselfAsTheTemporaryDirectoryIsKept(): void
    {
        $root = $this->package();
        $before = getenv('TMPDIR');
        putenv('TMPDIR='.$root);

        try {
            $this->tester($root, 'test')->execute([]);
        } finally {
            putenv(false === $before ? 'TMPDIR' : 'TMPDIR='.$before);
        }

        self::assertSame([], $this->runner->runs[0]['environment']);
    }

    public function testAnEmptyOrMissingTemporaryDirectoryIsReplaced(): void
    {
        $root = $this->package();
        $before = getenv('TMPDIR');
        $directory = getcwd();
        chdir($root);

        try {
            putenv('TMPDIR=');
            $this->tester($root, 'test')->execute([]);
            $empty = $this->runner->runs[0]['environment'];
            putenv('TMPDIR');
            $this->tester($root, 'test')->execute([]);
        } finally {
            putenv(false === $before ? 'TMPDIR' : 'TMPDIR='.$before);
            chdir((string) $directory);
        }

        self::assertSame(['TMPDIR' => realpath($root).'/var/tmp'], $empty);
        self::assertSame(['TMPDIR' => realpath($root).'/var/tmp'], $this->runner->runs[0]['environment']);
    }

    public function testATemporaryDirectoryBesideTheRootIsReplaced(): void
    {
        $root = $this->package();
        mkdir($root.'-other', recursive: true);
        mkdir($root.'/var/tmp', recursive: true);
        $before = getenv('TMPDIR');
        putenv('TMPDIR='.$root.'-other');

        try {
            $this->tester($root, 'test')->execute([]);
        } finally {
            putenv(false === $before ? 'TMPDIR' : 'TMPDIR='.$before);
            rmdir($root.'-other');
        }

        self::assertSame(['TMPDIR' => realpath($root).'/var/tmp'], $this->runner->runs[0]['environment']);
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
