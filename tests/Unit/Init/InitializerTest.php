<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Init;

use Phil\DevTools\DevTools;
use Phil\DevTools\Init\Initializer;
use Phil\DevTools\Init\InitResult;
use Phil\DevTools\Init\InitStatus;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Initializer::class)]
#[CoversClass(InitResult::class)]
#[CoversClass(InitStatus::class)]
final class InitializerTest extends TestCase
{
    use TemporaryDirectory;

    public function testAPackage(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x"}');
        mkdir($root.'/src');
        mkdir($root.'/tests/Unit', 0o777, true);
        mkdir($root.'/tests/Functional', 0o777, true);

        $results = new Initializer($root)->run(false, false);
        $config = DevTools::relative($root, DevTools::directory().'/config');

        self::assertSame(
            ['phil-dev.php', '.php-cs-fixer.dist.php', 'rector.php', 'arkitect.php', 'phpstan.neon', 'phpbench.json', 'phpunit.dist.xml', 'composer-dependency-analyser.php',
                'vendor-bin/arkitect/composer.json', 'vendor-bin/deptrac/composer.json', 'vendor-bin/infection/composer.json', 'vendor-bin/php-cs-fixer/composer.json',
                'vendor-bin/phpbench/composer.json', 'vendor-bin/phpstan/composer.json', 'vendor-bin/rector/composer.json', 'composer.json'],
            array_map(static fn (InitResult $result): string => $result->file, $results),
        );
        self::assertSame([InitStatus::Created], array_values(array_unique(array_map(static fn (InitResult $result): InitStatus => $result->status, \array_slice($results, 0, -1)), \SORT_REGULAR)));
        self::assertSame(InitStatus::Updated, $results[15]->status);

        self::assertStringContainsString("require __DIR__.'/".$config."/php-cs-fixer.php';", (string) file_get_contents($root.'/.php-cs-fixer.dist.php'));
        self::assertStringContainsString('    - '.$config.'/phpstan.neon', (string) file_get_contents($root.'/phpstan.neon'));
        self::assertStringContainsString('"runner.path": "tests"', (string) file_get_contents($root.'/phpbench.json'));
        self::assertStringContainsString("'paths' => ['src', 'tests'],", (string) file_get_contents($root.'/phil-dev.php'));

        $phpunit = (string) file_get_contents($root.'/phpunit.dist.xml');
        self::assertStringContainsString("<testsuite name=\"Unit\">\n            <directory>tests/Unit</directory>\n        </testsuite>", $phpunit);
        self::assertStringContainsString("<testsuite name=\"Integration\">\n            <directory>tests/Functional</directory>\n        </testsuite>", $phpunit);
        self::assertStringNotContainsString('packages/*', $phpunit);
        self::assertStringNotContainsString('{{', $phpunit);

        $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertSame(Initializer::DEV_REQUIRES, $composer['require-dev']);
        self::assertSame(['allow-plugins' => ['bamarni/composer-bin-plugin' => true], 'process-timeout' => 0], $composer['config']);
        self::assertSame(['bamarni-bin' => ['bin-links' => false, 'forward-command' => true, 'target-directory' => 'vendor-bin']], $composer['extra']);
        self::assertIsArray($composer['scripts']);
        self::assertArrayNotHasKey('paquets', $composer['scripts']);
        self::assertSame(['@deps', '@cs', '@phpstan', '@rector', '@deptrac', '@arkitect', '@test', '@infection'], $composer['scripts']['portes']);
        self::assertSame('@php '.DevTools::binary($root).' infection --changed', $composer['scripts']['infection:changed']);
        self::assertSame(new Initializer($root)->descriptions(false), $composer['scripts-descriptions']);
    }

    public function testAMonorepo(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        mkdir($root.'/tests', 0o777, true);

        $initializer = new Initializer($root);
        $files = $initializer->files(true);

        self::assertArrayNotHasKey('composer-dependency-analyser.php', $files);
        self::assertStringContainsString("'packages' => ['packages/*'],", $files['phil-dev.php']);
        self::assertStringContainsString('"runner.path": "packages"', $files['phpbench.json']);
        self::assertStringContainsString("<directory>tests</directory>\n            <directory>packages/*/tests/Unit</directory>", $files['phpunit.dist.xml']);
        self::assertStringContainsString("<directory>packages/*/tests/Integration</directory>\n            <directory>packages/*/tests/Functional</directory>", $files['phpunit.dist.xml']);
        self::assertStringContainsString("<include>\n            <directory>packages/*/src</directory>\n        </include>", $files['phpunit.dist.xml']);
        self::assertArrayHasKey('paquets', $initializer->scripts(true));
        self::assertArrayHasKey('paquets', $initializer->descriptions(true));
        self::assertArrayNotHasKey('paquets', $initializer->descriptions(false));
    }

