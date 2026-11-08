<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Config;

use Phil\DevTools\Config\Tooling;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Matrix\Matrix;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Workspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tooling::class)]
final class ToolingTest extends TestCase
{
    use TemporaryDirectory;

    public function testThePathsOfEveryPackageAnalysed(): void
    {
        $root = $this->fixture('monorepo');
        touch($root.'/phil-dev.php');
        $tooling = new Tooling(Workspace::load($root));

        self::assertSame([
            $root.'/src',
            $root.'/packages/slug/src',
            $root.'/packages/slug/tests',
            $root.'/packages/slug-bundle/src',
            $root.'/packages/slug-bundle/tests',
            $root.'/packages/tilttek-dice/src',
        ], $tooling->directories());
        self::assertSame([$root.'/packages/slug-bundle/bin/console'], $tooling->files());
        self::assertSame([
            $root.'/phil-dev.php',
            $root.'/packages/slug/phil-dev.php',
            $root.'/packages/slug-bundle/phil-dev.php',
            $root.'/packages/tilttek-dice/phil-dev.php',
        ], $tooling->configurationFiles());
        self::assertSame(['Example', 'Phil\\Slug', 'Phil\\SlugBundle', 'Tilttek\\Dice'], $tooling->namespaces());
        self::assertSame([$root.'/src', $root.'/packages/slug/src', $root.'/packages/slug-bundle/src', $root.'/packages/tilttek-dice/src'], $tooling->sourceDirectories());
        self::assertSame([$root.'/packages/slug/tests/Witnesses/PhpStan'], $tooling->witnessDirectories());
    }

    public function testTheExceptionsOfTheManifests(): void
    {
        $root = $this->fixture('monorepo');
        $tooling = new Tooling(Workspace::load($root));

        self::assertSame(['reference.php', 'src/Generated'], $tooling->codeStyleExclusions());
        self::assertSame(['phpdoc_to_comment' => true], $tooling->codeStyleRules());
        self::assertSame(['Some\\Rule', $root.'/packages/slug-bundle/src/Skipped', 'Other\\Rule' => [$root.'/packages/slug-bundle/src/One.php']], $tooling->rectorSkips());
        self::assertSame(['Legacy'], $tooling->arkitectExtraNamespaces());
        self::assertSame([], $tooling->arkitectRules());
        self::assertSame([
            $root.'/src/Domain/*',
            $root.'/src/*/Domain/*',
            $root.'/packages/slug/src/Domain/*',
            $root.'/packages/slug/src/*/Domain/*',
            $root.'/packages/slug-bundle/src/Domain/*',
            $root.'/packages/slug-bundle/src/*/Domain/*',
            $root.'/packages/tilttek-dice/src/Domain/*',
            $root.'/packages/tilttek-dice/src/*/Domain/*',
        ], $tooling->layerPatterns('Domain'));
    }

    public function testThePhpStanParameters(): void
    {
        $root = $this->fixture('monorepo');
        $phpstan = new Tooling(Workspace::load($root, ['slug-bundle']))->phpstan();

        self::assertSame([$root.'/packages/slug/extension.neon'], $phpstan['includes']);
        self::assertSame(Matrix::load()->phpVersionRange(), $phpstan['parameters']['phpVersion']);
        self::assertSame([$root.'/packages/slug-bundle/src', $root.'/packages/slug-bundle/tests', $root.'/packages/slug-bundle/bin/console'], $phpstan['parameters']['paths']);
        self::assertSame(['*/tests/Fixtures/*', '*/var/*', $root.'/packages/slug-bundle/src/Generated/*'], $phpstan['parameters']['excludePaths']['analyse']);
        self::assertSame([
            ['identifier' => 'argument.type', 'path' => $root.'/packages/slug-bundle/src/Legacy.php'],
            ['identifier' => 'return.type', 'paths' => [$root.'/packages/slug-bundle/src/A.php', '*/B.php']],
            '#^A message$#',
        ], $phpstan['parameters']['ignoreErrors']);
    }

    public function testSeveralOfEach(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/phil-dev.php', "<?php return ['paths' => ['src', 'one.php', 'two.php'], 'cs' => ['rules' => ['a' => true, 'b' => false, 3 => 'x']], 'arkitect' => ['namespaces' => ['One', 'Two'], 'rules' => static fn (): array => []], 'rector' => ['skip' => ['Legacy.php', 'Rule' => ['a.php', 'b.php']]]];");
        $this->put($root.'/packages/tilttek-dice/phil-dev.php', "<?php return ['arkitect' => ['rules' => static fn (): array => []]];");
        $this->put($root.'/one.php', '');
        $this->put($root.'/two.php', '');
        mkdir($root.'/packages/tilttek-dice/tests/Witnesses/PhpStan', 0o777, true);
        $tooling = new Tooling(Workspace::load($root));

        self::assertSame([$root.'/one.php', $root.'/two.php', $root.'/packages/slug-bundle/bin/console'], $tooling->files());
        self::assertSame(['a' => true, 'b' => false], $tooling->codeStyleRules());
        self::assertSame(['One', 'Two', 'Legacy'], $tooling->arkitectExtraNamespaces());
        self::assertCount(2, $tooling->arkitectRules());
        self::assertSame([$root.'/packages/slug/tests/Witnesses/PhpStan', $root.'/packages/tilttek-dice/tests/Witnesses/PhpStan'], $tooling->witnessDirectories());
        self::assertSame([$root.'/Legacy.php', 'Rule' => [$root.'/a.php', $root.'/b.php'], 'Some\\Rule', $root.'/packages/slug-bundle/src/Skipped', 'Other\\Rule' => [$root.'/packages/slug-bundle/src/One.php']], $tooling->rectorSkips());
        self::assertSame([$root.'/src', $root.'/packages/slug/src', $root.'/packages/slug/tests', $root.'/packages/slug-bundle/src', $root.'/packages/slug-bundle/tests', $root.'/packages/tilttek-dice/src', $root.'/packages/tilttek-dice/tests', $root.'/one.php', $root.'/two.php', $root.'/packages/slug-bundle/bin/console'], $tooling->phpstan()['parameters']['paths']);
    }

