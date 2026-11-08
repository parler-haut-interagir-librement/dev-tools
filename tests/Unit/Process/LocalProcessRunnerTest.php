<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Process;

use Ergebnis\PHPUnit\SlowTestDetector\Attribute\MaximumDuration;
use Phil\DevTools\Process\LocalProcessRunner;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalProcessRunner::class)]
#[CoversClass(ProcessResult::class)]
final class LocalProcessRunnerTest extends TestCase
{
    public function testItCapturesTheOutputInTheDirectoryWithTheEnvironment(): void
    {
        putenv('PHIL_DEV_INHERITED=inherited');

        try {
            $result = new LocalProcessRunner()->capture(
                [\PHP_BINARY, '-r', 'echo getcwd(), "|", getenv("PHIL_DEV_PROBE"), "|", getenv("PHIL_DEV_INHERITED"), "|", $argv[1]; exit(3);', 'an argument; with $hell'],
                sys_get_temp_dir(),
                ['PHIL_DEV_PROBE' => 'probe'],
            );
        } finally {
            putenv('PHIL_DEV_INHERITED');
        }

        self::assertSame(3, $result->exitCode);
        self::assertFalse($result->successful());
        self::assertSame(realpath(sys_get_temp_dir()).'|probe|inherited|an argument; with $hell', $result->output);
    }

    public function testItRunsWithTheStreamsOfPhilDev(): void
    {
        $file = sys_get_temp_dir().'/phil-dev-runner-'.getmypid();

        $result = new LocalProcessRunner()->run([\PHP_BINARY, '-r', 'file_put_contents($argv[1], getenv("PHIL_DEV_PROBE")); exit(0);', $file], sys_get_temp_dir(), ['PHIL_DEV_PROBE' => 'ran']);

        self::assertTrue($result->successful());
        self::assertSame('', $result->output);
        self::assertSame('ran', file_get_contents($file));
        unlink($file);
    }

    #[MaximumDuration(2000)]
    public function testATimeoutStopsTheCommand(): void
    {
        $start = hrtime(true);
        $result = new LocalProcessRunner()->run([\PHP_BINARY, '-r', 'sleep(30);'], sys_get_temp_dir(), timeout: 1);

        self::assertTrue($result->timedOut);
        self::assertTrue($result->successful());
        self::assertLessThan(10_000_000_000, hrtime(true) - $start);
    }

    public function testACommandThatEndsBeforeItsTimeoutKeepsItsExitCode(): void
    {
        $result = new LocalProcessRunner()->run([\PHP_BINARY, '-r', 'exit(4);'], sys_get_temp_dir(), timeout: 30);

        self::assertFalse($result->timedOut);
        self::assertSame(4, $result->exitCode);
    }
}
