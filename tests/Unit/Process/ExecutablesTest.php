<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Process;

use Phil\DevTools\Process\Executables;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Executables::class)]
final class ExecutablesTest extends TestCase
{
    use TemporaryDirectory;

    public function testTheFirstExecutableOfThePath(): void
    {
        $first = $this->temporaryDirectory();
        $second = $this->temporaryDirectory();
        $this->put($first.'/vale', '');
        $this->put($second.'/vale', '');
        chmod($second.'/vale', 0o755);
        mkdir($first.'/cspell');

        $executables = new Executables([$first, $second.'/']);

        self::assertSame($second.'/vale', $executables->find('vale'));
        self::assertNull($executables->find('cspell'));
        self::assertNull($executables->find('npx'));
    }

    public function testThePathOfTheEnvironment(): void
    {
        $directory = $this->temporaryDirectory();
        $this->put($directory.'/splitsh-lite', '');
        chmod($directory.'/splitsh-lite', 0o755);
        $previous = getenv('PATH');
        putenv('PATH='.\PATH_SEPARATOR.$directory.\PATH_SEPARATOR);

        try {
            self::assertSame($directory.'/splitsh-lite', Executables::fromEnvironment()->find('splitsh-lite'));
            putenv('PATH');
            self::assertNull(Executables::fromEnvironment()->find('splitsh-lite'));
        } finally {
            putenv('PATH='.(false === $previous ? '' : $previous));
        }
    }
}
