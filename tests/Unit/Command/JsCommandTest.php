<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\JsCommand;
use Phil\DevTools\Config\BiomeConfig;
use Phil\DevTools\DevTools;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JsCommand::class)]
final class JsCommandTest extends CommandTestCase
{
    public function testWithoutScripts(): void
    {
        $tester = $this->tester($this->package(), 'js');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("No script: nothing to check.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testBiomeIsDownloadedTheFirstTime(): void
    {
        $root = $this->package();
        mkdir($root.'/assets');
        $tester = $this->tester($root, 'js', [new ProcessResult(0), new ProcessResult(1)]);

        self::assertSame(1, $tester->execute([]));
        self::assertSame([
            \PHP_BINARY.' '.DevTools::resource('biome/download.php').' '.$root.'/vendor-bin/biome/vendor/autoload.php '.$root.'/var/phil-dev/bin '.BiomeConfig::VERSION,
            $root.'/var/phil-dev/bin/biome check --config-path='.$root.'/var/phil-dev/biome.json '.$root.'/assets',
        ], $this->runner->commands());
        self::assertSame([$root, $root], array_column($this->runner->runs, 'directory'));
        self::assertDirectoryExists($root.'/var/phil-dev/bin');
        self::assertStringContainsString('"recommended": true', (string) file_get_contents($root.'/var/phil-dev/biome.json'));
    }

    public function testAFailedDownloadStops(): void
    {
        $root = $this->package();
        mkdir($root.'/assets');
        mkdir($root.'/var/phil-dev/bin', 0o777, true);

        self::assertSame(3, $this->tester($root, 'js', [new ProcessResult(3)])->execute([]));
        self::assertCount(1, $this->runner->runs);
    }

    public function testFixWithBiomeAlreadyThere(): void
    {
        $root = $this->package(['js' => ['paths' => ['assets/controllers', 'assets/lib']]]);
        mkdir($root.'/assets/controllers', 0o777, true);
        mkdir($root.'/assets/lib', 0o777, true);
        $this->put($root.'/var/phil-dev/bin/biome', '');

        self::assertSame(0, $this->tester($root, 'js')->execute(['--fix' => true]));
        self::assertSame([$root.'/var/phil-dev/bin/biome check --config-path='.$root.'/var/phil-dev/biome.json --write '.$root.'/assets/controllers '.$root.'/assets/lib'], $this->runner->commands());
    }

    public function testTheGateCanBeTurnedOff(): void
    {
        $root = $this->package(['gates' => ['js' => false]]);
        mkdir($root.'/assets');
        $tester = $this->tester($root, 'js');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("The js gate is turned off in phil-dev.php.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }
}
