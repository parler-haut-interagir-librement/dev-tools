<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\UnusedCommand;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UnusedCommand::class)]
final class UnusedCommandTest extends CommandTestCase
{
    public function testTheRootWithTheIgnoredPackagesOfEveryManifest(): void
    {
        $root = $this->package(['unused' => ['ignore' => ['symfony/polyfill-php86', 'ext-intl']]]);
        $tester = $this->tester($root, 'unused', [new ProcessResult(1)]);

        self::assertSame(1, $tester->execute([]));
        self::assertSame([$root.'/vendor-bin/composer-unused/vendor/bin/composer-unused '.$root.'/composer.json --no-progress --excludePackage=symfony/polyfill-php86 --excludePackage=ext-intl'], $this->runner->commands());
        self::assertSame($root, $this->runner->runs[0]['directory']);
    }

    public function testTheGateCanBeTurnedOff(): void
    {
        $root = $this->package(['gates' => ['unused' => false]]);
        $tester = $this->tester($root, 'unused');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("The unused gate is turned off in phil-dev.php.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }
}
