<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Closure;
use Phil\DevTools\Command\SplitCommand;
use Phil\DevTools\Console\Application;
use Phil\DevTools\DevTools;
use Phil\DevTools\Docs\SourceStamp;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Process\LocalProcessRunner;
use Phil\DevTools\Process\ProcessResult;
use Phil\DevTools\Split\SecurityFiles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SplitCommand::class)]
final class SplitCommandTest extends CommandTestCase
{
    private const string SHA = '0123456789abcdef0123456789abcdef01234567';

    private const string HEAD = 'fedcba9876543210fedcba9876543210fedcba98';

    private const string BLOB = '1111111111111111111111111111111111111111';

    private const string TREE = '2222222222222222222222222222222222222222';

    public function testAPackageOnItsOwn(): void
    {
        $tester = $this->tester($this->package(), 'split');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("Not a monorepo: nothing to split.\n", $tester->getDisplay());
    }

    public function testADryRunWithGitSubtree(): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/slug.git']);
        $tester = $this->tester($root, 'split', [new ProcessResult(0, "-n 1/3 (1) [0]\r\n".self::SHA."\n"), ...$this->security()]);

        $status = $this->withEnvironment('PATH', $this->executables([]), static fn (): int => $tester->execute(['--tag' => 'v1.0.0']));

