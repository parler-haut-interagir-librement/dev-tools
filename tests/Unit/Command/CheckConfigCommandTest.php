<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\CheckConfigCommand;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CheckConfigCommand::class)]
final class CheckConfigCommandTest extends CommandTestCase
{
    public function testTheWarningsFailNothing(): void
    {
        $tester = $this->tester($this->fixture('conventions/strays'), 'check-config');

        self::assertSame(0, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringStartsWith("== phil/example-bundle\n   warning: the configuration's root key is \"phil_sample\": name it \"phil_example\", after the package.\n", $display);
        self::assertStringEndsWith("\n14 warnings on the configuration conventions.\n", $display);
        self::assertSame([], $this->runner->runs);
    }

    public function testStrictFails(): void
    {
        $tester = $this->tester($this->fixture('conventions/strays'), 'check-config');

        self::assertSame(1, $tester->execute(['--strict' => true]));
    }

    public function testABundleThatFollowsPasses(): void
    {
        $tester = $this->tester($this->fixture('conventions/follows'), 'check-config');

        self::assertSame(0, $tester->execute(['--strict' => true]));
        self::assertSame("Every bundle configuration follows the conventions.\n", $tester->getDisplay());
    }

    public function testEveryPackageIsCheckedWhateverComesBeforeIt(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/example-monorepo", "type": "project"}');
        $this->put($root.'/phil-dev.php', "<?php return ['packages' => ['packages/*']];");

        foreach (['a-clean' => 'AClean', 'b-stray' => 'BStray'] as $name => $namespace) {
            $this->put($root.'/packages/'.$name.'/composer.json', '{"name": "phil/'.$name.'-bundle", "type": "symfony-bundle", "autoload": {"psr-4": {"Phil\\\\'.$namespace.'Bundle\\\\": "src/"}}}');
        }

        $this->put($root.'/packages/a-clean/src/PhilACleanBundle.php', "<?php\nfinal class PhilACleanBundle {}\n");
        $this->put($root.'/packages/b-stray/src/PhilBStrayBundle.php', "<?php\n// DefinitionConfigurator\n\$d->rootNode()->children()->scalarNode('a')->defaultValue('b')->end();\n");
        $this->put($root.'/packages/b-stray/README.md', 'Nothing here.');

        $tester = $this->tester($root, 'check-config');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("== phil/b-stray-bundle\n   warning: option \"a\" is not named in doc/ nor in README.md: document it with its default.\n1 warnings on the configuration conventions.\n", $tester->getDisplay());
    }
}
