<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\DeptracCommand;
use Phil\DevTools\Command\GenerateCommand;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DeptracCommand::class)]
#[CoversClass(GenerateCommand::class)]
final class DeptracCommandTest extends CommandTestCase
{
    public function testTheGeneratedConfigurationThenTheRootOnes(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'deptrac', [new ProcessResult(0), new ProcessResult(1)]);

        self::assertSame(1, $tester->execute([]));

        $deptrac = $root.'/vendor-bin/deptrac/vendor/bin/deptrac analyse';
        self::assertSame([
            $deptrac.' --config-file='.$root.'/var/phil-dev/deptrac.yaml --cache-file='.$root.'/var/phil-dev/deptrac-0.cache --fail-on-uncovered --report-uncovered --no-progress',
            $deptrac.' --config-file='.$root.'/deptrac-contexts.yaml --cache-file='.$root.'/var/phil-dev/deptrac-1.cache --fail-on-uncovered --report-uncovered --no-progress',
        ], $this->runner->commands());
        self::assertFileExists($root.'/var/phil-dev/deptrac.yaml');
    }

    public function testItPassesWhenEveryConfigurationPasses(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/phil-dev.php', "<?php return ['deptrac' => ['configs' => ['a.yaml', 'b.yaml']]];");

        self::assertSame(0, $this->tester($root, 'deptrac')->execute([]));
        self::assertCount(3, $this->runner->runs);
        self::assertStringContainsString('--config-file='.$root.'/b.yaml ', $this->runner->commands()[2]);
    }

    public function testGenerate(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'generate');

        self::assertSame(0, $tester->execute([]));
        self::assertSame(implode('', array_map(static fn (string $file): string => $root.'/var/phil-dev/'.$file."\n", ['deptrac.yaml', 'infection.json', 'psalm.xml', 'biome.json', 'churn.yml'])), $tester->getDisplay());
        self::assertFileExists($root.'/var/phil-dev/infection.json');
        self::assertStringContainsString('<psalm ', (string) file_get_contents($root.'/var/phil-dev/psalm.xml'));
        self::assertStringContainsString('directoriesToScan:', (string) file_get_contents($root.'/var/phil-dev/churn.yml'));
    }
}
