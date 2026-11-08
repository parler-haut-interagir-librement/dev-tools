<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\BcCommand;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BcCommand::class)]
final class BcCommandTest extends CommandTestCase
{
    public function testAMonorepoIsLeftToItsSplitRepositories(): void
    {
        $tester = $this->tester($this->fixture('monorepo'), 'bc');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("A monorepo: the members are checked in their split repositories, where their tags are.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testOutsideTheRootOfARepository(): void
    {
        $tester = $this->tester($this->package(), 'bc');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("Not the root of a git repository: nothing to compare.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testNoTagYet(): void
    {
        $root = $this->package();
        mkdir($root.'/.git');
        $tester = $this->tester($root, 'bc', [new ProcessResult(0, '')]);

        self::assertSame(0, $tester->execute([]));
        self::assertSame("No tag yet: no released API to compare with.\n", $tester->getDisplay());
        self::assertSame(['git tag --list * --merged HEAD --sort=-v:refname'], $this->runner->commands());
        self::assertTrue($this->runner->runs[0]['capture']);
        self::assertSame($root, $this->runner->runs[0]['directory']);
    }

    public function testTheLatestTagOfThePattern(): void
    {
        $root = $this->package(['bc' => ['tags' => 'v*']]);
        mkdir($root.'/.git');
        $tester = $this->tester($root, 'bc', [new ProcessResult(0, " v1.2.0 \nv1.10.0\nv1.1.0\n"), new ProcessResult(1)]);

        self::assertSame(1, $tester->execute(['arguments' => ['--format=markdown', '--install-development-dependencies']]));
        self::assertSame("Comparing with v1.2.0.\n", $tester->getDisplay());
        self::assertSame([
            'git tag --list v* --merged HEAD --sort=-v:refname',
            $root.'/vendor-bin/bc/vendor/bin/roave-backward-compatibility-check --from=v1.2.0 --format=markdown --install-development-dependencies',
        ], $this->runner->commands());
        self::assertSame($root, $this->runner->runs[1]['directory']);
    }

    public function testAnInvalidPattern(): void
    {
        $root = $this->package(['bc' => ['tags' => ['v*']]]);
        mkdir($root.'/.git');

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('bc.tags must be a glob of tags, such as v*.');

        $this->tester($root, 'bc')->execute([]);
    }
}
