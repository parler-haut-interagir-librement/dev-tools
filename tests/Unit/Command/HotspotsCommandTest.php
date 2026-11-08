<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\HotspotsCommand;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HotspotsCommand::class)]
final class HotspotsCommandTest extends CommandTestCase
{
    public function testChurnOnTheGeneratedConfiguration(): void
    {
        $root = $this->package();
        $tester = $this->tester($root, 'hotspots', [new ProcessResult(1)]);

        self::assertSame(1, $tester->execute([]));
        self::assertSame([$root.'/vendor-bin/churn/vendor/bin/churn run --configuration='.$root.'/var/phil-dev/churn.yml'], $this->runner->commands());
        self::assertSame($root, $this->runner->runs[0]['directory']);
        self::assertStringContainsString("  - '".$root."/src'", (string) file_get_contents($root.'/var/phil-dev/churn.yml'));
    }
}
