<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Workspace;

use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\MemberAutoloader;
use Phil\DevTools\Workspace\Workspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MemberAutoloader::class)]
final class MemberAutoloaderTest extends TestCase
{
    use TemporaryDirectory;

    public function testItFindsTheClassesOfEachMember(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root", "autoload": {"psr-4": {"Root\\\\": "src/"}}}');
        $this->put($root.'/src/Kept.php', '<?php');
        $this->put($root.'/packages/a/composer.json', '{"name": "phil/a", "autoload": {"psr-4": {"Phil\\\\A\\\\": ["src/", "lib/"], "Odd\\\\": 3}}, "autoload-dev": {"psr-4": {"Phil\\\\A\\\\Tests\\\\": "tests/"}}}');
        $this->put($root.'/packages/a/lib/Sub/Thing.php', '<?php');
        $this->put($root.'/packages/a/tests/ThingTest.php', '<?php');
        $this->put($root.'/packages/b/composer.json', '{"name": "phil/b", "autoload": {"classmap": ["lib/"]}, "autoload-dev": {"psr-4": {"Phil\\\\B\\\\": "src/"}}}');
        $this->put($root.'/packages/b/src/Other.php', '<?php');

        $autoloader = MemberAutoloader::forWorkspace(Workspace::load($root, []));

        self::assertSame($root.'/packages/a/lib/Sub/Thing.php', $autoloader->find('Phil\\A\\Sub\\Thing'));
        self::assertSame($root.'/packages/a/tests/ThingTest.php', $autoloader->find('Phil\\A\\Tests\\ThingTest'));
        self::assertNull($autoloader->find('Phil\\A\\Missing'));
        self::assertSame($root.'/packages/b/src/Other.php', $autoloader->find('Phil\\B\\Other'));
        self::assertNull($autoloader->find('Root\\Kept'));
        self::assertNull($autoloader->find('Other\\Thing'));
    }

    public function testItRegistersAndLoads(): void
    {
        $root = $this->temporaryDirectory();
        $class = 'PhilDevToolsProbe'.random_int(1, \PHP_INT_MAX);
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/packages/a/composer.json', '{"name": "phil/a", "autoload": {"psr-4": {"Probe\\\\": "src/"}}}');
        $this->put($root.'/packages/a/src/'.$class.'.php', '<?php namespace Probe; final class '.$class.' {}');

        $autoloader = MemberAutoloader::register($root);

        try {
            self::assertTrue(class_exists('Probe\\'.$class));
            $autoloader->load('Probe\\Missing');
            self::assertFalse(class_exists('Probe\\Missing', false));
            self::assertContains($autoloader->loader, spl_autoload_functions());
        } finally {
            $autoloader->unregister();
        }

        self::assertNotContains($autoloader->loader, spl_autoload_functions());
    }
}
