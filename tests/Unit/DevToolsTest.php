<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit;

use Phil\DevTools\DevTools;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DevTools::class)]
final class DevToolsTest extends TestCase
{
    use TemporaryDirectory;

    public function testItsDirectory(): void
    {
        self::assertFileExists(DevTools::directory().'/composer.json');
        self::assertSame(DevTools::directory().'/resources/compose.yaml', DevTools::resource('compose.yaml'));
    }

    public function testRelativePaths(): void
    {
        self::assertSame('vendor/phil/dev-tools/config', DevTools::relative('/project', '/project/vendor/phil/dev-tools/config'));
        self::assertSame('../dev-tools/config', DevTools::relative('/work/project', '/work/dev-tools/config'));
        self::assertSame('.', DevTools::relative('/project', '/project/'));
        self::assertSame('..', DevTools::relative('/work/project', '/work'));
        self::assertSame('../../a', DevTools::relative('/work/b/c', '/work/a'));
        self::assertSame('config', DevTools::relative(DevTools::directory(), DevTools::directory().'/config'));
    }

    public function testTheBinaryOfThisRepository(): void
    {
        self::assertSame('bin/phil-dev', DevTools::binary(DevTools::directory()));
    }

    public function testTheBinaryWhenInstalledInTheProject(): void
    {
        self::assertSame('vendor/bin/phil-dev', DevTools::binary('/nowhere/project/', '/nowhere/project/vendor/phil/dev-tools'));
        self::assertSame('../dev-tools/bin/phil-dev', DevTools::binary('/nowhere/project', '/nowhere/dev-tools'));
    }

    public function testTheBinaryOfAProject(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/vendor/bin/phil-dev', '');

        self::assertSame('vendor/bin/phil-dev', DevTools::binary($root));
        self::assertStringEndsWith('/bin/phil-dev', DevTools::binary($this->temporaryDirectory()));
    }
}
