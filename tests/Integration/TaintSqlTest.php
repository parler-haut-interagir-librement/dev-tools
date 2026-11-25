<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Integration;

use Ergebnis\PHPUnit\SlowTestDetector\Attribute\MaximumDuration;
use Phil\DevTools\Command\TaintCommand;
use Phil\DevTools\Config\PsalmConfig;
use Phil\DevTools\Console\Application;
use Phil\DevTools\DevTools;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The SQL taint of `phil-dev taint` run by Psalm on tests/Fixtures/taint-sql: a request concatenated into a query, and
 * a value a request stored that a page prints back unescaped, are reported; a bound parameter and an escaped value are
 * not.
 */
#[CoversClass(PsalmConfig::class)]
#[CoversClass(TaintCommand::class)]
final class TaintSqlTest extends TestCase
{
    use TemporaryDirectory;

    // Psalm analyses the fixture in a process of its own.
    #[MaximumDuration(5000)]
    public function testTheRequestIsFollowedIntoTheQueryAndBackOutOfTheDatabase(): void
    {
        $psalm = DevTools::directory().'/vendor-bin/psalm/vendor/bin/psalm';

        if (!is_file($psalm)) {
            self::markTestSkipped('Psalm is not installed in vendor-bin/psalm.');
        }

        $root = $this->fixture('taint-sql');
        $this->put($root.'/vendor-bin/psalm/vendor/bin/psalm', "#!/bin/sh\nexec ".escapeshellarg(\PHP_BINARY).' '.escapeshellarg($psalm)." \"\$@\"\n");
        chmod($root.'/vendor-bin/psalm/vendor/bin/psalm', 0o755);
        $tester = new CommandTester(new Application($root)->find('taint'));

        $exitCode = $tester->execute(['arguments' => ['--no-cache', '--threads=1']]);

        $lines = [];

        foreach (explode("\n", (string) file_get_contents($root.'/src/Notes.php')) as $index => $line) {
            if (str_ends_with($line, '// reported')) {
                $lines[] = $index + 1;
            }
        }

        self::assertSame(1, $exitCode, $tester->getDisplay());
        self::assertSame([21, 40], $lines);
        self::assertSame([
            $root.'/src/Notes.php:21 TaintedSql: Detected tainted SQL',
            $root.'/src/Notes.php:40 TaintedHtml: Detected tainted HTML',
            $root.'/src/Notes.php:40 TaintedTextWithQuotes: Detected tainted text with possible quotes',
            '3 tainted paths.',
        ], explode("\n", trim($tester->getDisplay())));
    }
}
