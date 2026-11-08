<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\TwigStanCommand;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TwigStanCommand::class)]
final class TwigStanCommandTest extends CommandTestCase
{
    public function testWithoutTemplates(): void
    {
        $tester = $this->tester($this->package(), 'twigstan');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("No Twig template: nothing to analyse.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testTheGeneratedConfiguration(): void
    {
        $root = $this->package();
        mkdir($root.'/templates');
        $tester = $this->tester($root, 'twigstan', [new ProcessResult(1)]);

        self::assertSame(1, $tester->execute([]));
        self::assertSame([$root.'/vendor-bin/twig/vendor/bin/twigstan analyze --configuration='.$root.'/var/phil-dev/twigstan.php'], $this->runner->commands());
        self::assertSame(['PHIL_DEV_TWIG_PATHS' => '["'.$root.'/templates"]'], $this->runner->runs[0]['environment']);
        self::assertSame($root, $this->runner->runs[0]['directory']);
        self::assertStringContainsString("->phpstanConfigurationFile('".$root."/var/phil-dev/twigstan.neon')", (string) file_get_contents($root.'/var/phil-dev/twigstan.php'));
        self::assertStringContainsString('level: max', (string) file_get_contents($root.'/var/phil-dev/twigstan.neon'));
    }
}
