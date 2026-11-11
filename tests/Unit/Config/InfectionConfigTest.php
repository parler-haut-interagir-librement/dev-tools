<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Config;

use Phil\DevTools\Config\InfectionConfig;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Workspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InfectionConfig::class)]
final class InfectionConfigTest extends TestCase
{
    use TemporaryDirectory;

    public function testTheConfigurationOfAMonorepo(): void
    {
        $root = $this->fixture('monorepo');

        self::assertSame([
            'source' => [
                'directories' => [$root.'/src', $root.'/packages/slug/src', $root.'/packages/slug-bundle/src', $root.'/packages/tilttek-dice/src'],
                'excludes' => ['Infrastructure'],
            ],
            'phpUnit' => ['configDir' => $root],
            'testFrameworkOptions' => '--testsuite=Fast',
            'minMsi' => 100,
            'minCoveredMsi' => 100,
            'logs' => [
                'text' => $root.'/var/infection/infection.log',
                'summary' => $root.'/var/infection/summary.log',
                'html' => $root.'/var/infection/infection.html',
            ],
            'tmpDir' => $root.'/var/infection/tmp',
            'mutators' => [
                '@default' => true,
                'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['private const int MAX_.*', 'return hrtime.*']],
                'TrueValue' => false,
            ],
        ], new InfectionConfig(Workspace::load($root))->build());
    }

    public function testThePhpUnitConfigurationOfTheSkippedPlannedPackages(): void
    {
        $root = $this->fixture('monorepo');

        self::assertSame(['configDir' => $root.'/var/phil-dev/phpunit'], new InfectionConfig(Workspace::load($root), $root.'/var/phil-dev/phpunit')->build()['phpUnit']);
    }

    public function testDuplicatesAreDropped(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root", "autoload": {"psr-4": {"A\\\\": "src/", "B\\\\": "src/"}}}');
        $this->put($root.'/phil-dev.php', "<?php return ['infection' => ['directories' => ['src', 'src', 'lib'], 'excludes' => ['A', 'B', 'A', 'C'], 'mutators' => ['TrueValue' => ['ignore' => ['Événement::x']]]]];");

        $config = new InfectionConfig(Workspace::load($root));

        self::assertSame(['directories' => [$root.'/src', $root.'/lib'], 'excludes' => ['A', 'B', 'C']], $config->build()['source']);
        self::assertStringContainsString('"Événement::x"', $config->json());
    }

    public function testThePackageOnItsOwn(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/alone", "autoload": {"psr-4": {"Phil\\\\Alone\\\\": "src/"}}}');
        mkdir($root.'/src');
        $this->put($root.'/vendor-bin/infection/vendor/infection/infection/resources/schema.json', '{}');

        $config = new InfectionConfig(Workspace::load($root))->build();

        self::assertSame($root.'/vendor-bin/infection/vendor/infection/infection/resources/schema.json', $config['$schema']);
        self::assertSame(['directories' => [$root.'/src']], $config['source']);
        self::assertSame('--testsuite=Unit', $config['testFrameworkOptions']);
        self::assertSame(['@default' => true], $config['mutators']);
    }

    public function testTheJson(): void
    {
        $root = $this->fixture('monorepo');
        $json = new InfectionConfig(Workspace::load($root))->json();

        self::assertStringEndsWith("}\n", $json);
        self::assertStringContainsString('"minCoveredMsi": 100', $json);
        self::assertStringContainsString('"'.$root.'/src"', $json);
    }

    public function testMerge(): void
    {
        self::assertSame(['a', 'b'], InfectionConfig::merge(['a'], ['b', 'a']));
        self::assertSame(['a', 'b', 'c', 'd'], InfectionConfig::merge(['a', 'b', 'a'], ['c', 'b', 'd']));
        self::assertSame(['x' => ['a', 'b'], 'y' => 1, 'z' => 2], InfectionConfig::merge(['x' => ['a'], 'y' => 0], ['x' => ['b'], 'y' => 1, 'z' => 2]));
        self::assertSame(['x' => true], InfectionConfig::merge(['x' => ['a']], ['x' => true]));
    }
}
