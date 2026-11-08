<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\WorkerBenchCommand;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Process\ProcessResult;
use Phil\DevTools\Services\TestServices;
use Phil\DevTools\Tests\Unit\Support\BuiltInServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(WorkerBenchCommand::class)]
final class WorkerBenchCommandTest extends CommandTestCase
{
    use BuiltInServer;

    public function testNoPackageDeclaresAWorker(): void
    {
        $tester = $this->tester($this->package(), 'bench:worker');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("No package declares bench.worker: nothing to measure.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testSeveralPackagesDeclareOne(): void
    {
        $root = $this->fixture('monorepo');

        foreach (['slug', 'tilttek-dice'] as $member) {
            $this->put($root.'/packages/'.$member.'/phil-dev.php', "<?php return ['bench' => ['worker' => ['script' => 'worker.php']]];");
        }

        $tester = $this->tester($root, 'bench:worker');

        self::assertSame(2, $tester->execute([]));
        self::assertSame("Several packages declare bench.worker; name one.\n", $tester->getDisplay());
    }

    public function testTheServiceIsStartedMeasuredAndStopped(): void
    {
        $root = $this->package(['bench' => ['worker' => ['script' => 'tests/Bench/worker.php', 'requests' => 3, 'warmup' => 1]]]);
        $this->put($root.'/tests/Bench/worker.php', '<?php echo "ok";');
        $port = $this->serve($root.'/tests/Bench');
        $tester = $this->tester($root, 'bench:worker');

        $status = $this->withEnvironment('PHIL_DEV_FRANKENPHP_PORT', (string) $port, static fn (): int => $tester->execute([]));

        self::assertSame(0, $status, $tester->getDisplay());
        $compose = 'docker compose --file '.TestServices::composeFile().' --project-name phil-dev-tools --profile bench ';
        self::assertSame([$compose.'up --detach --wait --force-recreate frankenphp', $compose.'stop frankenphp'], $this->runner->commands());
        self::assertSame(['PHIL_DEV_WORKER_ROOT' => $root, 'PHIL_DEV_WORKER_SCRIPT' => '/app/tests/Bench/worker.php', 'PHIL_DEV_WORKER_DIRECTORY' => '/app/tests/Bench'], $this->runner->runs[0]['environment']);
        self::assertSame($this->runner->runs[0]['environment'], $this->runner->runs[1]['environment']);
        self::assertMatchesRegularExpression('#^http://127\.0\.0\.1:'.$port.'/worker\.php: 3 requests, [0-9.]+ per second; p50 [0-9.]+ ms, p95 [0-9.]+ ms, p99 [0-9.]+ ms; 0 failed\.\n$#', $tester->getDisplay());
    }

    public function testAFailedRequestFails(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/slug/phil-dev.php', "<?php return ['bench' => ['worker' => ['script' => 'worker.php', 'requests' => 2, 'warmup' => 0]]];");
        $this->put($root.'/packages/slug/worker.php', '<?php http_response_code(500);');
        $port = $this->serve($root.'/packages/slug');
        $tester = $this->tester($root, 'bench:worker');

        $status = $this->withEnvironment('PHIL_DEV_FRANKENPHP_PORT', (string) $port, static fn (): int => $tester->execute(['package' => 'slug']));

        self::assertSame(1, $status);
        self::assertStringContainsString('/worker.php: 2 requests', $tester->getDisplay());
        self::assertStringEndsWith("; 2 failed.\n", $tester->getDisplay());
        self::assertSame('/app/packages/slug/worker.php', $this->runner->runs[0]['environment']['PHIL_DEV_WORKER_SCRIPT']);
    }

    public function testTheServiceThatDoesNotStart(): void
    {
        $root = $this->package(['bench' => ['worker' => ['script' => 'worker.php']]]);
        $this->put($root.'/worker.php', '');

        self::assertSame(5, $this->tester($root, 'bench:worker', [new ProcessResult(5)])->execute([]));
        self::assertCount(1, $this->runner->runs);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidWorkers(): iterable
    {
        yield 'no script' => [['requests' => 3]];
        yield 'a script that does not exist' => [['script' => 'missing.php']];
        yield 'requests that are not a number' => [['script' => 'worker.php', 'requests' => 'many']];
        yield 'no request' => [['script' => 'worker.php', 'requests' => 0]];
        yield 'a warmup that is not a number' => [['script' => 'worker.php', 'warmup' => '1']];
        yield 'a negative warmup' => [['script' => 'worker.php', 'warmup' => -1]];
    }

    /**
     * @param array<string, mixed> $worker
     */
    #[DataProvider('invalidWorkers')]
    public function testAnInvalidWorker(array $worker): void
    {
        $root = $this->package(['bench' => ['worker' => $worker]]);
        $this->put($root.'/worker.php', '');

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('bench.worker of phil/example needs a script that exists, and whole numbers of requests and warmup.');

        $this->tester($root, 'bench:worker')->execute([]);
    }
}
