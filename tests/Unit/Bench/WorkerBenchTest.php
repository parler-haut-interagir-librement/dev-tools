<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Bench;

use Phil\DevTools\Bench\WorkerBench;
use Phil\DevTools\Bench\WorkerBenchResult;
use Phil\DevTools\Tests\Unit\Support\BuiltInServer;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkerBench::class)]
#[CoversClass(WorkerBenchResult::class)]
final class WorkerBenchTest extends TestCase
{
    use BuiltInServer;
    use TemporaryDirectory;

    public function testTheWarmupIsNotMeasured(): void
    {
        $calls = 0;
        $bench = new WorkerBench(static function (string $url) use (&$calls): bool {
            ++$calls;
            usleep(5_000);

            return 'http://x/' === $url && 0 === $calls % 2;
        });

        $result = $bench->run('http://x/', 4, 3);

        self::assertSame(7, $calls);
        self::assertSame(4, $result->requests());
        self::assertSame(2, $result->failures);
        // Each request is timed in milliseconds, from its start.
        self::assertGreaterThanOrEqual(5.0, $result->percentile(1));
        self::assertLessThan(1000.0, $result->percentile(100));
    }

    public function testTheStatusOfTheResponse(): void
    {
        $directory = $this->temporaryDirectory();
        file_put_contents($directory.'/ok.php', '<?php echo "ok";');
        file_put_contents($directory.'/error.php', '<?php http_response_code(500); echo "error";');
        $port = $this->serve($directory);
        $bench = new WorkerBench();

        self::assertSame(0, $bench->run('http://127.0.0.1:'.$port.'/ok.php', 2, 0)->failures);
        self::assertSame(2, $bench->run('http://127.0.0.1:'.$port.'/error.php', 2, 0)->failures);
        self::assertSame(0, $bench->run('http://127.0.0.1:'.$port.'/ok.php', 1, 0)->failures);
        // The headers of the request before are not read again for a server that does not answer.
        self::assertSame(1, $bench->run('http://127.0.0.1:1/', 1, 0)->failures);
    }

    public function testThePercentilesAndTheRate(): void
    {
        $result = new WorkerBenchResult([40.0, 10.0, 30.0, 20.0], 0);

        self::assertSame([10.0, 20.0, 30.0, 40.0], $result->durations);
        self::assertSame(10.0, $result->percentile(1));
        self::assertSame(20.0, $result->percentile(50));
        self::assertSame(30.0, $result->percentile(51));
        self::assertSame(40.0, $result->percentile(95));
        self::assertSame(40.0, $result->percentile(100));
        self::assertSame(10.0, $result->percentile(0));
        self::assertEqualsWithDelta(40.0, $result->perSecond(), 0.0001);

        $hundred = new WorkerBenchResult(array_map(floatval(...), range(1, 101)), 0);
        self::assertSame(2.0, $hundred->percentile(1));
        self::assertSame(51.0, $hundred->percentile(50));
        self::assertSame(101.0, $hundred->percentile(100));
    }

    public function testNoRequest(): void
    {
        $result = new WorkerBenchResult([], 0);

        self::assertSame(0, $result->requests());
        self::assertSame(0.0, $result->percentile(50));
        self::assertSame(0.0, $result->perSecond());
        self::assertSame(0.0, new WorkerBenchResult([0.0], 0)->perSecond());
    }
}
