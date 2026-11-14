<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\LintAppsCommand;
use Phil\DevTools\DevTools;
use Phil\DevTools\Process\LocalProcessRunner;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The script `lint-apps` runs for each kernel, run for real on the paths that need no Symfony application.
 */
#[CoversClass(LintAppsCommand::class)]
final class LintAppScriptTest extends TestCase
{
    public function testTheUsage(): void
    {
        $result = $this->script([]);

        self::assertSame(2, $result->exitCode);
        self::assertSame("Usage: php app.php <autoload.php> <kernel class>\n", $result->output);
    }

    public function testAMissingAutoloader(): void
    {
        $result = $this->script(['/missing/autoload.php', 'App\\Kernel']);

        self::assertSame(1, $result->exitCode);
        self::assertSame("Run composer install first: /missing/autoload.php does not exist.\n", $result->output);
    }

    public function testAClassThatIsNotAKernel(): void
    {
        $result = $this->script([DevTools::directory().'/vendor/autoload.php', DevTools::class]);

        self::assertSame(0, $result->exitCode);
        self::assertSame(DevTools::class." is not a kernel: skipped.\n", $result->output);
    }

    public function testAClassThatDoesNotExist(): void
    {
        $result = $this->script([DevTools::directory().'/vendor/autoload.php', 'App\\MissingKernel']);

        self::assertSame(0, $result->exitCode);
        self::assertSame("App\\MissingKernel is not a kernel: skipped.\n", $result->output);
    }

    /**
     * @param list<string> $arguments
     */
    private function script(array $arguments): ProcessResult
    {
        return new LocalProcessRunner()->capture([\PHP_BINARY, DevTools::resource('lint/app.php'), ...$arguments], DevTools::directory());
    }
}
