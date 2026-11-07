<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\WitnessesCommand;
use Phil\DevTools\DevTools;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WitnessesCommand::class)]
final class WitnessesCommandTest extends CommandTestCase
{
    public function testNoWitnesses(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x"}');
        $tester = $this->tester($root, 'witnesses');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("No witnesses to check.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testEveryWitnessIsStopped(): void
    {
        $root = $this->root();
        $report = json_encode(['files' => [$root.'/tests/Witnesses/PhpStan/A.php' => ['messages' => [['identifier' => 'phil.dump']]]]], \JSON_THROW_ON_ERROR);
        $tester = $this->tester($root, 'witnesses', [new ProcessResult(1, $report)]);

        self::assertSame(0, $tester->execute([]));
        self::assertSame("Each witness of PHPStan is stopped by its rule (1).\n", $tester->getDisplay());
        self::assertSame(
            $root.'/vendor-bin/phpstan/vendor/bin/phpstan analyse --configuration='.DevTools::directory().'/config/phpstan-witnesses.neon --error-format=json --no-progress --no-ansi',
            $this->runner->commands()[0],
        );
        self::assertSame(['PHIL_DEV_ROOT' => $root], $this->runner->runs[0]['environment']);
        self::assertTrue($this->runner->runs[0]['capture']);
    }

    public function testASilentRuleFails(): void
    {
        $root = $this->root();
        $tester = $this->tester($root, 'witnesses', [new ProcessResult(0, '{"files": []}')]);

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('the rule is silent', $tester->getDisplay());
    }

    private function root(): string
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x"}');
        $this->put($root.'/tests/Witnesses/PhpStan/A.php', "<?php\n/** Expected identifier: `phil.dump` */");

        return $root;
    }
}
