<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Config;

use Phil\DevTools\Config\BiomeConfig;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Workspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(BiomeConfig::class)]
final class BiomeConfigTest extends TestCase
{
    use TemporaryDirectory;

    public function testTheDefaults(): void
    {
        $config = new BiomeConfig(Workspace::load($this->root('<?php return [];')));

        self::assertSame('2.6.6', $config->version());
        self::assertSame(<<<'JSON'
            {
                "vcs": {
                    "enabled": false
                },
                "files": {
                    "ignoreUnknown": true
                },
                "formatter": {
                    "enabled": true,
                    "indentStyle": "space",
                    "indentWidth": 4,
                    "lineWidth": 120
                },
                "linter": {
                    "enabled": true,
                    "rules": {
                        "recommended": true
                    }
                },
                "javascript": {
                    "formatter": {
                        "quoteStyle": "single"
                    }
                }
            }

            JSON, $config->json());
    }

    public function testTheVersionAndRulesOfTheManifests(): void
    {
        $config = new BiomeConfig(Workspace::load($this->root("<?php return ['js' => ['biome' => '2.5.0', 'rules' => ['style' => ['noNonNullAssertion' => 'off']]]];")));

        self::assertSame('2.5.0', $config->version());
        self::assertSame(['enabled' => true, 'rules' => ['recommended' => true, 'style' => ['noNonNullAssertion' => 'off']]], $config->build()['linter']);
    }

    #[TestWith(["'latest'"])]
    #[TestWith(["'v2.6.6'"])]
    #[TestWith(['2'])]
    #[TestWith(["'2.6.6-beta'"])]
    public function testAnInvalidVersion(string $version): void
    {
        $config = new BiomeConfig(Workspace::load($this->root("<?php return ['js' => ['biome' => ".$version.']];')));

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('js.biome must be a Biome version, such as 2.6.6.');

        $config->version();
    }

    public function testRulesThatCannotBeWritten(): void
    {
        $config = new BiomeConfig(Workspace::load($this->root("<?php return ['js' => ['rules' => ['style' => NAN]]];")));

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('The js sections cannot be written as JSON: Inf and NaN cannot be JSON encoded');

        $config->json();
    }

    private function root(string $manifest): string
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x"}');
        $this->put($root.'/phil-dev.php', $manifest);

        return $root;
    }
}
