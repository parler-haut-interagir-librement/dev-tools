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

    public function testTheScriptTestsRunWithTheirModuleHooks(): void
    {
        $root = $this->package();
        $this->put($root.'/tests/js/a.test.mjs', '');
        $this->put($root.'/tests/js/register.mjs', '');
        $path = $this->executables(['node']);
        $tester = $this->tester($root, 'js', [new ProcessResult(0)]);

        self::assertSame(0, $this->withEnvironment('PATH', $path, static fn (): int => $tester->execute([])));
        self::assertSame([$path.'/node --import ./tests/js/register.mjs --test tests/js/**/*.test.mjs'], $this->runner->commands());
        self::assertSame($root, $this->runner->runs[0]['directory']);
        self::assertFalse($this->runner->runs[0]['capture']);
        self::assertSame('', $tester->getDisplay());
    }

    public function testBiomeThenTheTestsOfEachMember(): void
    {
        $root = (string) realpath($this->fixture('monorepo'));
        mkdir($root.'/packages/slug/assets');
        $this->put($root.'/packages/slug/tests/js/a.test.mjs', '');
        $this->put($root.'/packages/tilttek-dice/tests/js/b.test.mjs', '');
        $this->put($root.'/packages/tilttek-dice/tests/js/register.mjs', '');
        $this->put($root.'/var/phil-dev/bin/biome', '');
        $path = $this->executables(['node']);
        $tester = $this->tester($root, 'js', [new ProcessResult(0), new ProcessResult(1), new ProcessResult(0)]);

        self::assertSame(1, $this->withEnvironment('PATH', $path, static fn (): int => $tester->execute([])));
        self::assertSame([
            $root.'/var/phil-dev/bin/biome check --config-path='.$root.'/var/phil-dev/biome.json '.$root.'/packages/slug/assets',
            $path.'/node --test tests/js/**/*.test.mjs',
            $path.'/node --import ./tests/js/register.mjs --test tests/js/**/*.test.mjs',
        ], $this->runner->commands());
        self::assertSame([$root, $root.'/packages/slug', $root.'/packages/tilttek-dice'], array_column($this->runner->runs, 'directory'));
        self::assertSame("The script tests of phil/slug failed.\n", $tester->getDisplay());
    }

    public function testAFailureOfBiomeStopsBeforeTheTests(): void
    {
        $root = $this->package();
        mkdir($root.'/assets');
        $this->put($root.'/tests/js/a.test.mjs', '');
        $this->put($root.'/var/phil-dev/bin/biome', '');

        self::assertSame(2, $this->tester($root, 'js', [new ProcessResult(2)])->execute([]));
        self::assertCount(1, $this->runner->runs);
    }

    public function testWithoutNodeTheScriptTestsFail(): void
    {
        $root = $this->package();
        $this->put($root.'/tests/js/a.test.mjs', '');
        $path = $this->executables([]);
        $tester = $this->tester($root, 'js');

        self::assertSame(1, $this->withEnvironment('PATH', $path, static fn (): int => $tester->execute([])));
        self::assertSame("Node.js is not on the PATH: the script tests of phil/example cannot run.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testFixLeavesTheTestsAlone(): void
    {
        $root = $this->package();
        $this->put($root.'/tests/js/a.test.mjs', '');
        $tester = $this->tester($root, 'js');

        self::assertSame(0, $tester->execute(['--fix' => true]));
        self::assertSame("No script: nothing to check.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }
}
