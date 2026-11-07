<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit;

use Phil\DevTools\DevTools;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DevTools::class)]
final class DevToolsRootTest extends TestCase
{
    public function testTheRootFromTheVariable(): void
    {
        putenv('PHIL_DEV_ROOT=/somewhere');

        try {
            self::assertSame('/somewhere', DevTools::root());
        } finally {
            putenv('PHIL_DEV_ROOT');
        }
    }

    public function testTheRootIsTheCurrentDirectoryOtherwise(): void
    {
        self::assertSame('.', DevTools::root());

        putenv('PHIL_DEV_ROOT=');

        try {
            self::assertSame('.', DevTools::root());
        } finally {
            putenv('PHIL_DEV_ROOT');
        }
    }
}
