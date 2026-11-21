<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\CheckPackagesCommand;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Matrix\Matrix;
use Phil\DevTools\Process\ProcessResult;
use Phil\DevTools\Split\SecurityFiles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

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
        $this->put($root.'/packages/slug/SECURITY.md', '# Security Policy');
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

    public function testThePolyfillsOfTheRunningPhpAreExpectedUnused(): void
    {
        $root = $this->fixture('monorepo');
        $current = 'symfony/polyfill-php'.\PHP_MAJOR_VERSION.\PHP_MINOR_VERSION;
        $this->put($root.'/packages/slug/composer.json', json_encode(['name' => 'phil/slug', 'require' => [
            'php' => '>=8.5',
            'symfony/polyfill-php99' => '^1.33',
            $current => '^1.33',
            'symfony/polyfill-php80' => '^1.33',
            'symfony/polyfill-mbstring' => '^1.33',
        ]], \JSON_THROW_ON_ERROR));
        $this->put($root.'/packages/slug/composer-dependency-analyser.php', "<?php\n\nreturn new ShipMonk\\ComposerDependencyAnalyser\\Config\\Configuration();\n");

        self::assertSame(0, $this->tester($root, 'check-packages')->execute(['packages' => ['slug'], '--keep' => true]));

        $work = $this->runner->runs[0]['directory'];
        $configuration = $work.'/var/phil-dev/composer-dependency-analyser.php';
        self::assertSame(\PHP_BINARY.' '.$root.'/vendor/bin/composer-dependency-analyser --config='.$configuration, $this->runner->commands()[2]);
        $written = (string) file_get_contents($configuration);
        self::assertStringContainsString("ignoreErrorsOnPackages(['symfony/polyfill-php80', '".$current."'], [ErrorType::UNUSED_DEPENDENCY])", $written);
        self::assertStringContainsString('polyfill nothing on PHP '.\PHP_MAJOR_VERSION.'.'.\PHP_MINOR_VERSION.',', $written);
        self::assertStringContainsString("__DIR__.'/../../composer-dependency-analyser.php'", $written);
        self::assertFileExists($work.'/composer-dependency-analyser.php');
        self::assertInstanceOf(Configuration::class, require $configuration);

        $this->adopt($work);
    }

    public function testWithoutAnIdlePolyfillTheAnalyserReadsThePackagesOwnConfiguration(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/slug/composer.json', '{"name": "phil/slug", "require": {"symfony/polyfill-php99": "^1.33", "symfony/polyfill-php8": "^1.0"}}');

        self::assertSame(0, $this->tester($root, 'check-packages')->execute(['packages' => ['slug']]));
        self::assertSame(\PHP_BINARY.' '.$root.'/vendor/bin/composer-dependency-analyser', $this->runner->commands()[2]);
    }

    public function testWhatASplitRepositoryWouldMissIsAWarning(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/slug/README.md', "# phil/slug\n");
        $this->put($root.'/packages/slug-bundle/README.md', "# phil/slug-bundle\n\n".SecurityFiles::badge('o', 'slug-bundle')."\n");
        $this->put($root.'/packages/slug-bundle/SECURITY.md', '# Security Policy');
        $tester = $this->tester($root, 'check-packages');

        self::assertSame(0, $tester->execute(['packages' => ['slug', 'slug-bundle']]));
        self::assertSame(
            "== phil/slug\n"
            ."   warning: no SECURITY.md (phil-dev split --security-files adds it).\n"
            ."   warning: README.md shows no security badge (phil-dev split --security-files adds it).\n"
            ."== phil/slug-bundle\n"
            ."Every package installs and passes on its own.\n",
            $tester->getDisplay(),
        );
    }

    public function testAnArchiveThatLeavesOutTheTestsFailsThePackage(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/slug/composer.json', '{"name": "phil/slug", "autoload": {"psr-4": {"Phil\\\\Slug\\\\": "src/"}}, "autoload-dev": {"psr-4": {"Phil\\\\Slug\\\\Tests\\\\": "tests/"}}}');
        $this->put($root.'/packages/slug/tests/SlugTest.php', '<?php');
        $this->put($root.'/packages/slug/.gitattributes', "/tests export-ignore\n");
        $this->put($root.'/packages/slug/SECURITY.md', '# Security Policy');
        $tester = $this->tester($root, 'check-packages');

        self::assertSame(1, $tester->execute(['packages' => ['slug']]));
        self::assertSame(
            "== phil/slug\n"
            ."phil/slug: the archive leaves out tests/ (autoload-dev psr-4), through \"/tests\" in .gitattributes\n",
            $tester->getDisplay(),
        );
        self::assertCount(4, $this->runner->runs);
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

    public function testTheRootsOtherPathRepositoriesAreGivenToEachCopy(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/composer.json', json_encode(['name' => 'phil/example-monorepo', 'repositories' => [
            ['type' => 'path', 'url' => ''],
            ['type' => 'path'],
            'not a repository',
            'members' => ['type' => 'path', 'url' => './packages/*', 'options' => ['symlink' => true]],
            ['type' => 'path', 'url' => './extra/*'],
            ['type' => 'path', 'url' => '../public/packages/*', 'options' => ['symlink' => true]],
            ['type' => 'path', 'url' => '/opt/dev-tools'],
            ['type' => 'path', 'url' => '../public/packages/*'],
            ['type' => 'vcs', 'url' => 'https://example.com/repository.git'],
        ]], \JSON_THROW_ON_ERROR));
        $this->put($root.'/phil-dev.php', "<?php return ['packages' => ['packages/*', 'extra/*']];");
        $tester = $this->tester($root, 'check-packages');

        self::assertSame(0, $tester->execute(['packages' => ['slug']]));

        $commands = $this->runner->commands();
        self::assertStringStartsWith('composer config repositories.siblings0 ', $commands[0]);
        self::assertSame([
            'composer config repositories.neighbours0 {"type":"path","url":"'.$root.'/../public/packages/*","options":{"symlink":false}}',
            'composer config repositories.neighbours1 {"type":"path","url":"/opt/dev-tools","options":{"symlink":false}}',
            'composer install --no-interaction --no-progress --quiet',
        ], \array_slice($commands, 2, 3));
    }

    public function testAPackageOnItsOwnNeedsNoSiblings(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/alone", "repositories": [{"type": "path", "url": "../beside"}]}');
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
