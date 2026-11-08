<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\BenchCommand;
use Phil\DevTools\Command\WorkspaceCommand;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;

#[CoversClass(BenchCommand::class)]
#[CoversClass(WorkspaceCommand::class)]
final class BenchCommandTest extends CommandTestCase
{
    public function testARun(): void
    {
        $root = $this->package();
        $tester = $this->tester($root, 'bench', [new ProcessResult(2)]);

        self::assertSame(2, $tester->execute(['arguments' => ['--filter=Parse']]));
        self::assertSame([$root.'/vendor-bin/phpbench/vendor/bin/phpbench run --report=phil --filter=Parse'], $this->runner->commands());
        self::assertSame($root, $this->runner->runs[0]['directory']);
    }

    public function testTheBaseline(): void
    {
        $root = $this->package();

        $this->tester($root, 'bench')->execute(['--baseline' => true]);

        self::assertSame([$root.'/vendor-bin/phpbench/vendor/bin/phpbench run --report=phil --tag=phil_baseline --store'], $this->runner->commands());
    }

    public function testTheComparisonAtTheDefaultThreshold(): void
    {
        $root = $this->package();

        $this->tester($root, 'bench')->execute(['--compare' => true]);

        self::assertSame([$root.'/vendor-bin/phpbench/vendor/bin/phpbench run --report=phil --ref=phil_baseline --assert=mode(variant.time.avg) <= mode(baseline.time.avg) +/- 10%'], $this->runner->commands());
    }

    public function testTheThresholdOfTheManifestAndOfTheOption(): void
    {
        $root = $this->package(['bench' => ['threshold' => 25]]);

        $this->tester($root, 'bench')->execute(['--compare' => true]);
        self::assertStringEndsWith('+/- 25%', $this->runner->commands()[0]);

        $this->tester($root, 'bench')->execute(['--compare' => true, '--threshold' => '0']);
        self::assertStringEndsWith('+/- 0%', $this->runner->commands()[0]);
    }

    public function testANegativeThresholdInTheManifest(): void
    {
        $this->expectException(InvalidManifest::class);

        $this->tester($this->package(['bench' => ['threshold' => -5]]), 'bench')->execute(['--compare' => true]);
    }

    #[TestWith(['-1'])]
    #[TestWith(['x5'])]
    #[TestWith(['5x'])]
    #[TestWith(['many'])]
    public function testAnInvalidThresholdOption(string $threshold): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('The bench threshold is a percentage, zero or more.');

        $this->tester($this->package(), 'bench')->execute(['--compare' => true, '--threshold' => $threshold]);
    }
}