        self::assertSame(0, $status);
        self::assertSame('git subtree split --prefix=packages/slug HEAD', $this->runner->commands()[0]);
        self::assertSame('git read-tree '.self::SHA, $this->runner->commands()[1]);
        self::assertCount(9, $this->runner->runs);
        self::assertTrue($this->runner->runs[0]['capture']);
        self::assertSame($root, $this->runner->runs[0]['directory']);
        self::assertSame(
            "phil/slug: would push fedcba987654, the split 0123456789ab and its security files, to git@github.com:phil/slug.git as refs/heads/main and refs/tags/v1.0.0.\n"
            ."phil/slug-bundle: not finished, no split.repository.\n"
            ."tilttek/dice: not finished, no split.repository.\n"
            .'Nothing was pushed: run again with --push.'."\n",
            $tester->getDisplay(),
        );
        self::assertDirectoryDoesNotExist($root.'/var/phil-dev/split/security-phil-slug');
    }

    public function testAPushWithSplitshLite(): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/slug.git', 'branch' => '1.x']);
        $path = $this->executables(['splitsh-lite']);
        $tester = $this->tester($root, 'split', [new ProcessResult(0, "\n".self::SHA." \r\n"), new ProcessResult(0, ''), ...$this->security(), new ProcessResult(0)]);

        $status = $this->withEnvironment('PATH', $path, static fn (): int => $tester->execute(['packages' => ['slug'], '--push' => true, '--tag' => 'v1.0.0']));

        self::assertSame(0, $status);
        $commands = $this->runner->commands();
        self::assertSame($path.'/splitsh-lite --prefix=packages/slug --origin=HEAD', $commands[0]);
        self::assertSame('git ls-remote --heads git@github.com:phil/slug.git refs/heads/1.x', $commands[1]);
        self::assertSame('git push git@github.com:phil/slug.git '.self::HEAD.':refs/heads/1.x '.self::HEAD.':refs/tags/v1.0.0', $commands[\count($commands) - 1]);
        self::assertFalse($this->runner->runs[\count($commands) - 1]['capture']);
        self::assertSame('', $tester->getDisplay());
    }

    public function testAFailedPush(): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/slug.git']);
        $tester = $this->tester($root, 'split', [new ProcessResult(0, self::SHA), new ProcessResult(0, ''), ...$this->security(), new ProcessResult(1)]);

        self::assertSame(1, $this->withEnvironment('PATH', '', static fn (): int => $tester->execute(['packages' => ['slug'], '--push' => true, '--branch' => 'next'])));
        $commands = $this->runner->commands();
        self::assertSame('git push git@github.com:phil/slug.git '.self::HEAD.':refs/heads/next', $commands[\count($commands) - 1]);
    }

    public function testAnUnreadableRemoteStopsThePackage(): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/slug.git']);
        $tester = $this->tester($root, 'split', [new ProcessResult(0, self::SHA), new ProcessResult(128)]);

        self::assertSame(1, $this->withEnvironment('PATH', '', static fn (): int => $tester->execute(['packages' => ['slug'], '--push' => true])));
        self::assertSame("phil/slug: cannot read the branch main of git@github.com:phil/slug.git.\n", $tester->getDisplay());
        self::assertCount(2, $this->runner->runs);
    }

    public function testAFailedSecurityCommitStopsThePackage(): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/slug.git']);
        $tester = $this->tester($root, 'split', [new ProcessResult(0, self::SHA), new ProcessResult(1)]);

        self::assertSame(1, $this->withEnvironment('PATH', '', static fn (): int => $tester->execute(['packages' => ['slug']])));
        self::assertSame("phil/slug: the commit of the security files failed.\nNothing was pushed: run again with --push.\n", $tester->getDisplay());
    }

    public function testAMemberWithoutASecurityPolicyIsNotSplit(): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/slug.git']);
        unlink($root.'/packages/slug/SECURITY.md');
        $tester = $this->tester($root, 'split');

        self::assertSame(1, $this->withEnvironment('PATH', '', static fn (): int => $tester->execute(['packages' => ['slug']])));
        self::assertStringStartsWith('phil/slug: no SECURITY.md; a split repository needs its security policy (phil-dev split --security-files adds it).'."\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
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
        $this->put($root.'/packages/tilttek-dice/SECURITY.md', '# Security Policy');
        $tester = $this->tester($root, 'split', [$result, new ProcessResult(0, self::SHA), ...$this->security()]);

        self::assertSame(1, $this->withEnvironment('PATH', '', static fn (): int => $tester->execute(['packages' => ['slug', 'tilttek-dice']])));
        self::assertStringStartsWith("phil/slug: the split failed.\ntilttek/dice: would push ", $tester->getDisplay());
        self::assertCount(1 + 9, $this->runner->runs);
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

    public function testADryRunInARealRepositoryAddsTheSecurityFilesAndPushesNothing(): void
    {
        $remote = $this->bareRepository();
        $root = $this->monorepo(['repository' => $remote]);
        $this->put($root.'/packages/slug/assets/package.json', '{}');
        $this->commitAll($root, 'The monorepo');
        $split = $this->git($root, 'subtree', 'split', '--prefix=packages/slug', 'HEAD');

        [$status, $display] = $this->realSplit($root, ['packages' => ['slug']]);

        self::assertSame(0, $status, $display);
        self::assertSame(1, preg_match('/^phil\/slug: would push ([0-9a-f]{12}), the split '.substr($split, 0, 12).' and its security files, to /', $display, $match), $display);
        $head = $this->git($root, 'rev-parse', $match[1]);
        self::assertSame($split, $this->git($root, 'rev-parse', $head.'^1'));
        self::assertSame(".github/dependabot.yml\n.github/workflows/scorecard.yml", $this->git($root, 'diff-tree', '--no-commit-id', '--name-only', '-r', $head));
        self::assertStringContainsString('directory: /assets', $this->git($root, 'show', $head.':.github/dependabot.yml'));
        self::assertSame($this->git($root, 'show', $split.':composer.json'), $this->git($root, 'show', $head.':composer.json'));
        self::assertSame('', $this->git($remote, 'for-each-ref'));
        self::assertSame('', $this->git($root, 'status', '--porcelain'));
        self::assertSame($this->git($root, 'rev-parse', 'HEAD^{tree}'), $this->git($root, 'write-tree'));

        // The same split and the same files give the same commit.
        [, $again] = $this->realSplit($root, ['packages' => ['slug']]);
        self::assertSame($display, $again);
    }

    public function testEachSplitIsAFastForwardOfThePublishedBranch(): void
    {
        $remote = $this->bareRepository();
        $root = $this->monorepo(['repository' => $remote]);
        $this->commitAll($root, 'The monorepo');

        [$status, $display] = $this->realSplit($root, ['packages' => ['slug'], '--push' => true, '--tag' => 'v0.1.0']);
        self::assertSame(0, $status, $display);
        $first = $this->git($remote, 'rev-parse', 'refs/heads/main');
        self::assertSame($first, $this->git($remote, 'rev-parse', 'refs/tags/v0.1.0^{commit}'));
        self::assertSame($this->git($root, 'subtree', 'split', '--prefix=packages/slug', 'HEAD'), $this->git($remote, 'rev-parse', $first.'^1'));

        $this->put($root.'/packages/slug/src/Slug.php', '<?php');
        $this->commitAll($root, 'A class');
        [$status, $display] = $this->realSplit($root, ['packages' => ['slug'], '--push' => true]);
        self::assertSame(0, $status, $display);
        $second = $this->git($remote, 'rev-parse', 'refs/heads/main');
        $split = $this->git($root, 'subtree', 'split', '--prefix=packages/slug', 'HEAD');
        self::assertSame($split.' '.$first, $this->git($remote, 'log', '-1', '--format=%P', $second));
        self::assertSame('<?php', $this->git($remote, 'show', $second.':src/Slug.php'));
        self::assertSame($first, $this->git($remote, 'rev-parse', 'refs/tags/v0.1.0^{commit}'));

        // Nothing new: the tip already holds this tree, and is pushed as it is.
        [$status, $display] = $this->realSplit($root, ['packages' => ['slug'], '--push' => true]);
        self::assertSame(0, $status, $display);
        self::assertSame($second, $this->git($remote, 'rev-parse', 'refs/heads/main'));

        // A published tag is never moved.
        [$status] = $this->realSplit($root, ['packages' => ['slug'], '--push' => true, '--tag' => 'v0.1.0']);
        self::assertSame(1, $status);
        self::assertSame($first, $this->git($remote, 'rev-parse', 'refs/tags/v0.1.0^{commit}'));
    }

    public function testTheSecurityFilesArePreparedInTheMembers(): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil-forks/slug.git']);
        unlink($root.'/packages/slug/SECURITY.md');
        $readme = "# phil/slug\n\n**Slugs.**\n";
        $this->put($root.'/packages/slug/README.md', $readme);
        $this->put($root.'/packages/slug/README.fr.md', SourceStamp::apply("# phil/slug\n\n**Des slugs.**\n", $readme));
        $this->put($root.'/packages/slug-bundle/README.md', "# phil/slug-bundle\n");
        $this->put($root.'/packages/slug-bundle/SECURITY.md', 'kept');
        $this->put($root.'/packages/slug-bundle/README.fr.md', SourceStamp::apply("# phil/slug-bundle\n", 'an older README'));
        $this->put($root.'/phil-dev.php', '<?php return '.var_export(['packages' => ['packages/*'], 'split' => ['snyk' => true]], true).';');
        $this->put($root.'/.snyk', 'kept');
        $tester = $this->tester($root, 'split');

        self::assertSame(0, $tester->execute(['--security-files' => true]));

        self::assertSame(
            "phil/slug: added SECURITY.md and the badge of its README.\n"
            ."phil/slug-bundle: added the badge of its README.\n"
            ."tilttek/dice: added SECURITY.md.\n"
            ."The root: added .github/workflows/snyk.yml.\n"
            ."Nothing was split or committed: review the changes, then commit them.\n",
            $tester->getDisplay(),
        );
        self::assertSame([], $this->runner->runs);
        self::assertFileEquals(DevTools::resource('skeleton/common/SECURITY.md'), $root.'/packages/slug/SECURITY.md');
        $badge = SecurityFiles::badge('phil-forks', 'slug');
        $updated = "# phil/slug\n\n".$badge."\n\n**Slugs.**\n";
        self::assertSame($updated, file_get_contents($root.'/packages/slug/README.md'));
        self::assertSame(SourceStamp::apply("# phil/slug\n\n".$badge."\n\n**Des slugs.**\n", $updated), file_get_contents($root.'/packages/slug/README.fr.md'));
        $bundleBadge = SecurityFiles::badge('parler-haut-interagir-librement', 'slug-bundle');
        self::assertSame("# phil/slug-bundle\n\n".$bundleBadge."\n", file_get_contents($root.'/packages/slug-bundle/README.md'));
        $bundleMirror = (string) file_get_contents($root.'/packages/slug-bundle/README.fr.md');
        self::assertSame(SourceStamp::fingerprint('an older README'), SourceStamp::read($bundleMirror));
        self::assertStringContainsString("# phil/slug-bundle\n\n".$bundleBadge."\n", $bundleMirror);
        self::assertSame('kept', file_get_contents($root.'/packages/slug-bundle/SECURITY.md'));
        self::assertSame('kept', file_get_contents($root.'/.snyk'));
        self::assertFileEquals(DevTools::resource('security/monorepo/snyk.yml'), $root.'/.github/workflows/snyk.yml');

        $tester->execute(['--security-files' => true, 'packages' => ['slug']]);
        self::assertSame("phil/slug: nothing to add.\nNothing was split or committed: review the changes, then commit them.\n", $tester->getDisplay());
        self::assertSame($updated, file_get_contents($root.'/packages/slug/README.md'));
    }

    public function testThePreparationLeavesOutWhatItCannotName(): void
    {
        $root = $this->package(composer: ['name' => 'acme/thing']);
        $this->put($root.'/README.md', "# acme/thing\n");
        $this->put($root.'/SECURITY.md', 'kept');
        $tester = $this->tester($root, 'split');

        self::assertSame(0, $tester->execute(['--security-files' => true]));
        self::assertSame("acme/thing: nothing to add.\nNothing was split or committed: review the changes, then commit them.\n", $tester->getDisplay());
        self::assertSame("# acme/thing\n", file_get_contents($root.'/README.md'));
        self::assertFileDoesNotExist($root.'/.snyk');
    }

    public function testThePreparationAddsTheRootFilesOfAPublicMonorepo(): void
    {
        $root = $this->monorepo(['repository' => 'git@github.com:phil/slug.git']);
        $this->put($root.'/phil-dev.php', '<?php return '.var_export(['packages' => ['packages/*'], 'split' => ['snyk' => true]], true).';');
        $tester = $this->tester($root, 'split');

        $tester->execute(['--security-files' => true, 'packages' => ['slug']]);

        self::assertStringContainsString("The root: added .snyk.\nThe root: added .github/workflows/snyk.yml.\n", $tester->getDisplay());
        self::assertFileEquals(DevTools::resource('security/monorepo/.snyk'), $root.'/.snyk');
    }

    /**
     * What git answers to the commands of the security commit, from the index to the commit.
     *
     * @return list<ProcessResult>
     */
    private function security(): array
    {
        return [
            new ProcessResult(0),
            new ProcessResult(0, self::BLOB."\n"),
            new ProcessResult(0),
            new ProcessResult(0, self::BLOB."\n"),
            new ProcessResult(0),
            new ProcessResult(0, self::TREE."\n"),
            new ProcessResult(0, "Split\nsplit@example.com\n1700000000 +0000\n"),
            new ProcessResult(0, self::HEAD."\n"),
        ];
    }

    /**
     * The monorepo fixture, its `slug` member finished with `$split`, with its security policy.
     *
     * @param array<string, mixed> $split
     */
    private function monorepo(array $split): string
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/slug/phil-dev.php', '<?php return '.var_export(['split' => $split], true).';');
        $this->put($root.'/packages/slug/SECURITY.md', '# Security Policy');

        return $root;
    }

    /**
     * Runs the command with git itself, its global and system configurations left out.
     *
     * @param array<string, mixed> $input
     *
     * @return array{int, string}
     */
    private function realSplit(string $root, array $input): array
    {
        $tester = new CommandTester(new Application($root, new LocalProcessRunner())->find('split'));
        $path = \dirname($this->which('git')).':/usr/bin:/bin';

        return $this->isolated(fn (): array => $this->withEnvironment('PATH', $path, static fn (): array => [$tester->execute($input), $tester->getDisplay()]));
    }

    private function bareRepository(): string
    {
        $remote = $this->temporaryDirectory();
        $this->git($remote, 'init', '--quiet', '--bare', '--initial-branch=main');

        return $remote;
    }

    private function commitAll(string $root, string $message): void
    {
        if (!is_dir($root.'/.git')) {
            $this->git($root, 'init', '--quiet', '--initial-branch=main');
        }

        $this->git($root, 'add', '--all');
        $this->git($root, '-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '--quiet', '--no-verify', '-m', $message);
    }

    private function git(string $directory, string ...$arguments): string
    {
        $result = $this->isolated(static fn (): ProcessResult => new LocalProcessRunner()->capture(['git', ...array_values($arguments)], $directory));

        if (!$result->successful()) {
            throw new RuntimeException('git '.implode(' ', $arguments).' failed in '.$directory.'.');
        }

        return trim($result->output);
    }

    /**
     * Runs `$test` with the global and system configurations of git left out: no signing, hook or identity of the
     * machine reaches the repositories of the test.
     *
     * @template T
     *
     * @param Closure(): T $test
     *
     * @return T
     */
    private function isolated(Closure $test): mixed
    {
        return $this->withEnvironment('GIT_CONFIG_GLOBAL', '/dev/null', fn (): mixed => $this->withEnvironment('GIT_CONFIG_NOSYSTEM', '1', $test));
    }

    private function which(string $program): string
    {
        return trim(new LocalProcessRunner()->capture(['/bin/sh', '-c', 'command -v '.$program], '/')->output);
    }
}
