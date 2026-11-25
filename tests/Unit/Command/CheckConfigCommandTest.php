<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\CheckConfigCommand;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CheckConfigCommand::class)]
final class CheckConfigCommandTest extends CommandTestCase
{
    public function testTheWarningsFailNothing(): void
    {
        $tester = $this->tester($this->fixture('conventions/strays'), 'check-config');

        self::assertSame(0, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringStartsWith("== phil/example-bundle\n   warning: the configuration's root key is \"phil_sample\": name it \"phil_example\", after the package.\n", $display);
        self::assertStringEndsWith("\n14 warnings on the configuration conventions.\n", $display);
        self::assertSame([], $this->runner->runs);
    }

    public function testStrictFails(): void
    {
        $tester = $this->tester($this->fixture('conventions/strays'), 'check-config');

        self::assertSame(1, $tester->execute(['--strict' => true]));
    }

    public function testABundleThatFollowsPasses(): void
    {
        $tester = $this->tester($this->fixture('conventions/follows'), 'check-config');

        self::assertSame(0, $tester->execute(['--strict' => true]));
        self::assertSame("Every bundle configuration follows the conventions.\n", $tester->getDisplay());
    }
}
