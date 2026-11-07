<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Config;

use Phil\DevTools\Config\DeptracConfig;
use Phil\DevTools\Config\Layer;
use Phil\DevTools\Config\LayerKind;
use Phil\DevTools\Config\YamlWriter;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Workspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Randomizer;

#[CoversClass(DeptracConfig::class)]
#[CoversClass(Layer::class)]
#[CoversClass(LayerKind::class)]
#[CoversClass(YamlWriter::class)]
final class DeptracConfigTest extends TestCase
{
    use TemporaryDirectory;

    public function testTheLayersOfEveryKnownPackage(): void
    {
        $root = $this->fixture('monorepo');
        $config = new DeptracConfig(Workspace::load($root))->build()['deptrac'];

        self::assertSame([$root.'/src', $root.'/packages/slug/src', $root.'/packages/slug-bundle/src', $root.'/packages/tilttek-dice/src'], $config['paths']);

        $forbidden = '^(?!Example\\\\.*)(?!Phil\\\\Slug\\\\.*)(?!Phil\\\\SlugBundle\\\\Domain\\\\.*)(?!Phil\\\\SlugBundle\\\\Application\\\\.*)(?!Phil\\\\SlugBundle\\\\Infrastructure\\\\.*)'
            .'(?!Phil\\\\SlugBundle\\\\[^\\\\]+$)(?!Tilttek\\\\Dice\\\\.*)(?!Phil\\\\Resource\\\\.*)'
            .'(?!Symfony\\\\Component\\\\.*)(?!Symfony\\\\Contracts\\\\.*)(?!Symfony\\\\Polyfill\\\\.*)(?!Psr\\\\.*)'
            .'(?!Egulias\\\\EmailValidator\\\\.*)(?![a-z]+)(?!Random\\\\)[^\\\\]+\\\\.+';

        self::assertSame([
            ['name' => 'ExampleApp', 'collectors' => [['type' => 'classLike', 'value' => '^Example\\\\.*']]],
            ['name' => 'PhilSlug', 'collectors' => [['type' => 'classLike', 'value' => '^Phil\\\\Slug\\\\.*']]],
            ['name' => 'PhilSlugBundleDomain', 'collectors' => [['type' => 'classLike', 'value' => '^Phil\\\\SlugBundle\\\\Domain\\\\.*']]],
            ['name' => 'PhilSlugBundleApplication', 'collectors' => [['type' => 'classLike', 'value' => '^Phil\\\\SlugBundle\\\\Application\\\\.*']]],
            ['name' => 'PhilSlugBundleInfrastructure', 'collectors' => [['type' => 'classLike', 'value' => '^Phil\\\\SlugBundle\\\\Infrastructure\\\\.*']]],
            ['name' => 'PhilSlugBundle', 'collectors' => [['type' => 'classLike', 'value' => '^Phil\\\\SlugBundle\\\\[^\\\\]+$']]],
            ['name' => 'TilttekDice', 'collectors' => [['type' => 'classLike', 'value' => '^Tilttek\\\\Dice\\\\.*']]],
            ['name' => 'PhilResource', 'collectors' => [['type' => 'classLike', 'value' => '^Phil\\\\Resource\\\\.*']]],
            ['name' => 'DefaultAllowed', 'collectors' => [
                ['type' => 'classLike', 'value' => '^Symfony\\\\Component\\\\.*'],
                ['type' => 'classLike', 'value' => '^Symfony\\\\Contracts\\\\.*'],
                ['type' => 'classLike', 'value' => '^Symfony\\\\Polyfill\\\\.*'],
                ['type' => 'classLike', 'value' => '^Psr\\\\.*'],
            ]],
            ['name' => 'AllowedEguliasEmailValidator', 'collectors' => [['type' => 'classLike', 'value' => '^Egulias\\\\EmailValidator\\\\.*']]],
            ['name' => 'Allowed2', 'collectors' => [['type' => 'classLike', 'value' => '[a-z]+']]],
            ['name' => 'Forbidden', 'collectors' => [['type' => 'classLike', 'value' => $forbidden]]],
        ], $config['layers']);

        foreach (['Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle' => 1, 'Symfony\\Component\\Yaml\\Yaml' => 0, 'Phil\\SlugBundle\\PhilSlugBundle' => 0, 'Phil\\SlugBundle\\Twig\\Extension' => 1, Randomizer::class => 0, 'DateTimeImmutable' => 0] as $class => $matches) {
            $found = preg_match('/'.$forbidden.'/', $class);
            self::assertSame($matches, $found, $class);
        }
    }

