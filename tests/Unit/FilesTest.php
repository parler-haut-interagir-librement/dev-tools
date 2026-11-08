<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit;

use Phil\DevTools\Files;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Files::class)]
final class FilesTest extends TestCase
{
    use TemporaryDirectory;

    public function testItReadsAFile(): void
    {
        $file = $this->temporaryDirectory().'/a.txt';
        $this->put($file, 'content');

        self::assertSame('content', Files::read($file));
    }

    public function testADirectoryCannotBeRead(): void
    {
        $directory = $this->temporaryDirectory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Cannot read '.$directory.'.');

        Files::read($directory);
    }

    public function testADirectoryIsRemovedWithEverythingInIt(): void
    {
        $directory = sys_get_temp_dir().'/phil-dev-files-'.getmypid();
        mkdir($directory.'/a/b', 0o777, true);
        file_put_contents($directory.'/a/b/c.txt', 'c');
        file_put_contents($directory.'/d.txt', 'd');

        Files::removeDirectory($directory);

        self::assertDirectoryDoesNotExist($directory);
    }
}
