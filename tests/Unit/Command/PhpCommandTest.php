<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\MatrixCommand;
use Phil\DevTools\Command\PhpCommand;
use Phil\DevTools\DevTools;
use Phil\DevTools\Matrix\Matrix;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(PhpCommand::class)]
#[CoversClass(MatrixCommand::class)]
final class PhpCommandTest extends CommandTestCase
{
    private const string SAFE = '--env GIT_CONFIG_COUNT=1 --env GIT_CONFIG_KEY_0=safe.directory --env GIT_CONFIG_VALUE_0=/app';

    public function testItRunsInTheImageOfTheVersion(): void
    {
        $root = $this->project();
        $floor = Matrix::load()->floor('php');

        self::assertSame(3, $this->tester($root, 'php', [$this->built(), new ProcessResult(3)])->execute(['version' => 'floor', 'arguments' => ['php', '-v']]));
        self::assertSame([
            $this->inspect($floor->version),
            'docker run --rm --volume '.$root.':/app '.$this->caches($root, $floor->version).'--workdir /app phil-dev/php:'.$floor->version.' php -v',
        ], $this->runner->commands());
        self::assertTrue($this->runner->runs[0]['capture']);
    }

    public function testItBuildsTheImageWhenMissing(): void
    {
        $root = $this->project();
        $floor = Matrix::load()->floor('php');

        self::assertSame(0, $this->tester($root, 'php', [new ProcessResult(1)])->execute(['version' => $floor->version]));
        self::assertSame([
            $this->inspect($floor->version),
            'docker build --build-arg PHP_IMAGE='.$floor->image.' --label phil-dev.recipe='.$this->recipe().' --tag phil-dev/php:'.$floor->version.' '.DevTools::resource('php'),
            'docker run --rm --volume '.$root.':/app '.$this->caches($root, $floor->version).'--workdir /app phil-dev/php:'.$floor->version.' php vendor/bin/phpunit',
        ], $this->runner->commands());
    }

    public function testItBuildsOnRequestAndStopsWhenTheBuildFails(): void
    {
        $root = $this->project();

        self::assertSame(4, $this->tester($root, 'php', [new ProcessResult(4)])->execute(['version' => 'floor', '--build' => true]));
        self::assertCount(1, $this->runner->runs);
        self::assertStringStartsWith('docker build ', $this->runner->commands()[0]);
    }

    public function testItMountsTheNeighboursOfThePathRepositories(): void
    {
        // Under /tmp, not the temporary directory: in the container of a PHP of the matrix, that one is under var/tmp of
        // the project mounted at /app, where an absolute path is what /app already shows and is never mounted.
        $base = (string) realpath((string) tempnam('/tmp', 'phil-dev-test-'));
        unlink($base);
        mkdir($base);
        $this->adopt($base);
        self::assertStringStartsNotWith('/app/', $base.'/');
        $root = $base.'/work/project';
        mkdir($root.'/packages', recursive: true);
        mkdir($base.'/lib/packages', recursive: true);
        mkdir($base.'/other');
        mkdir($base.'/dev-tools');
        mkdir($base.'/work/app/x', recursive: true);
        mkdir($base.'/work/dev-tools');
        symlink($base.'/other', $root.'/linked');
        $this->put($root.'/composer.json', json_encode(['name' => 'phil/x', 'repositories' => [
            'odd',
            ['type' => 'vcs', 'url' => '../../lib'],
            ['type' => 'path', 'url' => 3],
            ['type' => 'path', 'url' => '../missing'],
            ['type' => 'path', 'url' => '../../..'],
            ['type' => 'path', 'url' => '../app'],
            ['type' => 'path', 'url' => '../app/x'],
            ['type' => 'path', 'url' => './packages/*'],
            ['type' => 'path', 'url' => 'linked/*'],
            ['type' => 'path', 'url' => '*'],
            ['type' => 'path', 'url' => '../../lib/packages/*', 'options' => ['symlink' => true]],
            ['type' => 'path', 'url' => '../../lib/packages/{a,b}'],
            ['type' => 'path', 'url' => '../dev-tools'],
            ['type' => 'path', 'url' => $base.'/dev-tools'],
            ['type' => 'path', 'url' => '../project/packages/a?'],
            ['type' => 'path', 'url' => '../../other/*/src'],
        ]], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));
        $floor = Matrix::load()->floor('php');

        $this->tester($root, 'php', [$this->built()])->execute(['version' => 'floor']);

        self::assertSame(
            'docker run --rm --volume '.$root.':/app '.$this->caches($root, $floor->version).'--volume '.$base.'/lib/packages:/lib/packages:ro --volume '.$base.'/work/dev-tools:/dev-tools:ro --volume '.$base.'/dev-tools:'.$base.'/dev-tools:ro --volume '.$root.'/packages:/project/packages:ro --volume '.$base.'/other:/other:ro --workdir /app phil-dev/php:'.$floor->version.' php vendor/bin/phpunit',
            $this->runner->commands()[1],
        );
    }