    public function testAPackageWithoutTests(): void
    {
        $root = $this->temporaryDirectory();

        $phpunit = new Initializer($root)->files(false)['phpunit.dist.xml'];

        self::assertStringContainsString("<testsuite name=\"Unit\">\n        </testsuite>", $phpunit);
        self::assertStringNotContainsString('<directory>', $phpunit);
    }

    public function testTheRecorderExtensionWhenTheBridgeIsInstalled(): void
    {
        $root = $this->temporaryDirectory();
        self::assertStringNotContainsString('<extensions>', new Initializer($root)->files(false)['phpunit.dist.xml']);

        mkdir($root.'/vendor/symfony/phpunit-bridge', 0o777, true);

        self::assertStringEndsWith("    </source>\n\n    <extensions>\n        <bootstrap class=\"Symfony\\Bridge\\PhpUnit\\SymfonyExtension\" />\n    </extensions>\n</phpunit>\n", new Initializer($root)->files(false)['phpunit.dist.xml']);
    }

    public function testItIsIdempotent(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x"}');
        new Initializer($root)->run(false, false);

        $results = new Initializer($root)->run(false, false);

        self::assertSame([InitStatus::Unchanged], array_values(array_unique(array_map(static fn (InitResult $result): InitStatus => $result->status, $results), \SORT_REGULAR)));
    }

    public function testItKeepsWhatDiffersUnlessForced(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x", "scripts": {"cs": "mine"}}');
        $this->put($root.'/rector.php', '<?php // mine');
        $this->put($root.'/phil-dev.php', '<?php return [];');

        $results = new Initializer($root)->run(false, false);

        self::assertSame(InitStatus::Kept, $results[0]->status);
        self::assertSame(InitStatus::Kept, $results[2]->status);
        self::assertSame('<?php // mine', file_get_contents($root.'/rector.php'));
        self::assertSame(InitStatus::Updated, $results[15]->status);
        self::assertSame(['scripts.cs'], $results[15]->kept);

        $forced = new Initializer($root)->run(false, true);

        self::assertSame(InitStatus::Kept, $forced[0]->status);
        self::assertSame('<?php return [];', file_get_contents($root.'/phil-dev.php'));
        self::assertSame(InitStatus::Updated, $forced[2]->status);
        self::assertStringContainsString('rector.php', (string) file_get_contents($root.'/rector.php'));
        self::assertSame(InitStatus::Updated, $forced[15]->status);
        self::assertSame([], $forced[15]->kept);
    }

    public function testComposerJsonIsKeptWhenOnlyDifferencesRemain(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x", "scripts": {"cs": "mine"}}');
        new Initializer($root)->run(false, false);

        $results = new Initializer($root)->run(false, false);

        self::assertSame(InitStatus::Kept, $results[15]->status);
    }

    public function testThePackageFiles(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root.'/src');
        mkdir($root.'/tests/Unit', 0o777, true);
        mkdir($root.'/tests/Integration', 0o777, true);

        $this->assertGolden('package', new Initializer($root), $root, false);
    }

    public function testTheMonorepoFiles(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root.'/tests');

        $this->assertGolden('monorepo', new Initializer($root), $root, true);
    }

    private function assertGolden(string $name, Initializer $initializer, string $root, bool $monorepo): void
    {
        $golden = \dirname(__DIR__, 2).'/Fixtures/init/'.$name;
        $config = DevTools::relative($root, DevTools::directory().'/config');

        foreach ($initializer->files($monorepo) as $file => $content) {
            self::assertStringEqualsFile($golden.'/'.$file, str_replace($config, '@config@', $content), $file);
        }

        $scripts = json_encode(['scripts' => $initializer->scripts($monorepo), 'descriptions' => $initializer->descriptions($monorepo)], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n";
        self::assertStringEqualsFile($golden.'/scripts.json', str_replace(DevTools::binary($root), '@bin@', $scripts));
    }
}
