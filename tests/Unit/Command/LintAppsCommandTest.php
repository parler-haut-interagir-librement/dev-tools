<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\LintAppsCommand;
use Phil\DevTools\DevTools;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LintAppsCommand::class)]
final class LintAppsCommandTest extends CommandTestCase
{
    public function testNoFunctionalApplication(): void
    {
        $tester = $this->tester($this->package(), 'lint-apps');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("No package has a functional test application: nothing to lint.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testEachKernelInAProcessOfItsOwn(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/slug-bundle/tests/Functional/App/TestKernel.php', "<?php\nnamespace Phil\\SlugBundle\\Tests\\Functional\\App;\nfinal class TestKernel extends Kernel {}");
        $this->put($root.'/packages/slug-bundle/tests/Functional/History/HistoryKernel.php', "<?php\nnamespace Phil\\SlugBundle\\Tests\\Functional\\History;\nfinal class HistoryKernel extends Kernel {}");
        $this->put($root.'/packages/slug/tests/Functional/SlugKernel.php', "<?php\nnamespace Phil\\Slug\\Tests\\Functional;\nfinal class SlugKernel extends Kernel {}");
        $tester = $this->tester($root, 'lint-apps', [new ProcessResult(0), new ProcessResult(1), new ProcessResult(0)]);

        $status = $this->withEnvironment('TMPDIR', null, static fn (): int => $tester->execute([]));

        self::assertSame(1, $status);
        $script = \PHP_BINARY.' '.DevTools::resource('lint/app.php').' '.$root.'/vendor/autoload.php ';
        self::assertSame([
            $script.'Phil\\Slug\\Tests\\Functional\\SlugKernel',
            $script.'Phil\\SlugBundle\\Tests\\Functional\\App\\TestKernel',
            $script.'Phil\\SlugBundle\\Tests\\Functional\\History\\HistoryKernel',
        ], $this->runner->commands());
        self::assertSame([$root, $root, $root], array_column($this->runner->runs, 'directory'));
        self::assertSame(['TMPDIR' => $root.'/var/tmp'], $this->runner->runs[0]['environment']);
        self::assertSame(
            "== phil/slug: packages/slug/tests/Functional/SlugKernel.php\n"
            ."== phil/slug-bundle: packages/slug-bundle/tests/Functional/App/TestKernel.php\n"
            ."== phil/slug-bundle: packages/slug-bundle/tests/Functional/History/HistoryKernel.php\n",
            $tester->getDisplay(),
        );
    }

    public function testEveryApplicationPasses(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/slug-bundle/tests/Functional/App/TestKernel.php', "<?php\nnamespace A;\nfinal class TestKernel extends Kernel {}");
        $tester = $this->tester($root, 'lint-apps');

        self::assertSame(0, $tester->execute(['packages' => ['slug-bundle']]));
        self::assertCount(1, $this->runner->runs);
    }

    public function testOnlyThePackagesNamed(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/slug-bundle/tests/Functional/App/TestKernel.php', "<?php\nnamespace A;\nfinal class TestKernel extends Kernel {}");
        $tester = $this->tester($root, 'lint-apps');

        self::assertSame(0, $tester->execute(['packages' => ['slug']]));
        self::assertSame("No package has a functional test application: nothing to lint.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testAKernelLeftOutByTheManifest(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/slug-bundle/phil-dev.php', "<?php return ['apps' => ['skip' => ['A\\\\OpenApiKernel']]];");
        $this->put($root.'/packages/slug-bundle/tests/Functional/App/OpenApiKernel.php', "<?php\nnamespace A;\nfinal class OpenApiKernel extends Kernel {}");
        $this->put($root.'/packages/slug-bundle/tests/Functional/App/TestKernel.php', "<?php\nnamespace A;\nfinal class TestKernel extends Kernel {}");
        $tester = $this->tester($root, 'lint-apps', [new ProcessResult(0)]);

        self::assertSame(0, $tester->execute([]));
        self::assertSame([\PHP_BINARY.' '.DevTools::resource('lint/app.php').' '.$root.'/vendor/autoload.php A\\TestKernel'], $this->runner->commands());
        self::assertSame(
            "== phil/slug-bundle: packages/slug-bundle/tests/Functional/App/OpenApiKernel.php\nLeft out by apps.skip.\n"
            ."== phil/slug-bundle: packages/slug-bundle/tests/Functional/App/TestKernel.php\n",
            $tester->getDisplay(),
        );
    }

    public function testTheGateTurnedOff(): void
    {
        $root = $this->package(['gates' => ['apps' => false]]);
        $this->put($root.'/tests/Functional/App/TestKernel.php', "<?php\nnamespace A;\nfinal class TestKernel extends Kernel {}");
        $tester = $this->tester($root, 'lint-apps');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("The apps gate is turned off in phil-dev.php.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }
}