    public function testNoRepositoriesMountNothingMore(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x", "repositories": {"a": {"type": "path", "url": "/"}}}');

        $this->tester($root, 'php', [$this->built()])->execute(['version' => 'floor']);

        self::assertStringStartsWith('docker run --rm --volume '.$root.':/app '.$this->caches($root, Matrix::load()->floor('php')->version).'--workdir /app ', $this->runner->commands()[1]);
    }

    public function testTheContainerHasPhpStanDirectoriesOfItsOwn(): void
    {
        $root = $this->project();
        $next = Matrix::load()->find('php', 'next')->version;

        $this->tester($root, 'php', [$this->built(), new ProcessResult(0)])->execute(['version' => 'next']);

        self::assertStringContainsString(' '.$this->caches($root, $next).'--workdir ', $this->runner->commands()[1]);
        self::assertDirectoryExists($root.'/var/phil-dev/php/'.$next.'/var-phpstan');
        self::assertDirectoryExists($root.'/var/phil-dev/php/'.$next.'/var-phpstan-witnesses');
        self::assertDirectoryExists($root.'/var/phil-dev/php/'.$next.'/var-tmp');
        self::assertDirectoryExists($root.'/var/phil-dev/php/'.$next.'/var-infection-tmp');

        // A second run finds the directories already there.
        $this->tester($root, 'php', [$this->built(), new ProcessResult(0)])->execute(['version' => 'next']);
        self::assertStringContainsString(' '.$this->caches($root, $next).'--workdir ', $this->runner->commands()[1]);
    }

    public function testAWorktreeMountsItsRepositoryAtThePathItsGitFileNames(): void
    {
        $base = $this->outsideApp();
        $root = $this->worktree($base, $base.'/repo/.git/worktrees/wt', "../..\n");
        $floor = Matrix::load()->floor('php')->version;

        $tester = $this->tester($root, 'php', [$this->built(), new ProcessResult(0)]);

        self::assertSame(0, $tester->execute(['version' => 'floor', 'arguments' => ['git', '-C', '/app', 'rev-parse', 'HEAD']], ['capture_stderr_separately' => true]));
        self::assertSame(
            'docker run --rm --volume '.$root.':/app '.$this->caches($root, $floor).'--mount type=bind,source='.$base.'/repo/.git,target='.$base.'/repo/.git,readonly '.self::SAFE.' --workdir /app phil-dev/php:'.$floor.' git -C /app rev-parse HEAD',
            $this->runner->commands()[1],
        );
        self::assertSame('', $tester->getErrorOutput());
    }

    public function testARelativeGitdirIsMountedWhereItReachesFromApp(): void
    {
        $base = $this->outsideApp();
        $root = $this->worktree($base, '../repo/.git/worktrees/wt', '../..');

        $this->tester($root, 'php', [$this->built(), new ProcessResult(0)])->execute(['version' => 'floor']);

        self::assertStringContainsString(' --mount type=bind,source='.$base.'/repo/.git,target=/repo/.git,readonly '.self::SAFE.' --workdir /app ', $this->runner->commands()[1]);
    }

    public function testAnAbsoluteCommondirIsMountedAtItsOwnPath(): void
    {
        $base = $this->outsideApp();
        $root = $this->worktree($base, '../repo/.git/worktrees/wt', $base."/repo/./.git/\n");

        $this->tester($root, 'php', [$this->built(), new ProcessResult(0)])->execute(['version' => 'floor']);

        self::assertStringContainsString(' --mount type=bind,source='.$base.'/repo/.git,target='.$base.'/repo/.git,readonly '.self::SAFE.' --workdir /app ', $this->runner->commands()[1]);
    }