    public function testTheDefaultRules(): void
    {
        $ruleset = new DeptracConfig(Workspace::load($this->fixture('monorepo')))->build()['deptrac']['ruleset'];

        self::assertSame([
            'ExampleApp' => ['DefaultAllowed'],
            'PhilSlug' => ['DefaultAllowed', 'AllowedEguliasEmailValidator', 'PhilResource'],
            'PhilSlugBundleDomain' => ['DefaultAllowed', 'PhilSlug', 'PhilResource'],
            'PhilSlugBundleApplication' => ['PhilSlugBundleDomain', 'DefaultAllowed', 'Forbidden', 'PhilSlug', 'PhilResource'],
            'PhilSlugBundleInfrastructure' => ['PhilSlugBundleDomain', 'PhilSlugBundleApplication', 'DefaultAllowed', 'Forbidden', 'PhilSlug', 'PhilResource', 'TilttekDice'],
            'PhilSlugBundle' => ['PhilSlugBundleDomain', 'PhilSlugBundleApplication', 'PhilSlugBundleInfrastructure', 'DefaultAllowed', 'Forbidden', 'PhilSlug', 'PhilResource', 'TilttekDice'],
            'TilttekDice' => ['DefaultAllowed'],
            'PhilResource' => ['DefaultAllowed', 'AllowedEguliasEmailValidator', 'Allowed2'],
        ], $ruleset);
    }

    public function testALibraryNeverSeesABundle(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/packages/core-bundle/composer.json', '{"name": "phil/core-bundle", "type": "symfony-bundle", "autoload": {"psr-4": {"Phil\\\\CoreBundle\\\\": "src/"}}}');
        $this->put($root.'/packages/lib/composer.json', '{"name": "phil/lib", "autoload": {"psr-4": {"Phil\\\\Lib\\\\": "src/", "Phil\\\\LibExtra\\\\": "extra/"}}}');
        $this->put($root.'/packages/other/composer.json', '{"name": "phil/other", "require": {"phil/core-bundle": "*", "phil/lib": "*"}, "autoload": {"psr-4": {"Phil\\\\Other\\\\": "src/"}}}');
        $this->put($root.'/packages/app-bundle/composer.json', '{"name": "phil/app-bundle", "type": "symfony-bundle", "require": {"phil/core-bundle": "*", "phil/lib": "*", "phil/unknown": "*"}, "require-dev": {"phil/lib": "*"}, "autoload": {"psr-4": {"Phil\\\\AppBundle\\\\": "src/"}}}');

        $config = new DeptracConfig(Workspace::load($root))->build()['deptrac'];
        $ruleset = $config['ruleset'];

        self::assertSame(['DefaultAllowed', 'PhilLib', 'PhilLibExtra'], $ruleset['PhilOther']);
        self::assertSame(['DefaultAllowed', 'PhilCoreBundleDomain', 'PhilLib', 'PhilLibExtra'], $ruleset['PhilAppBundleDomain']);
        self::assertSame(['PhilAppBundleDomain', 'DefaultAllowed', 'Forbidden', 'PhilCoreBundleDomain', 'PhilCoreBundleApplication', 'PhilCoreBundle', 'PhilLib', 'PhilLibExtra'], $ruleset['PhilAppBundleApplication']);
        self::assertSame(['PhilAppBundleDomain', 'PhilAppBundleApplication', 'DefaultAllowed', 'Forbidden', 'PhilCoreBundleDomain', 'PhilCoreBundleApplication', 'PhilCoreBundleInfrastructure', 'PhilCoreBundle', 'PhilLib', 'PhilLibExtra'], $ruleset['PhilAppBundleInfrastructure']);
    }

    public function testTheRootReplacesTheDefaultAndDeclaresItsLayers(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root", "require": {"phil/lib": "*"}}');
        $this->put($root.'/phil-dev.php', "<?php return ['deptrac' => ['default' => ['^Psr\\\\\\\\Clock\\\\\\\\.*', '^Root\\\\\\\\Value\\\\\\\\.*'], 'allow' => ['^Psr\\\\\\\\Clock\\\\\\\\.*', '^Vendor\\\\\\\\Tool\\\\\\\\.*', '^Vendor\\\\\\\\Tool\\\\\\\\.*'], 'layers' => ['Core' => ['^Root\\\\\\\\Core\\\\\\\\.*', '^Root\\\\\\\\Kernel$'], 'Shared' => '^Root\\\\\\\\Shared\\\\\\\\.*', 'Ui' => '^Root\\\\\\\\Ui\\\\\\\\.*'], 'ruleset' => ['Ui' => ['Nothing', 'Core', 'Ui', 'Core', 'Shared']]]];");
        $this->put($root.'/packages/lib/composer.json', '{"name": "phil/lib", "autoload": {"psr-4": {"Phil\\\\Lib\\\\": "src/"}}}');
        $this->put($root.'/packages/lib/phil-dev.php', "<?php return ['deptrac' => ['allow' => ['^Vendor\\\\\\\\Tool\\\\\\\\.*']]];");

        $config = new DeptracConfig(Workspace::load($root))->build()['deptrac'];

        self::assertSame(['Core', 'Shared', 'Ui', 'PhilLib', 'DefaultAllowed', 'AllowedVendorTool', 'Forbidden'], array_column($config['layers'], 'name'));
        self::assertSame(['^Root\\\\Core\\\\.*', '^Root\\\\Kernel$'], array_column($config['layers'][0]['collectors'], 'value'));
        self::assertSame([
            ['type' => 'classLike', 'value' => '^Psr\\\\Clock\\\\.*'],
            ['type' => 'classLike', 'value' => '^Root\\\\Value\\\\.*'],
        ], $config['layers'][4]['collectors']);
        self::assertSame([
            'Core' => ['DefaultAllowed', 'AllowedVendorTool', 'PhilLib'],
            'Shared' => ['DefaultAllowed', 'AllowedVendorTool', 'PhilLib'],
            'Ui' => ['Core', 'Shared'],
            'PhilLib' => ['DefaultAllowed', 'AllowedVendorTool'],
        ], $config['ruleset']);
    }

