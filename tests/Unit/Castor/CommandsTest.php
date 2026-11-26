<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Castor;

use Phil\DevTools\Castor\Commands;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Commands::class)]
final class CommandsTest extends TestCase
{
    public function testOneFailedStepFailsTheWhole(): void
    {
        self::assertSame(0, Commands::aggregate());
        self::assertSame(0, Commands::aggregate(0, 0));
        self::assertSame(1, Commands::aggregate(0, 2));
        self::assertSame(1, Commands::aggregate(128, 128), 'Exit codes are not summed modulo 256.');
        self::assertSame(1, Commands::aggregate(-1));
    }

    public function testThePhpUnitCommandQuotesTheSuitesAndTheFilter(): void
    {
        self::assertSame("vendor/bin/phpunit --testsuite='Unit,Functional'", Commands::phpunit('vendor/bin/phpunit', ['Unit', 'Functional']));
        self::assertSame("vendor/bin/phpunit --testsuite='Unit' --filter='a b' --testdox", Commands::phpunit('vendor/bin/phpunit', ['Unit'], 'a b', '--testdox'));
        self::assertSame("phpunit --testsuite='Unit' --testdox", Commands::phpunit('phpunit', ['Unit'], options: '--testdox'));
        self::assertSame("phpunit --testsuite='Unit;rm'", Commands::phpunit('phpunit', ['Unit;rm']));
    }

    /**
     * @return iterable<string, array{string, ?float}>
     */
    public static function provideReports(): iterable
    {
        $summary = "Code Coverage Report:\n  2026-11-25 10:00:00\n\n Summary:\n  Classes: 50.00% (1/2)\n  Methods: 75.00% (3/4)\n  Lines:   {lines} (19/20)\n\nApp\\Foo\n  Methods: 100.00% ( 1/ 1)   Lines:  50.00% (  1/  2)\n";

        yield 'a summary' => [str_replace('{lines}', '95.00%', $summary), 95.0];
        yield 'full' => [str_replace('{lines}', '100.00%', $summary), 100.0];
        yield 'a whole number' => ["  Lines:   80% (8/10)\n", 80.0];
        yield 'none' => [' Summary:', null];
        yield 'empty' => ['', null];
        yield 'coloured by PHPUnit' => ["\e[30;42m  Lines:   97.50% (39/40)\e[0m\n", 97.5];
        yield 'coloured, the code before the label only' => ["\e[33m  Lines:\e[0m   50.00% (1/2)\n", 50.0];
        yield 'not a summary line' => ["  Methods: 75.00% (3/4)   Lines: 12.50% (1/8)\n", null];
    }

    #[DataProvider('provideReports')]
    public function testItReadsTheCoveredLinesOfTheSummary(string $report, ?float $expected): void
    {
        self::assertSame($expected, Commands::coveredLines($report));
    }

    public function testAReportReachesItsThresholdAtOrAboveIt(): void
    {
        self::assertTrue(Commands::reaches('Lines: 100.00%', 100));
        self::assertTrue(Commands::reaches('Lines: 90.00%', 90));
        self::assertFalse(Commands::reaches('Lines: 89.99%', 90));
        self::assertFalse(Commands::reaches('', 0), 'A run that wrote no summary is not a pass.');
        self::assertTrue(Commands::reaches('Lines: 0.00%', 0));
    }
}