    public function testRectorSkipsAccumulate(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/phil-dev.php', "<?php return ['rector' => ['skip' => ['Other\\\\Rule' => 'src/Root.php']]];");

        self::assertSame(
            ['Other\\Rule' => [$root.'/src/Root.php', $root.'/packages/slug-bundle/src/One.php'], 'Some\\Rule', $root.'/packages/slug-bundle/src/Skipped'],
            new Tooling(Workspace::load($root))->rectorSkips(),
        );
    }

    public function testArkitectRulesAreCallables(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/phil-dev.php', "<?php return ['arkitect' => ['rules' => static fn (): array => []]];");

        self::assertCount(1, new Tooling(Workspace::load($root))->arkitectRules());
    }

    public function testArkitectRulesThatAreNotCallablesAreRefused(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/phil-dev.php', "<?php return ['arkitect' => ['rules' => 'nothing']];");

        $this->expectException(InvalidManifest::class);

        new Tooling(Workspace::load($root))->arkitectRules();
    }

    public function testARectorSkipThatIsNeitherARuleNorAPathIsRefused(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/phil-dev.php', "<?php return ['rector' => ['skip' => [3]]];");

        $this->expectException(InvalidManifest::class);

        new Tooling(Workspace::load($root))->rectorSkips();
    }

    public function testARectorSkipWithoutPathsIsRefused(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/phil-dev.php', "<?php return ['rector' => ['skip' => ['Rule' => [3]]]];");

        $this->expectException(InvalidManifest::class);

        new Tooling(Workspace::load($root))->rectorSkips();
    }

    public function testTheRootComesFromTheEnvironment(): void
    {
        $root = $this->fixture('monorepo');
        putenv('PHIL_DEV_ROOT='.$root);

        try {
            $tooling = Tooling::fromEnvironment();
        } finally {
            putenv('PHIL_DEV_ROOT');
        }

        self::assertSame($root, $tooling->workspace->root->dir);
        self::assertSame($root, Tooling::fromEnvironment($root)->workspace->root->dir);
    }

    public function testThePhpStanIncludeIsWrittenWhenItChanges(): void
    {
        $root = $this->fixture('monorepo');
        $tooling = new Tooling(Workspace::load($root));

        $file = $tooling->phpstanInclude('phpstan', ['parameters' => ['paths' => ["it's\\here", 'a/b']]]);

        self::assertSame($root.'/var/phil-dev/phpstan.php', $file);
        self::assertSame(<<<'PHP'
            <?php

            // Generated by phil-dev from the phil-dev.php of each package. Do not edit.

            return json_decode('{
                "parameters": {
                    "paths": [
                        "it\'s\\\\here",
                        "a/b"
                    ]
                }
            }', true, flags: \JSON_THROW_ON_ERROR);

            PHP, file_get_contents($file));
        $tooling->phpstanInclude('phpstan', ['parameters' => ['paths' => ["it's\\here"]]]);
        self::assertSame(['parameters' => ['paths' => ["it's\\here"]]], require $file);

        touch($file, 1);
        clearstatcache();
        $tooling->phpstanInclude('phpstan', ['parameters' => ['paths' => ["it's\\here"]]]);
        clearstatcache();
        self::assertSame(1, filemtime($file));

        $tooling->phpstanInclude('phpstan', ['parameters' => []]);
        self::assertSame(['parameters' => []], require $file);
    }

    public function testTheTemplateAndScriptDirectories(): void
    {
        $root = $this->fixture('monorepo');
        mkdir($root.'/templates');
        mkdir($root.'/packages/slug-bundle/src/Resources/views', 0o777, true);
        mkdir($root.'/packages/slug-bundle/assets');
        mkdir($root.'/packages/slug/views');
        mkdir($root.'/packages/slug/js');
        mkdir($root.'/packages/slug/templates');
        file_put_contents($root.'/packages/slug/phil-dev.php', "<?php return ['twig' => ['paths' => ['views', 'views', 'missing']], 'js' => ['paths' => ['js']]];");

        $tooling = new Tooling(Workspace::load($root));

        self::assertSame([$root.'/templates', $root.'/packages/slug/views', $root.'/packages/slug-bundle/src/Resources/views'], $tooling->templateDirectories());
        self::assertSame([$root.'/packages/slug/js', $root.'/packages/slug-bundle/assets'], $tooling->scriptDirectories());
        self::assertSame(['templates', 'src/Resources/views', 'Resources/views'], Tooling::TEMPLATE_DIRECTORIES);
        self::assertSame(['assets'], Tooling::SCRIPT_DIRECTORIES);
    }
}