    public function testAMountFieldWithACommaOrAQuoteIsQuoted(): void
    {
        $base = $this->outsideApp();
        $root = $this->worktree($base, $base.'/re,p"o/.git/worktrees/wt', '../..', 're,p"o');

        $this->tester($root, 'php', [$this->built(), new ProcessResult(0)])->execute(['version' => 'floor']);

        self::assertStringContainsString(' --mount type=bind,"source='.$base.'/re,p""o/.git","target='.$base.'/re,p""o/.git",readonly '.self::SAFE.' --workdir ', $this->runner->commands()[1]);
    }

    public function testARepositoryThatAppAlreadyShowsIsOnlyDeclaredSafe(): void
    {
        $base = $this->outsideApp();
        // The repository inside the worktree, then the root of the container: neither is mounted.
        $root = $this->worktree($base, 'inner/.git/worktrees/wt', '../..', 'wt/inner');
        $this->tester($root, 'php', [$this->built(), new ProcessResult(0)])->execute(['version' => 'floor']);
        self::assertStringContainsString($this->caches($root, Matrix::load()->floor('php')->version).self::SAFE.' --workdir ', $this->runner->commands()[1]);

        $this->put($base.'/worktrees/top/commondir', '../..');
        $this->put($base.'/wt/.git', 'gitdir: ../worktrees/top');
        $this->tester($root, 'php', [$this->built(), new ProcessResult(0)])->execute(['version' => 'floor']);
        self::assertStringContainsString($this->caches($root, Matrix::load()->floor('php')->version).self::SAFE.' --workdir ', $this->runner->commands()[1]);
    }

