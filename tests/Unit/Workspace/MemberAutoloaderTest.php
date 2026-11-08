<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Workspace;

use Composer\Autoload\ClassLoader;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\MemberAutoloader;
use Phil\DevTools\Workspace\Workspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

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

    public function testItAddsThePrefixesToComposersLoader(): void
    {
        $root = $this->temporaryDirectory();
        $class = 'PhilDevToolsProbe'.random_int(1, \PHP_INT_MAX);
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/packages/a/composer.json', '{"name": "phil/a", "autoload": {"psr-4": {"Probe\\\\": "src/"}}}');
        $this->put($root.'/packages/a/src/'.$class.'.php', '<?php namespace Probe; final class '.$class.' {}');
        $classLoader = new ClassLoader();
        $classLoader->addPsr4('Probe\\', '/kept');
        $classLoader->register();

        try {
            $autoloader = MemberAutoloader::register($root, $classLoader);

            self::assertSame(['Probe\\' => ['/kept', $root.'/packages/a/src']], $classLoader->getPrefixesPsr4());
            self::assertSame($root.'/packages/a/src/'.$class.'.php', $classLoader->findFile('Probe\\'.$class));
            self::assertTrue(class_exists('Probe\\'.$class));
            self::assertNotContains($autoloader->loader, spl_autoload_functions());

            $classLoader->addPsr4('Probe\\', '/later');
            $autoloader->unregister();

            self::assertSame(['Probe\\' => ['/kept', '/later']], $classLoader->getPrefixesPsr4());
        } finally {
            $classLoader->unregister();
        }
    }

    public function testItAddsThePrefixesToTheComposerLoaderOfTheRoot(): void
    {
        $file = new ReflectionClass(ClassLoader::class)->getFileName();
        self::assertIsString($file);
        $vendor = \dirname($file, 2);
        $root = \dirname($vendor);
        $loader = ClassLoader::getRegisteredLoaders()[$vendor];
        $autoloader = MemberAutoloader::register($root);

        try {
            self::assertNotContains($autoloader->loader, spl_autoload_functions());
            self::assertSame([], array_diff(array_merge(...array_values($autoloader->prefixes)), array_merge(...array_values($loader->getPrefixesPsr4()))));
        } finally {
            $autoloader->unregister();
        }
    }

    public function testItPrefersTheComposerLoaderOfTheRoot(): void
    {
        $root = (string) realpath($this->temporaryDirectory());
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/packages/a/composer.json', '{"name": "phil/a", "autoload": {"psr-4": {"Probe\\\\": "src/"}}}');
        $registered = ClassLoader::getRegisteredLoaders();
        $first = reset($registered);
        self::assertInstanceOf(ClassLoader::class, $first);
        $classLoader = new ClassLoader($root.'/vendor');
        $classLoader->register();

        try {
            $autoloader = MemberAutoloader::register($root);
            self::assertSame(['Probe\\' => [$root.'/packages/a/src']], $classLoader->getPrefixesPsr4());
            self::assertArrayNotHasKey('Probe\\', $first->getPrefixesPsr4());
            $autoloader->unregister();
        } finally {
            $classLoader->unregister();
        }
    }

    public function testItFallsBackOnTheFirstComposerLoader(): void
    {
        $root = (string) realpath($this->temporaryDirectory());
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/packages/a/composer.json', '{"name": "phil/a", "autoload": {"psr-4": {"Probe\\\\Fallback\\\\": "src/"}}}');
        $registered = ClassLoader::getRegisteredLoaders();
        $first = reset($registered);
        self::assertInstanceOf(ClassLoader::class, $first);

        $autoloader = MemberAutoloader::register($root);

        try {
            self::assertSame([$root.'/packages/a/src'], $first->getPrefixesPsr4()['Probe\\Fallback\\'] ?? null);
            self::assertNotContains($autoloader->loader, spl_autoload_functions());
        } finally {
            $autoloader->unregister();
        }
    }

    public function testItRegistersAndLoadsWithoutComposer(): void
    {
        $root = $this->temporaryDirectory();
        $class = 'PhilDevToolsProbe'.random_int(1, \PHP_INT_MAX);
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/packages/a/composer.json', '{"name": "phil/a", "autoload": {"psr-4": {"Probe\\\\": "src/"}}}');
        $this->put($root.'/packages/a/src/'.$class.'.php', '<?php namespace Probe; final class '.$class.' {}');
        $loaders = new ReflectionProperty(ClassLoader::class, 'registeredLoaders');
        $registered = $loaders->getValue();
        $loaders->setValue(null, []);

        try {
            $autoloader = MemberAutoloader::register($root);
        } finally {
            $loaders->setValue(null, $registered);
        }

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
