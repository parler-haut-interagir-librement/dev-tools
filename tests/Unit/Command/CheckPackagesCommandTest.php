<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\CheckPackagesCommand;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Matrix\Matrix;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(CheckPackagesCommand::class)]
final class CheckPackagesCommandTest extends CommandTestCase
{
    public function testEachMemberIsInstalledOnItsOwn(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/slug/vendor/autoload.php', '');
        $this->put($root.'/packages/slug/composer.lock', '{}');
        $this->put($root.'/packages/slug-bundle/composer.json', '{"name": "phil/slug-bundle", "extra": {"branch-alias": {"dev-trunk": "1.0.x-dev"}}}');
        $this->put($root.'/packages/tilttek-dice/composer.json', '{"name": "tilttek/dice", "extra": {"branch-alias": {"0.1": "0.1.x-dev"}}}');
        $tester = $this->tester($root, 'check-packages');

        self::assertSame(0, $tester->execute(['packages' => ['slug']]));
        self::assertSame("== phil/slug\nEvery package installs and passes on its own.\n", $tester->getDisplay());

        $versions = ['phil/slug' => 'dev-main', 'phil/slug-bundle' => 'dev-trunk', 'tilttek/dice' => 'dev-main'];
        $repository = json_encode(['type' => 'path', 'url' => $root.'/packages/*', 'options' => ['symlink' => false, 'versions' => $versions]], \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
        self::assertSame([
            'composer config repositories.siblings0 '.$repository,
            'composer install --no-interaction --no-progress --quiet',
            \PHP_BINARY.' '.$root.'/vendor/bin/composer-dependency-analyser',
            'vendor/bin/phpunit --no-configuration tests',
        ], $this->runner->commands());

        $work = $this->runner->runs[0]['directory'];
        self::assertStringStartsWith('phil-dev-', basename($work));
        self::assertSame(['TMPDIR' => $work.'/var/tmp'], $this->runner->runs[3]['environment']);
        self::assertSame($this->runner->runs[0]['environment'], $this->runner->runs[3]['environment']);
        self::assertDirectoryDoesNotExist($work);
    }

    public function testAFailureStopsThatPackageAndIsReported(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/slug/vendor/autoload.php', '');
        $this->put($root.'/packages/slug/composer.lock', '{}');
        $this->put($root.'/packages/slug/src/vendor/kept.php', '');
        $tester = $this->tester($root, 'check-packages', [new ProcessResult(0), new ProcessResult(1)]);

        self::assertSame(1, $tester->execute(['--keep' => true]));
        self::assertStringContainsString('phil/slug: composer install failed', $tester->getDisplay());
        self::assertStringContainsString('== tilttek/dice', $tester->getDisplay());
        self::assertCount(2 + 4 + 4, $this->runner->runs);

        $work = $this->runner->runs[0]['directory'];
        self::assertStringContainsString('kept in '.$work, $tester->getDisplay());
        self::assertFileExists($work.'/composer.json');
        self::assertFileExists($work.'/tests/Witnesses/PhpStan/Witness.php');
        self::assertFileDoesNotExist($work.'/vendor');
        self::assertFileDoesNotExist($work.'/composer.lock');
        self::assertFileExists($work.'/src/vendor/kept.php');
        self::assertDirectoryExists($work.'/var/tmp');

        foreach (array_unique(array_column($this->runner->runs, 'directory')) as $directory) {
            $this->adopt($directory);
        }
    }

    public function testAnotherSymfonyVersion(): void
    {
        $root = $this->fixture('monorepo');
        $floor = Matrix::load()->floor('symfony')->version;
        $this->put($root.'/packages/slug/composer.json', json_encode([
            'name' => 'phil/slug',
            'require' => ['php' => '>=8.5', 'symfony/string' => '^'.$floor, 'symfony/polyfill-mbstring' => '^1.30', 'phil/resource' => '^0.1'],
            'require-dev' => ['symfony/console' => '^'.$floor.'|^9.0'],
        ], \JSON_THROW_ON_ERROR));
        $this->put($root.'/packages/tilttek-dice/composer.json', '{"name": "tilttek/dice", "require": "odd", "require-dev": {"0": "^'.$floor.'", "symfony/yaml": ["^'.$floor.'"], "symfony/finder": "^'.$floor.'", "other/thing": "^'.$floor.'"}}');

        self::assertSame(0, $this->tester($root, 'check-packages', [])->execute(['packages' => ['slug', 'tilttek-dice'], '--symfony' => 'floor', '--keep' => true]));

        $slug = json_decode((string) file_get_contents($this->runner->runs[0]['directory'].'/composer.json'), true);
        self::assertSame([
            'name' => 'phil/slug',
            'require' => ['php' => '>=8.5', 'symfony/string' => $floor.'.*', 'symfony/polyfill-mbstring' => '^1.30', 'phil/resource' => '^0.1'],
            'require-dev' => ['symfony/console' => $floor.'.*'],
            'minimum-stability' => 'dev',
            'prefer-stable' => true,
        ], $slug);

        $dice = json_decode((string) file_get_contents($this->runner->runs[4]['directory'].'/composer.json'), true);
        self::assertSame(['name' => 'tilttek/dice', 'require' => 'odd', 'require-dev' => ['^'.$floor, 'symfony/yaml' => ['^'.$floor], 'symfony/finder' => $floor.'.*', 'other/thing' => '^'.$floor], 'minimum-stability' => 'dev', 'prefer-stable' => true], $dice);
        self::assertStringContainsString("{\n    \"name\": \"tilttek/dice\",", (string) file_get_contents($this->runner->runs[4]['directory'].'/composer.json'));
        self::assertStringContainsString('"symfony/finder": "', (string) file_get_contents($this->runner->runs[4]['directory'].'/composer.json'));

        foreach (array_unique(array_column($this->runner->runs, 'directory')) as $directory) {
            $this->adopt($directory);
        }
    }

    public function testAnUnknownSymfonyVersion(): void
    {
        $this->expectException(InvalidManifest::class);

        $this->tester($this->fixture('monorepo'), 'check-packages')->execute(['--symfony' => '1.0']);
    }

    public function testAPackageOnItsOwnNeedsNoSiblings(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/alone"}');
        $this->put($root.'/src/A.php', '<?php');

        self::assertSame(0, $this->tester($root, 'check-packages')->execute([]));
        self::assertSame('composer install --no-interaction --no-progress --quiet', $this->runner->commands()[0]);
        self::assertSame('vendor/bin/phpunit --no-configuration tests', $this->runner->commands()[2]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function configurations(): iterable
    {
        yield 'phpunit.xml' => ['phpunit.xml'];
        yield 'phpunit.dist.xml' => ['phpunit.dist.xml'];
        yield 'phpunit.xml.dist' => ['phpunit.xml.dist'];
    }

    #[DataProvider('configurations')]
    public function testAPackageWithItsOwnPhpUnitConfiguration(string $file): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/alone"}');
        $this->put($root.'/'.$file, '<phpunit/>');

        $this->tester($root, 'check-packages')->execute([]);

        self::assertSame('vendor/bin/phpunit', $this->runner->commands()[2]);
    }
}
