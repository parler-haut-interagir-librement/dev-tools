<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\TwigCommand;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TwigCommand::class)]
final class TwigCommandTest extends CommandTestCase
{
    public function testWithoutTemplates(): void
    {
        $tester = $this->tester($this->package(), 'twig');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("No Twig template: nothing to check.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testTheTemplateDirectories(): void
    {
        $root = $this->package();
        mkdir($root.'/templates');
        mkdir($root.'/src/Resources/views', 0o777, true);
        $tester = $this->tester($root, 'twig', [new ProcessResult(1)]);

        self::assertSame(1, $tester->execute([]));
        self::assertSame([$root.'/vendor-bin/twig/vendor/bin/twig-cs-fixer lint --no-cache '.$root.'/templates '.$root.'/src/Resources/views'], $this->runner->commands());
        self::assertSame($root, $this->runner->runs[0]['directory']);
    }

    public function testFix(): void
    {
        $root = $this->package(['twig' => ['paths' => ['views']]]);
        mkdir($root.'/views');

        $this->tester($root, 'twig')->execute(['--fix' => true]);

        self::assertSame([$root.'/vendor-bin/twig/vendor/bin/twig-cs-fixer lint --no-cache --fix '.$root.'/views'], $this->runner->commands());
    }

    public function testTheGateCanBeTurnedOff(): void
    {
        $root = $this->package(['gates' => ['twig' => false]]);
        mkdir($root.'/templates');
        $tester = $this->tester($root, 'twig');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("The twig gate is turned off in phil-dev.php.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }
}
