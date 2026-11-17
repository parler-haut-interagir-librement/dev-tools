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

    public function testItCapturesTheErrorsWithTheOutputOnRequest(): void
    {
        $directory = sys_get_temp_dir();

        $result = new LocalProcessRunner()->capture(['/bin/sh', '-c', 'echo out; echo err >&2; exit 3'], $directory, withErrors: true);

        self::assertSame(3, $result->exitCode);
        self::assertSame("out\nerr\n", $result->output);
    }

    /**
     * Without `$withErrors`, the error output stays phil-dev's own, apart from the captured one; the command tells
     * which, so that nothing is written there: Infection stops its initial run at the first byte on stderr.
     */
    public function testItLeavesTheErrorsOnTheTerminalByDefault(): void
    {
        $command = ['/bin/sh', '-c', '[ /dev/fd/1 -ef /dev/fd/2 ] && echo one || echo apart'];

        self::assertSame("apart\n", new LocalProcessRunner()->capture($command, sys_get_temp_dir())->output);
        self::assertSame("one\n", new LocalProcessRunner()->capture($command, sys_get_temp_dir(), withErrors: true)->output);
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
