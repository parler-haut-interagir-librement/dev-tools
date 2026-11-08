<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\SplitCommand;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(SplitCommand::class)]
final class SplitCommandTest extends CommandTestCase
{
    private const string SHA = '0123456789abcdef0123456789abcdef01234567';

    public function testAPackageOnItsOwn(): void
    {
        $tester = $this->tester($this->package(), 'split');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("Not a monorepo: nothing to split.\n", $tester->getDisplay());
    }

    public function testADryRunWithGitSubtree(): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/slug.git']);
        $tester = $this->tester($root, 'split', [new ProcessResult(0, "-n 1/3 (1) [0]\r\n".self::SHA."\n")]);

        $status = $this->withEnvironment('PATH', $this->executables([]), static fn (): int => $tester->execute(['--tag' => 'v1.0.0']));

        self::assertSame(0, $status);
        self::assertSame(['git subtree split --prefix=packages/slug HEAD'], $this->runner->commands());
        self::assertTrue($this->runner->runs[0]['capture']);
        self::assertSame($root, $this->runner->runs[0]['directory']);
        self::assertSame(
            "phil/slug: would push 0123456789ab to git@github.com:phil/slug.git as refs/heads/main and refs/tags/v1.0.0.\n"
            ."phil/slug-bundle: not finished, no split.repository.\n"
            ."tilttek/dice: not finished, no split.repository.\n"
            .'Nothing was pushed: run again with --push.'."\n",
            $tester->getDisplay(),
        );
    }

    public function testAPushWithSplitshLite(): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/slug.git', 'branch' => '1.x']);
        $path = $this->executables(['splitsh-lite']);
        $tester = $this->tester($root, 'split', [new ProcessResult(0, "\n".self::SHA." \r\n"), new ProcessResult(0)]);

        $status = $this->withEnvironment('PATH', $path, static fn (): int => $tester->execute(['packages' => ['slug'], '--push' => true, '--tag' => 'v1.0.0']));

        self::assertSame(0, $status);
        self::assertSame([
            $path.'/splitsh-lite --prefix=packages/slug --origin=HEAD',
            'git push git@github.com:phil/slug.git '.self::SHA.':refs/heads/1.x '.self::SHA.':refs/tags/v1.0.0',
        ], $this->runner->commands());
        self::assertSame([true, false], array_column($this->runner->runs, 'capture'));
        self::assertSame('', $tester->getDisplay());
    }

    public function testAFailedPush(): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/slug.git']);
        $tester = $this->tester($root, 'split', [new ProcessResult(0, self::SHA), new ProcessResult(1)]);

        self::assertSame(1, $this->withEnvironment('PATH', '', static fn (): int => $tester->execute(['packages' => ['slug'], '--push' => true, '--branch' => 'next'])));
        self::assertSame('git push git@github.com:phil/slug.git '.self::SHA.':refs/heads/next', $this->runner->commands()[1]);
    }

    /**
     * @return iterable<string, array{ProcessResult}>
     */
    public static function failedSplits(): iterable
    {
        yield 'a failure' => [new ProcessResult(1, self::SHA)];
        yield 'no commit' => [new ProcessResult(0, "\n")];
        yield 'something else than a commit' => [new ProcessResult(0, "fatal: no such prefix\n")];
        yield 'a commit after something else' => [new ProcessResult(0, 'x'.self::SHA)];
        yield 'a commit before something else' => [new ProcessResult(0, self::SHA.'x')];
    }

    #[DataProvider('failedSplits')]
    public function testAFailedSplit(ProcessResult $result): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/slug.git']);
        $this->put($root.'/packages/tilttek-dice/phil-dev.php', "<?php return ['split' => ['repository' => 'git@github.com:tilttek/dice.git']];");
        $tester = $this->tester($root, 'split', [$result, new ProcessResult(0, self::SHA)]);

        self::assertSame(1, $this->withEnvironment('PATH', '', static fn (): int => $tester->execute(['packages' => ['slug', 'tilttek-dice']])));
        self::assertStringStartsWith("phil/slug: the split failed.\ntilttek/dice: would push ", $tester->getDisplay());
        self::assertCount(2, $this->runner->runs);
    }

    public function testADryRunIntoAMonorepo(): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/packages.git', 'prefix' => '/packages/slug/']);
        $tester = $this->tester($root, 'split', [new ProcessResult(0, self::SHA)]);

        self::assertSame(0, $this->withEnvironment('PATH', '', static fn (): int => $tester->execute(['packages' => ['slug']])));
        self::assertStringStartsWith('phil/slug: would merge 0123456789ab under packages/slug/ of git@github.com:phil/packages.git, branch main.', $tester->getDisplay());
        self::assertCount(1, $this->runner->runs);
    }

    public function testAPushIntoAMonorepoAddsTheDirectoryTheFirstTime(): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/packages.git', 'prefix' => 'packages/slug']);
        $work = $root.'/var/phil-dev/split/phil-slug';
        $this->put($work.'/stale.txt', 'from an earlier run');
        $tester = $this->tester($root, 'split', [new ProcessResult(0, self::SHA)]);
        $this->runner->onRun = static function (array $command) use ($work): void {
            if ('clone' === $command[1]) {
                mkdir($work);
            }
        };

        self::assertSame(0, $this->withEnvironment('PATH', '', static fn (): int => $tester->execute(['packages' => ['slug'], '--push' => true])));
        self::assertSame([
            'git subtree split --prefix=packages/slug HEAD',
            'git clone --branch main --single-branch git@github.com:phil/packages.git '.$work,
            'git branch --force phil-dev-split/phil-slug '.self::SHA,
            'git subtree add --prefix=packages/slug --message=Split of phil/slug at 0123456789ab '.$root.' phil-dev-split/phil-slug',
            'git push origin main',
        ], $this->runner->commands());
        self::assertSame([$root, $root, $root, $work, $work], array_column($this->runner->runs, 'directory'));
        self::assertFileDoesNotExist($work.'/stale.txt');
    }

    public function testAPushIntoAMonorepoMergesOnceTheDirectoryExists(): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/packages.git', 'prefix' => 'packages/slug']);
        $work = $root.'/var/phil-dev/split/phil-slug';
        $tester = $this->tester($root, 'split', [new ProcessResult(0, self::SHA)]);
        // The clone is what makes the directory: the recording runner clones nothing, so the test makes it.
        $this->runner->onRun = static function (array $command) use ($work): void {
            if ('clone' === $command[1]) {
                mkdir($work.'/packages/slug', 0o777, true);
            }
        };

        $this->withEnvironment('PATH', '', static fn (): int => $tester->execute(['packages' => ['slug'], '--push' => true]));

        self::assertStringStartsWith('git subtree pull --prefix=packages/slug ', $this->runner->commands()[3]);
    }

    /**
     * @return iterable<string, array{list<ProcessResult>, int}>
     */
    public static function failedMerges(): iterable
    {
        yield 'the clone' => [[new ProcessResult(0, self::SHA), new ProcessResult(128)], 2];
        yield 'the branch' => [[new ProcessResult(0, self::SHA), new ProcessResult(0), new ProcessResult(1)], 3];
        yield 'the subtree' => [[new ProcessResult(0, self::SHA), new ProcessResult(0), new ProcessResult(0), new ProcessResult(1)], 4];
        yield 'the push' => [[new ProcessResult(0, self::SHA), new ProcessResult(0), new ProcessResult(0), new ProcessResult(0), new ProcessResult(1)], 5];
    }

    /**
     * @param list<ProcessResult> $results
     */
    #[DataProvider('failedMerges')]
    public function testAFailedMergeStops(array $results, int $runs): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/packages.git', 'prefix' => 'packages/slug']);
        $tester = $this->tester($root, 'split', $results);

        self::assertSame(1, $this->withEnvironment('PATH', '', static fn (): int => $tester->execute(['packages' => ['slug'], '--push' => true])));
        self::assertCount($runs, $this->runner->runs);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidSplits(): iterable
    {
        yield 'a repository that is not a string' => [['repository' => ['x']]];
        yield 'an empty repository' => [['repository' => '']];
        yield 'a branch that is not a string' => [['repository' => 'x', 'branch' => 1]];
        yield 'an empty branch' => [['repository' => 'x', 'branch' => '']];
        yield 'a prefix that is not a string' => [['repository' => 'x', 'prefix' => true]];
        yield 'an empty prefix' => [['repository' => 'x', 'prefix' => '/']];
    }

    /**
     * @param array<string, mixed> $split
     */
    #[DataProvider('invalidSplits')]
    public function testAnInvalidSplit(array $split): void
    {
        $root = $this->monorepo($split);

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageMatches('/^split\.(repository|branch and split\.prefix) of phil\/slug must be /');

        $this->tester($root, 'split')->execute(['packages' => ['slug']]);
    }

    /**
     * The monorepo fixture, its `slug` member finished with `$split`.
     *
     * @param array<string, mixed> $split
     */
    private function monorepo(array $split): string
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/slug/phil-dev.php', '<?php return '.var_export(['split' => $split], true).';');

        return $root;
    }
}