    public function testAnOrdinaryCheckoutMountsNoRepository(): void
    {
        $root = $this->project();
        mkdir($root.'/.git');

        $this->tester($root, 'php', [$this->built(), new ProcessResult(0)])->execute(['version' => 'floor']);

        self::assertStringEndsWith(':/app/var/infection/tmp --workdir /app phil-dev/php:'.Matrix::load()->floor('php')->version.' php vendor/bin/phpunit', $this->runner->commands()[1]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function strangeGitFiles(): iterable
    {
        yield 'no gitdir' => ['ref: refs/heads/main', 'cannot be read as the gitdir of a worktree'];
        yield 'a gitdir after something else' => ['x gitdir: %s/repo/.git/worktrees/wt', 'cannot be read as the gitdir of a worktree'];
        yield 'a gitdir followed by another line' => ["gitdir: %s/repo/.git/worktrees/wt\nmore", 'cannot be read as the gitdir of a worktree'];
        yield 'a missing gitdir' => ['gitdir: %s/missing', 'names %s/missing, which is not the directory of a worktree'];
        yield 'a gitdir without commondir' => ['gitdir: %s/repo/.git/modules/sub', 'names %s/repo/.git/modules/sub, which is not the directory of a worktree'];
        yield 'an empty commondir' => ['gitdir: %s/repo/.git/worktrees/empty', 'names %s/repo/.git/worktrees/empty, which is not'];
        yield 'a commondir that is missing' => ['gitdir: %s/repo/.git/worktrees/lost', 'names %s/repo/.git/worktrees/lost, which is not'];
        yield 'a commondir that holds no worktrees/' => ['gitdir: %s/repo/.git/worktrees/far', 'names %s/repo/.git/worktrees/far, which is not'];
        yield 'a commondir whose worktrees/ is elsewhere' => ['gitdir: %s/repo/.git/other/wt', 'names %s/repo/.git/other/wt, which is not'];
    }

    #[DataProvider('strangeGitFiles')]
    public function testAGitFileThatNamesNoWorktreeIsReportedAndNothingIsMounted(string $content, string $message): void
    {
        $base = $this->outsideApp();
        $root = $this->worktree($base, $base.'/repo/.git/worktrees/wt', '../..');
        mkdir($base.'/repo/.git/modules/sub', recursive: true);
        $this->put($base.'/repo/.git/worktrees/empty/commondir', "\n");
        $this->put($base.'/repo/.git/worktrees/lost/commondir', '../../missing');
        $this->put($base.'/repo/.git/worktrees/far/commondir', '../../..');
        $this->put($base.'/repo/.git/other/wt/commondir', '../..');
        $this->put($root.'/.git', \sprintf($content, $base));

        $tester = $this->tester($root, 'php', [$this->built(), new ProcessResult(5)]);

        self::assertSame(5, $tester->execute(['version' => 'floor'], ['capture_stderr_separately' => true]));
        self::assertStringContainsString($root.'/.git '.\sprintf($message, $base), $tester->getErrorOutput());
        self::assertStringContainsString(': the repository is not mounted, and git cannot reach it in the container.', $tester->getErrorOutput());
        self::assertSame('', $tester->getDisplay());
        self::assertStringEndsWith(':/app/var/infection/tmp --workdir /app phil-dev/php:'.Matrix::load()->floor('php')->version.' php vendor/bin/phpunit', $this->runner->commands()[1]);
    }

    public function testAGitFileThatCannotBeReadIsReported(): void
    {
        $base = $this->outsideApp();
        $root = $this->worktree($base, $base.'/repo/.git/worktrees/wt', '../..');
        chmod($root.'/.git', 0o000);

        if (is_readable($root.'/.git')) {
            chmod($root.'/.git', 0o644);
            self::markTestSkipped('Every file is readable to this user.');
        }

        $tester = $this->tester($root, 'php', [$this->built(), new ProcessResult(0)]);
        $tester->execute(['version' => 'floor']);
        chmod($root.'/.git', 0o644);

        self::assertStringContainsString($root.'/.git cannot be read as the gitdir of a worktree: the repository is not mounted', $tester->getDisplay());
        self::assertStringNotContainsString('--mount', $this->runner->commands()[1]);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function tmpDirs(): iterable
    {
        yield 'relative' => ['phpstan.neon', "parameters:\n    tmpDir: build/stan\n", 'build/stan'];
        yield 'quoted, dotted, doubled slashes' => ['phpstan.neon', "parameters:\n    level: max\n    tmpDir: './build//stan/'\n", 'build/stan'];
        yield 'dist' => ['phpstan.dist.neon', "parameters:\n    tmpDir: \"cache\"\n", 'cache'];
        yield 'absolute' => ['phpstan.neon', "parameters:\n    tmpDir: /tmp/stan\n", 'var/phpstan'];
        yield 'outside' => ['phpstan.neon', "parameters:\n    tmpDir: var/../../stan\n", 'var/phpstan'];
        yield 'the project itself' => ['phpstan.neon', "parameters:\n    tmpDir: .\n", 'var/phpstan'];
        yield 'none' => ['phpstan.neon', "parameters:\n    level: max\n", 'var/phpstan'];
        yield 'after a key on its line' => ['phpstan.neon', "parameters: tmpDir: build\n", 'var/phpstan'];
        yield 'at the start of a line' => ['phpstan.neon', "tmpDir: build\n", 'var/phpstan'];
        yield 'an expression' => ['phpstan.neon', "parameters:\n    tmpDir: %rootDir%/stan\n", 'var/phpstan'];
    }

    #[DataProvider('tmpDirs')]
    public function testTheTmpDirOfThePhpStanConfiguration(string $file, string $neon, string $expected): void
    {
        $root = $this->project();
        $this->put($root.'/'.$file, $neon);
        $floor = Matrix::load()->floor('php')->version;

        $this->tester($root, 'php', [$this->built(), new ProcessResult(0)])->execute(['version' => 'floor']);

        self::assertStringContainsString(' '.$this->caches($root, $floor, $expected).'--workdir ', $this->runner->commands()[1]);
    }

    public function testTheNeonFileWinsOverTheDistOne(): void
    {
        $root = $this->project();
        $this->put($root.'/phpstan.neon', "parameters:\n    level: max\n");
        $this->put($root.'/phpstan.dist.neon', "parameters:\n    tmpDir: cache\n");
        $floor = Matrix::load()->floor('php')->version;

        $this->tester($root, 'php', [$this->built(), new ProcessResult(0)])->execute(['version' => 'floor']);

        self::assertStringContainsString(' '.$this->caches($root, $floor).'--workdir ', $this->runner->commands()[1]);
    }

    public function testATmpDirOfTheWitnessesIsMountedOnce(): void
    {
        $root = $this->project();
        $this->put($root.'/phpstan.neon', "parameters:\n    tmpDir: var/phpstan-witnesses\n");
        $floor = Matrix::load()->floor('php')->version;

        $this->tester($root, 'php', [$this->built(), new ProcessResult(0)])->execute(['version' => 'floor']);

        self::assertStringContainsString(
            ':/app --volume '.$root.'/var/phil-dev/php/'.$floor.'/var-phpstan-witnesses:/app/var/phpstan-witnesses --volume '.$root.'/var/phil-dev/php/'.$floor.'/var-tmp:/app/var/tmp --volume ',
            $this->runner->commands()[1],
        );
    }

    public function testTheMatrix(): void
    {
        $tester = $this->tester($this->temporaryDirectory(), 'matrix');
        $floor = Matrix::load()->floor('php');

        self::assertSame(0, $tester->execute([]));
        self::assertStringStartsWith(\sprintf("php      %-5s floor  %s\n", $floor->version, $floor->image), $tester->getDisplay());
        self::assertStringContainsString(\sprintf("symfony  %-5s floor\n", Matrix::load()->floor('symfony')->version), $tester->getDisplay());
    }

    private function caches(string $root, string $version, string $tmpDir = 'var/phpstan'): string
    {
        $volumes = '';

        foreach (array_unique([$tmpDir, 'var/phpstan-witnesses', 'var/tmp', 'var/infection/tmp']) as $directory) {
            $volumes .= '--volume '.$root.'/var/phil-dev/php/'.$version.'/'.str_replace('/', '-', $directory).':/app/'.$directory.' ';
        }

        return $volumes;
    }

    /**
     * A directory under /tmp, not the temporary directory: in the container of a PHP of the matrix, that one is under
     * var/tmp of the project mounted at /app, where an absolute path is what /app already shows and is never mounted.
     */
    private function outsideApp(): string
    {
        $base = (string) realpath((string) tempnam('/tmp', 'phil-dev-test-'));
        unlink($base);
        mkdir($base);
        $this->adopt($base);

        return $base;
    }

    /**
     * A worktree as git lays it out: the project `<base>/wt`, whose `.git` file names `$gitdir`, and the directory of
     * the worktree, `<base>/<repository>/.git/worktrees/wt`, with its `commondir`.
     */
    private function worktree(string $base, string $gitdir, string $commondir, string $repository = 'repo'): string
    {
        $root = $base.'/wt';
        $this->put($root.'/composer.json', '{"name": "phil/x"}');
        $this->put($root.'/.git', 'gitdir: '.$gitdir."\n");
        $this->put($base.'/'.$repository.'/.git/worktrees/wt/commondir', $commondir);
        $this->put($base.'/'.$repository.'/.git/HEAD', "ref: refs/heads/main\n");

        return $root;
    }

    private function project(): string
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x"}');

        return $root;
    }

    public function testAnImageBuiltFromAnotherDockerfileIsBuiltAgain(): void
    {
        $root = $this->project();
        $floor = Matrix::load()->floor('php');

        self::assertSame(0, $this->tester($root, 'php', [new ProcessResult(0, "0123abcd\n"), new ProcessResult(0), new ProcessResult(0)])->execute(['version' => 'floor']));
        self::assertSame('docker build --build-arg PHP_IMAGE='.$floor->image.' --label phil-dev.recipe='.$this->recipe().' --tag phil-dev/php:'.$floor->version.' '.DevTools::resource('php'), $this->runner->commands()[1]);
        self::assertCount(3, $this->runner->runs);
    }

    public function testTheDockerfileOfTheImageInstallsPcntl(): void
    {
        self::assertMatchesRegularExpression('/docker-php-ext-install [^;]*\bpcntl\b/', (string) file_get_contents(DevTools::resource('php/Dockerfile')));
    }

    /**
     * What `docker image inspect` gives for an image built from the Dockerfile of this version of phil-dev.
     */
    private function built(): ProcessResult
    {
        return new ProcessResult(0, $this->recipe()."\n");
    }

    private function recipe(): string
    {
        return (string) hash_file('sha256', DevTools::resource('php/Dockerfile'));
    }

    private function inspect(string $version): string
    {
        return 'docker image inspect --format {{ index .Config.Labels "phil-dev.recipe" }} phil-dev/php:'.$version;
    }
}
