<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Config;

use Phil\DevTools\Config\DocsLintConfig;
use Phil\DevTools\DevTools;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Workspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocsLintConfig::class)]
final class DocsLintConfigTest extends TestCase
{
    use TemporaryDirectory;

    public function testTheFilesOfAPackage(): void
    {
        $root = $this->root('<?php return [];');
        $this->put($root.'/README.md', '');
        $this->put($root.'/SECURITY.md', '');
        $this->put($root.'/doc/a.md', '');
        $this->put($root.'/doc/topic/b.md', '');
        $this->put($root.'/doc/c.txt', '');
        $this->put($root.'/NOTES.md', '');

        $workspace = Workspace::load($root);

        self::assertSame([$root.'/README.md', $root.'/SECURITY.md', $root.'/doc/a.md', $root.'/doc/topic/b.md'], new DocsLintConfig($workspace)->files($workspace->root));
        self::assertSame([], new DocsLintConfig(Workspace::load($this->root('<?php return [];')))->files(Workspace::load($this->root('<?php return [];'))->root));
    }

    public function testTheFrenchFilesAreReadApart(): void
    {
        $root = $this->root('<?php return [];');

        foreach (['README.md', 'README.fr.md', 'doc/a.md', 'doc/fr/a.md', 'doc/fr/sub/b.md', 'doc/fr-glossary.md'] as $file) {
            $this->put($root.'/'.$file, '');
        }

        $workspace = Workspace::load($root);
        $config = new DocsLintConfig($workspace);
        $french = json_decode($config->cspell($workspace->root, true), true, flags: \JSON_THROW_ON_ERROR);
        $english = json_decode($config->cspell($workspace->root), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([$root.'/README.md', $root.'/doc/a.md'], $config->files($workspace->root));
        self::assertSame([$root.'/README.fr.md', $root.'/doc/fr/a.md', $root.'/doc/fr/sub/b.md', $root.'/doc/fr-glossary.md'], $config->translations($workspace->root));
        self::assertIsArray($french);
        self::assertIsArray($english);
        self::assertSame('fr,en,en-GB', $french['language']);
        self::assertSame('en,en-GB', $english['language']);
    }

    public function testEnglishByDefault(): void
    {
        $workspace = Workspace::load($this->root("<?php return ['docs' => ['words' => ['PHIL', 'Example', 'PHIL', 'Élise']]];"));
        $config = new DocsLintConfig($workspace);

        self::assertSame('en', $config->language($workspace->root));
        self::assertSame([
            'version' => '0.2',
            'language' => 'en,en-GB',
            'dictionaryDefinitions' => [['name' => 'phil', 'path' => DevTools::resource('docs/words.txt'), 'addWords' => false]],
            'dictionaries' => ['phil'],
            'words' => ['PHIL', 'Example', 'Élise'],
            'ignoreRegExpList' => ['/```[\s\S]*?```/g', '/`[^`\n]+`/g', '/\]\([^)]*\)/g'],
        ], json_decode($config->cspell($workspace->root), true, flags: \JSON_THROW_ON_ERROR));
        self::assertStringContainsString("\n    \"words\": [\n        \"PHIL\",\n        \"Example\",\n        \"Élise\"\n    ],\n", $config->cspell($workspace->root));
        self::assertStringContainsString('"path": "'.DevTools::resource('docs/words.txt').'"', $config->cspell($workspace->root));
        self::assertStringEndsWith("}\n", $config->cspell($workspace->root));
    }

    public function testAFrenchMemberWithTheWordsOfTheRoot(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/phil-dev.php', "<?php return ['docs' => ['words' => ['PHIL']]];");
        $this->put($root.'/packages/slug/phil-dev.php', "<?php return ['docs' => ['language' => 'fr', 'words' => ['grimoire']]];");
        $workspace = Workspace::load($root);
        $slug = $workspace->targets(['slug'])[0];
        $config = new DocsLintConfig($workspace);

        $cspell = json_decode($config->cspell($slug), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('fr', $config->language($slug));
        self::assertIsArray($cspell);
        self::assertSame('fr,en,en-GB', $cspell['language']);
        self::assertSame(['@cspell/dict-fr-fr/cspell-ext.json'], $cspell['import']);
        self::assertSame(['PHIL', 'grimoire'], $cspell['words']);
        self::assertSame('en', $config->language($workspace->targets(['tilttek-dice'])[0]));

        $this->put($root.'/phil-dev.php', "<?php return ['docs' => ['language' => 'fr']];");
        $this->put($root.'/packages/slug/phil-dev.php', "<?php return ['docs' => ['language' => 'en']];");
        $workspace = Workspace::load($root);
        self::assertSame('en', new DocsLintConfig($workspace)->language($workspace->targets(['slug'])[0]));
        self::assertSame('fr', new DocsLintConfig($workspace)->language($workspace->targets(['tilttek-dice'])[0]));
    }

    public function testTheLanguageOfTheRootIsTheDefault(): void
    {
        $workspace = Workspace::load($this->root("<?php return ['docs' => ['language' => 'fr']];"));

        self::assertSame('fr', new DocsLintConfig($workspace)->language($workspace->root));
    }

    public function testAnUnknownLanguage(): void
    {
        $workspace = Workspace::load($this->root("<?php return ['docs' => ['language' => 'de']];"));

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('docs.language of phil/x is one of en, fr.');

        new DocsLintConfig($workspace)->language($workspace->root);
    }

    public function testTheDefaultMarkdownlintRules(): void
    {
        self::assertSame(<<<'JSON'
            {
                "config": {
                    "default": true,
                    "MD013": {
                        "line_length": 120,
                        "code_blocks": false,
                        "tables": false
                    },
                    "MD024": {
                        "siblings_only": true
                    },
                    "MD060": false
                }
            }

            JSON, new DocsLintConfig(Workspace::load($this->root('<?php return [];')))->markdownlint());
    }

    public function testMarkdownlintAndVale(): void
    {
        $config = new DocsLintConfig(Workspace::load($this->root("<?php return ['docs' => ['markdownlint' => ['MD013' => ['line_length' => 100], 'MD033' => false]]];")));

        self::assertSame(['config' => [
            'default' => true,
            'MD013' => ['line_length' => 100, 'code_blocks' => false, 'tables' => false],
            'MD024' => ['siblings_only' => true],
            'MD060' => false,
            'MD033' => false,
        ]], json_decode($config->markdownlint(), true, flags: \JSON_THROW_ON_ERROR));
        self::assertSame(
            "# Generated by phil-dev. Do not edit.\nStylesPath = ".DevTools::resource('docs/vale')."\nMinAlertLevel = warning\n\n[*.md]\nBasedOnStyles = Vale, Phil\n# cspell checks the spelling, with the words of each package.\nVale.Spelling = NO\n",
            $config->vale(),
        );
        self::assertFileExists(DevTools::resource('docs/vale/Phil/Hedging.yml'));
    }

    public function testRulesThatCannotBeWritten(): void
    {
        $config = new DocsLintConfig(Workspace::load($this->root("<?php return ['docs' => ['markdownlint' => ['MD013' => NAN]]];")));

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('The docs section cannot be written as JSON: Inf and NaN cannot be JSON encoded');

        $config->markdownlint();
    }

    private function root(string $manifest): string
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x"}');
        $this->put($root.'/phil-dev.php', $manifest);

        return $root;
    }
}
