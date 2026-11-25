<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Conventions;

use Phil\DevTools\Conventions\ConfigurationConventions;
use Phil\DevTools\Conventions\ConfigurationOption;
use Phil\DevTools\Conventions\ConfigurationTreeReader;
use Phil\DevTools\Conventions\RecipeReader;
use Phil\DevTools\Conventions\TreeFrame;
use Phil\DevTools\DevTools;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\PackageRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigurationConventions::class)]
#[CoversClass(ConfigurationOption::class)]
#[CoversClass(ConfigurationTreeReader::class)]
#[CoversClass(RecipeReader::class)]
#[CoversClass(TreeFrame::class)]
final class ConfigurationConventionsTest extends TestCase
{
    use TemporaryDirectory;

    public function testABundleThatStraysIsWarnedAbout(): void
    {
        self::assertSame([
            'the configuration\'s root key is "phil_sample": name it "phil_example", after the package.',
            'option "locale" is not named in doc/ nor in README.md: document it with its default.',
            'option "connection" is not named in doc/ nor in README.md: document it with its default.',
            'option "host" has no default: give it one, or declare it isRequired().',
            'option "host" is not named in doc/ nor in README.md: document it with its default.',
            'option "template" is not named in doc/ nor in README.md: document it with its default.',
            'option "mail.ratio" is not named in doc/ nor in README.md: document it with its default.',
            'option "mode" is not named in doc/ nor in README.md: document it with its default.',
            'the recipe recipe/0.10/config/packages/phil_sample.yaml sets "title" to its default, Status: leave it out.',
            'the recipe recipe/0.10/config/packages/phil_sample.yaml sets "enabled" to its default, true: leave it out.',
            'the recipe recipe/0.10/config/packages/phil_sample.yaml sets "connection" to its default, null: leave it out.',
            'the recipe recipe/0.10/config/packages/phil_sample.yaml sets "mail.sender" to its default, no-reply@example.com: leave it out.',
            'the recipe recipe/0.10/config/packages/phil_sample.yaml sets "mail.ratio" to its default, 0.5: leave it out.',
            'the recipe recipe/0.10/config/packages/phil_sample.yaml sets "mode" to its default, a: leave it out.',
        ], new ConfigurationConventions()->check($this->bundle('strays')));
    }

    public function testABundleThatFollowsTheConventionsPasses(): void
    {
        self::assertSame([], new ConfigurationConventions()->check($this->bundle('follows')));
    }

    public function testAPackageThatIsNoBundleOrHasNoTreeIsSkipped(): void
    {
        $library = $this->temporaryDirectory();
        $this->put($library.'/composer.json', '{"name": "phil/example", "autoload": {"psr-4": {"Phil\\\\Example\\\\": "src/"}}}');
        $this->put($library.'/src/Tree.php', "<?php\n// TreeBuilder\n\$t->scalarNode('x')->end();\n");
        $bundle = $this->temporaryDirectory();
        $this->put($bundle.'/composer.json', '{"name": "phil/bare-bundle", "type": "symfony-bundle", "autoload": {"psr-4": {"Phil\\\\BareBundle\\\\": "src/"}}}');
        $this->put($bundle.'/src/PhilBareBundle.php', "<?php\nfinal class PhilBareBundle {}\n");

        self::assertSame([], new ConfigurationConventions()->check(Package::fromDirectory($library, '', PackageRole::Root)));
        self::assertSame([], new ConfigurationConventions()->check(Package::fromDirectory($bundle, '', PackageRole::Root)));
    }

    public function testTheRootKeyOfAnExtensionIsRead(): void
    {
        $bundle = $this->temporaryDirectory();
        $this->put($bundle.'/composer.json', '{"name": "phil/legacy-bundle", "type": "symfony-bundle", "autoload": {"psr-4": {"Phil\\\\LegacyBundle\\\\": "src/"}}}');
        $this->put($bundle.'/src/DependencyInjection/LegacyExtension.php', "<?php\nfinal class LegacyExtension {\n    public function getAlias(): string\n    {\n        return 'legacy';\n    }\n}\n");
        $this->put($bundle.'/src/DependencyInjection/Configuration.php', "<?php\n\$tree = new TreeBuilder('legacy');\n\$tree->getRootNode()->children()->scalarNode('a')->defaultValue('b')->end();\n");
        $this->put($bundle.'/README.md', 'Set `a`.');

        self::assertSame(['the configuration\'s root key is "legacy": name it "phil_legacy", after the package.'], new ConfigurationConventions()->check(Package::fromDirectory($bundle, '', PackageRole::Root)));
    }

    public function testARecipeWithoutTheConfigurationFileIsLeftAlone(): void
    {
        $bundle = $this->temporaryDirectory();
        $this->put($bundle.'/composer.json', '{"name": "phil/routes-bundle", "type": "symfony-bundle", "autoload": {"psr-4": {"Phil\\\\RoutesBundle\\\\": "src/"}}}');
        $this->put($bundle.'/src/PhilRoutesBundle.php', "<?php\n// DefinitionConfigurator\n\$d->rootNode()->children()->scalarNode('prefix')->defaultValue('/r')->end();\n");
        $this->put($bundle.'/doc/routes.md', "```yaml\nprefix: /r\n```\n");
        $this->put($bundle.'/recipe/0.1/config/routes/phil_routes.yaml', "phil_routes:\n    prefix: /r\n");

        self::assertSame([], new ConfigurationConventions()->check(Package::fromDirectory($bundle, '', PackageRole::Root)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function packageNames(): iterable
    {
        yield 'a bundle' => ['phil/rate-limit-bundle', 'phil_rate_limit'];
        yield 'a Tilttek bundle' => ['tilttek/dice-probability-bundle', 'tilttek_dice_probability'];
        yield 'no suffix' => ['phil/slides', 'phil_slides'];
        yield 'the suffix only at the end' => ['phil/bundle-flex', 'phil_bundle_flex'];
        yield 'no vendor' => ['Example', 'example_'];
    }

    #[DataProvider('packageNames')]
    public function testTheRootKeyFollowsThePackageName(string $package, string $key): void
    {
        self::assertSame($key, new ConfigurationConventions()->expectedRootKey($package));
    }

    private function bundle(string $name): Package
    {
        return Package::fromDirectory(DevTools::directory().'/tests/Fixtures/conventions/'.$name, '', PackageRole::Root);
    }
}
