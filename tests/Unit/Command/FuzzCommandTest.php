<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\FuzzCommand;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Exception\WorkspaceNotFound;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(FuzzCommand::class)]
final class FuzzCommandTest extends CommandTestCase
{
    public function testNoTarget(): void
    {
        $tester = $this->tester($this->package(), 'fuzz');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("No fuzz target declared: nothing to fuzz.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testEveryTargetForItsBudget(): void
    {
        $root = $this->package(['fuzz' => ['targets' => ['markup' => 'tests/Fuzz/markup.php', 'css' => 'tests/Fuzz/css.php'], 'budget' => 30]]);
        $tester = $this->tester($root, 'fuzz', [new ProcessResult(0, timedOut: true), new ProcessResult(1)]);

        self::assertSame(1, $tester->execute([]));
        self::assertSame([
            $root.'/vendor-bin/fuzzer/vendor/bin/php-fuzzer fuzz '.$root.'/tests/Fuzz/markup.php '.$root.'/tests/Fuzz/corpus/markup',
            $root.'/vendor-bin/fuzzer/vendor/bin/php-fuzzer fuzz '.$root.'/tests/Fuzz/css.php '.$root.'/tests/Fuzz/corpus/css',
        ], $this->runner->commands());
        self::assertSame([30, 30], array_column($this->runner->runs, 'timeout'));
        self::assertSame([$root.'/var/phil-dev/fuzz/markup', $root.'/var/phil-dev/fuzz/css'], array_column($this->runner->runs, 'directory'));
        self::assertSame(['PHIL_DEV_AUTOLOAD' => $root.'/vendor/autoload.php'], $this->runner->runs[0]['environment']);
        self::assertDirectoryExists($root.'/tests/Fuzz/corpus/css');
        self::assertDirectoryExists($root.'/var/phil-dev/fuzz/css');
        self::assertSame("== markup, for 30 seconds\n== css, for 30 seconds\ncss crashed: its input is in ".$root."/var/phil-dev/fuzz/css\n", $tester->getDisplay());
    }

    public function testOneTargetWithTheOptions(): void
    {
        $root = $this->package(['fuzz' => ['targets' => ['markup' => 'markup.php', 'css' => 'css.php'], 'corpus' => 'fuzz', 'budget' => 300]]);
        $tester = $this->tester($root, 'fuzz');

        self::assertSame(0, $tester->execute(['targets' => ['css'], '--budget' => '5', '--max-runs' => '100']));
        self::assertSame([$root.'/vendor-bin/fuzzer/vendor/bin/php-fuzzer fuzz '.$root.'/css.php '.$root.'/fuzz/css --max-runs 100'], $this->runner->commands());
        self::assertSame(5, $this->runner->runs[0]['timeout']);
    }

    public function testABudgetOfOneSecond(): void
    {
        $root = $this->package(['fuzz' => ['targets' => ['markup' => 'markup.php'], 'budget' => '1']]);

        $this->tester($root, 'fuzz')->execute([]);

        self::assertSame(1, $this->runner->runs[0]['timeout']);
    }

    public function testTheDefaultBudget(): void
    {
        $root = $this->package(['fuzz' => ['targets' => ['markup' => 'markup.php']]]);

        $this->tester($root, 'fuzz')->execute([]);

        self::assertSame(FuzzCommand::DEFAULT_BUDGET, $this->runner->runs[0]['timeout']);
        self::assertSame(60, FuzzCommand::DEFAULT_BUDGET);
    }

    public function testAnUnknownTarget(): void
    {
        $root = $this->package(['fuzz' => ['targets' => ['markup' => 'markup.php', 'css' => 'css.php']]]);

        $this->expectException(WorkspaceNotFound::class);
        $this->expectExceptionMessageIsOrContains('No fuzz target "svg"; the targets are markup, css.');

        $this->tester($root, 'fuzz')->execute(['targets' => ['svg']]);
    }

    public function testAnUnknownTargetWhenThereIsNone(): void
    {
        $this->expectExceptionMessageIsOrContains('No fuzz target "svg"; the targets are none.');

        $this->tester($this->package(), 'fuzz')->execute(['targets' => ['svg']]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidManifests(): iterable
    {
        yield 'a budget of zero' => [['budget' => 0, 'targets' => ['a' => 'a.php']], 'The fuzz budget is a number of seconds, one or more.'];
        yield 'a budget that is not a number' => [['budget' => 'long', 'targets' => ['a' => 'a.php']], 'The fuzz budget is a number of seconds, one or more.'];
        yield 'a budget after something else' => [['budget' => 'x5', 'targets' => ['a' => 'a.php']], 'The fuzz budget is a number of seconds, one or more.'];
        yield 'a budget before something else' => [['budget' => '5x', 'targets' => ['a' => 'a.php']], 'The fuzz budget is a number of seconds, one or more.'];
        yield 'a corpus that is not a path' => [['corpus' => ['a'], 'targets' => ['a' => 'a.php']], 'fuzz.corpus of phil/example must be a directory.'];
        yield 'a target without a name' => [['targets' => ['a.php']], 'fuzz.targets of phil/example maps a name to the PHP file of the target.'];
        yield 'a target without a file' => [['targets' => ['a' => true]], 'fuzz.targets of phil/example maps a name to the PHP file of the target.'];
    }

    /**
     * @param array<string, mixed> $fuzz
     */
    #[DataProvider('invalidManifests')]
    public function testAnInvalidManifest(array $fuzz, string $message): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->tester($this->package(['fuzz' => $fuzz]), 'fuzz')->execute([]);
    }
}
