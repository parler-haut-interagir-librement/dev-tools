<?php

declare(strict_types=1);

namespace {{namespace}}\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The smoke test of a package being written: its namespace is autoloaded from `src/`.
 */
#[CoversNothing]
final class PackageTest extends TestCase
{
    public function testTheNamespaceIsAutoloadedFromSrc(): void
    {
        $directory = \dirname(__DIR__, 2);
        $composer = json_decode((string) file_get_contents($directory.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        $autoload = $composer['autoload'] ?? null;

        self::assertSame(['psr-4' => ['{{namespace_php}}\\' => 'src/']], $autoload);
        self::assertDirectoryExists($directory.'/src');
    }
}
