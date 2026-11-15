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
    public function testItRunsInTheImageOfTheVersion(): void
    {
        $root = $this->project();
        $floor = Matrix::load()->floor('php');

        self::assertSame(3, $this->tester($root, 'php', [new ProcessResult(0), new ProcessResult(3)])->execute(['version' => 'floor', 'arguments' => ['php', '-v']]));
        self::assertSame([
            'docker image inspect phil-dev/php:'.$floor->version,
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
            'docker image inspect phil-dev/php:'.$floor->version,
            'docker build --build-arg PHP_IMAGE='.$floor->image.' --tag phil-dev/php:'.$floor->version.' '.DevTools::resource('php'),
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

        $this->tester($root, 'php', [new ProcessResult(0)])->execute(['version' => 'floor']);

        self::assertSame(
            'docker run --rm --volume '.$root.':/app '.$this->caches($root, $floor->version).'--volume '.$base.'/lib/packages:/lib/packages:ro --volume '.$base.'/work/dev-tools:/dev-tools:ro --volume '.$base.'/dev-tools:'.$base.'/dev-tools:ro --volume '.$root.'/packages:/project/packages:ro --volume '.$base.'/other:/other:ro --workdir /app phil-dev/php:'.$floor->version.' php vendor/bin/phpunit',
            $this->runner->commands()[1],
        );
    }

    public function testNoRepositoriesMountNothingMore(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x", "repositories": {"a": {"type": "path", "url": "/"}}}');

        $this->tester($root, 'php', [new ProcessResult(0)])->execute(['version' => 'floor']);

        self::assertStringStartsWith('docker run --rm --volume '.$root.':/app '.$this->caches($root, Matrix::load()->floor('php')->version).'--workdir /app ', $this->runner->commands()[1]);
    }

    public function testTheContainerHasPhpStanDirectoriesOfItsOwn(): void
    {
        $root = $this->project();
        $next = Matrix::load()->find('php', 'next')->version;

        $this->tester($root, 'php', [new ProcessResult(0), new ProcessResult(0)])->execute(['version' => 'next']);

        self::assertStringContainsString(' '.$this->caches($root, $next).'--workdir ', $this->runner->commands()[1]);
        self::assertDirectoryExists($root.'/var/phil-dev/php/'.$next.'/var-phpstan');
        self::assertDirectoryExists($root.'/var/phil-dev/php/'.$next.'/var-phpstan-witnesses');

        // A second run finds the directories already there.
        $this->tester($root, 'php', [new ProcessResult(0), new ProcessResult(0)])->execute(['version' => 'next']);
        self::assertStringContainsString(' '.$this->caches($root, $next).'--workdir ', $this->runner->commands()[1]);
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
        yield 'at the start of a line' => ['phpstan.neon', "tmpDir: build\n", 'var/phpstan'];
        yield 'an expression' => ['phpstan.neon', "parameters:\n    tmpDir: %rootDir%/stan\n", 'var/phpstan'];
    }

    #[DataProvider('tmpDirs')]
    public function testTheTmpDirOfThePhpStanConfiguration(string $file, string $neon, string $expected): void
    {
        $root = $this->project();
        $this->put($root.'/'.$file, $neon);
        $floor = Matrix::load()->floor('php')->version;

        $this->tester($root, 'php', [new ProcessResult(0), new ProcessResult(0)])->execute(['version' => 'floor']);

        self::assertStringContainsString(' '.$this->caches($root, $floor, $expected).'--workdir ', $this->runner->commands()[1]);
    }

    public function testTheNeonFileWinsOverTheDistOne(): void
    {
        $root = $this->project();
        $this->put($root.'/phpstan.neon', "parameters:\n    level: max\n");
        $this->put($root.'/phpstan.dist.neon', "parameters:\n    tmpDir: cache\n");
        $floor = Matrix::load()->floor('php')->version;

        $this->tester($root, 'php', [new ProcessResult(0), new ProcessResult(0)])->execute(['version' => 'floor']);

        self::assertStringContainsString(' '.$this->caches($root, $floor).'--workdir ', $this->runner->commands()[1]);
    }

    public function testATmpDirOfTheWitnessesIsMountedOnce(): void
    {
        $root = $this->project();
        $this->put($root.'/phpstan.neon', "parameters:\n    tmpDir: var/phpstan-witnesses\n");
        $floor = Matrix::load()->floor('php')->version;

        $this->tester($root, 'php', [new ProcessResult(0), new ProcessResult(0)])->execute(['version' => 'floor']);

        self::assertStringContainsString(
            ':/app --volume '.$root.'/var/phil-dev/php/'.$floor.'/var-phpstan-witnesses:/app/var/phpstan-witnesses --workdir ',
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

        foreach ([$tmpDir, 'var/phpstan-witnesses'] as $directory) {
            $volumes .= '--volume '.$root.'/var/phil-dev/php/'.$version.'/'.str_replace('/', '-', $directory).':/app/'.$directory.' ';
        }

        return $volumes;
    }

    private function project(): string
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x"}');

        return $root;
    }
}
