<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\TaintCommand;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TaintCommand::class)]
final class TaintCommandTest extends CommandTestCase
{
    public function testOnlyTheTaintIssuesAreReported(): void
    {
        $root = $this->package();
        $report = json_encode([
            ['type' => 'TaintedHtml', 'file_path' => 'src/View.php', 'line_from' => 12, 'message' => 'Detected tainted HTML'],
            ['type' => 'MissingOverrideAttribute', 'file_path' => 'src/A.php', 'line_from' => 3, 'message' => 'Add #[Override]'],
            ['type' => 'TaintedSql', 'file_path' => ['x'], 'message' => 2.5],
            ['type' => 7],
            'not an issue',
        ], \JSON_THROW_ON_ERROR);
        $tester = $this->tester($root, 'taint', [new ProcessResult(2, $report)]);

        self::assertSame(1, $tester->execute(['arguments' => ['--threads=4', '--no-cache']]));
        self::assertSame([$root.'/vendor-bin/psalm/vendor/bin/psalm --config='.$root.'/var/phil-dev/psalm.xml --taint-analysis --no-progress --output-format=json --threads=4 --no-cache'], $this->runner->commands());
        self::assertTrue($this->runner->runs[0]['capture']);
        self::assertSame($root, $this->runner->runs[0]['directory']);
        self::assertSame("src/View.php:12 TaintedHtml: Detected tainted HTML\n?:? TaintedSql: ?\n2 tainted paths.\n", $tester->getDisplay());
        self::assertStringContainsString('<directory name="'.$root.'/src" />', (string) file_get_contents($root.'/var/phil-dev/psalm.xml'));
    }

    public function testNoTaint(): void
    {
        $tester = $this->tester($this->package(), 'taint', [new ProcessResult(2, '[{"type": "MissingOverrideAttribute"}]')]);

        self::assertSame(0, $tester->execute([]));
        self::assertSame("No tainted data reaches a sink.\n", $tester->getDisplay());
    }

    public function testNoReport(): void
    {
        $tester = $this->tester($this->package(), 'taint', [new ProcessResult(255, 'Fatal error')]);

        self::assertSame(1, $tester->execute([]));
        self::assertSame("Psalm gave no report: run it with --output-format=console to see why.\n", $tester->getDisplay());
    }
}