    public function testABundleWithTwoNamespaces(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root", "type": "symfony-bundle", "autoload": {"psr-4": {"A\\\\": "a/", "B\\\\": "b/"}}}');

        $ruleset = new DeptracConfig(Workspace::load($root))->build()['deptrac']['ruleset'];

        self::assertSame(['ADomain', 'BDomain', 'DefaultAllowed', 'Forbidden'], $ruleset['AApplication']);
        self::assertSame(['ADomain', 'BDomain', 'AApplication', 'BApplication', 'DefaultAllowed', 'Forbidden'], $ruleset['BInfrastructure']);
        self::assertSame(['ADomain', 'BDomain', 'AApplication', 'BApplication', 'AInfrastructure', 'BInfrastructure', 'DefaultAllowed', 'Forbidden'], $ruleset['A']);
    }

    public function testTheNamesOfAllowedLayers(): void
    {
        self::assertSame('AllowedLeagueUri', DeptracConfig::allowedName('^League\\\\Uri\\\\.*', 1));
        self::assertSame('AllowedLeague', DeptracConfig::allowedName('^League\\\\(Uri|Csv)\\\\.*', 2));
        self::assertSame('Allowed3', DeptracConfig::allowedName('League\\\\Uri\\\\.*', 3));
        self::assertSame('Allowed4', DeptracConfig::allowedName('^League.*', 4));
    }

    public function testTheExtraConfigurationsOfTheRoot(): void
    {
        $root = $this->fixture('monorepo');

        self::assertSame([$root.'/deptrac-contexts.yaml'], new DeptracConfig(Workspace::load($root))->extraConfigurations());
    }

    public function testTheYaml(): void
    {
        $yaml = new DeptracConfig(Workspace::load($this->fixture('monorepo')))->yaml();

        self::assertStringStartsWith("# Generated by phil-dev from the composer.json and phil-dev.php of each package.\n# Do not edit", $yaml);
        self::assertStringContainsString("  layers:\n    - name: 'ExampleApp'\n      collectors:\n        - type: 'classLike'\n          value: '^Example\\\\.*'\n", $yaml);
        self::assertStringContainsString("  ruleset:\n    ExampleApp:\n      - 'DefaultAllowed'\n", $yaml);
    }

    public function testTwoPackagesCannotDeclareTheSameLayer(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root", "autoload": {"psr-4": {"Phil\\\\Lib\\\\": "src/"}}}');
        $this->put($root.'/packages/lib/composer.json', '{"name": "phil/lib", "autoload": {"psr-4": {"Phil\\\\Lib\\\\": "src/"}}}');

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('The layer "PhilLib" of phil/lib is already declared by another package.');

        new DeptracConfig(Workspace::load($root))->build();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wrongSections(): iterable
    {
        yield 'layers not a map' => ["['layers' => 'x']"];
        yield 'layer without a name' => ["['layers' => ['^x']]"];
        yield 'layer of a number' => ["['layers' => ['A' => 3]]"];
        yield 'layer holding a number' => ["['layers' => ['A' => ['^a', 3]]]"];
        yield 'ruleset not a list' => ["['ruleset' => ['A' => 'B']]"];
        yield 'ruleset holding a number' => ["['ruleset' => ['A' => ['B', 3]]]"];
        yield 'ruleset without a layer' => ["['ruleset' => [['B']]]"];
    }

    #[DataProvider('wrongSections')]
    public function testAWrongSectionIsRefused(string $section): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/phil-dev.php', "<?php return ['deptrac' => ".$section.'];');

        $this->expectException(InvalidManifest::class);

        new DeptracConfig(Workspace::load($root))->build();
    }

    public function testTheLayerName(): void
    {
        self::assertSame('TilttekQuestsBundle', DeptracConfig::name('Tilttek\\QuestsBundle'));
    }

    public function testWhatEachKindIsSeenBy(): void
    {
        $seen = static fn (LayerKind $kind): array => [new Layer('A', [], $kind)->seenByDomains(), new Layer('A', [], $kind)->seenByApplications()];

        self::assertSame([true, true], $seen(LayerKind::Library));
        self::assertSame([true, true], $seen(LayerKind::Domain));
        self::assertSame([false, true], $seen(LayerKind::Application));
        self::assertSame([false, false], $seen(LayerKind::Infrastructure));
        self::assertSame([false, true], $seen(LayerKind::Bundle));
    }
}
