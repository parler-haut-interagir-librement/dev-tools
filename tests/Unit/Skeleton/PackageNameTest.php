<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Skeleton;

use Phil\DevTools\Exception\InvalidPackageName;
use Phil\DevTools\Skeleton\PackageName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PackageName::class)]
#[CoversClass(InvalidPackageName::class)]
final class PackageNameTest extends TestCase
{
    public function testALibrary(): void
    {
        $name = PackageName::parse('phil/opening-hours');

        self::assertFalse($name->bundle);
        self::assertSame('phil/opening-hours', $name->package());
        self::assertSame('Phil\\OpeningHours', $name->namespace());
        self::assertSame('opening-hours', $name->directory());
        self::assertSame('phil/opening-hours', $name->library());
        self::assertSame('phil_opening_hours', $name->alias());
    }

    public function testABundle(): void
    {
        $name = PackageName::parse('tilttek/quests-bundle');

        self::assertTrue($name->bundle);
        self::assertSame('Tilttek\\QuestsBundle', $name->namespace());
        self::assertSame('TilttekQuestsBundle', $name->bundleClass());
        self::assertSame('tilttek_quests', $name->alias());
        self::assertSame('tilttek/quests', $name->library());
        self::assertSame('tilttek-quests-bundle', $name->directory());
    }

    public function testTheOptionAddsTheSuffix(): void
    {
        self::assertSame('phil/slug-bundle', PackageName::parse('phil/slug', true)->package());
        self::assertSame('phil/slug-bundle', PackageName::parse('phil/slug-bundle', true)->package());
    }

    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function wrongNames(): iterable
    {
        yield 'no vendor' => ['slug', false, 'is not a package name'];
        yield 'capitals' => ['phil/Slug', false, 'is not a package name'];
        yield 'leading text' => ['x/phil/slug', false, 'is not a package name'];
        yield 'trailing dash' => ['phil/slug-', false, 'is not a package name'];
        yield 'other vendor' => ['acme/slug', false, 'The vendor "acme" is not one of phil, tilttek.'];
        yield 'bundle alone' => ['phil/bundle', false, 'A bundle needs a name before "-bundle".'];
        yield 'bundle alone, with the option' => ['phil/bundle', true, 'A bundle needs a name before "-bundle".'];
    }

    #[DataProvider('wrongNames')]
    public function testAWrongNameIsRefused(string $package, bool $bundle, string $message): void
    {
        $this->expectException(InvalidPackageName::class);
        $this->expectExceptionMessageIsOrContains($message);

        PackageName::parse($package, $bundle);
    }
}
