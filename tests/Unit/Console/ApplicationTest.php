<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Console;

use Phil\DevTools\Console\Application;
use Phil\DevTools\DevTools;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase
{
    public function testEveryCommand(): void
    {
        $application = new Application();

        self::assertSame(DevTools::NAME, $application->getName());
        self::assertSame(DevTools::VERSION, $application->getVersion());

        foreach (['init', 'new', 'check-docs', 'check-translations', 'stamp-translations', 'check-packages', 'infection', 'deptrac', 'witnesses', 'generate', 'services'] as $command) {
            self::assertTrue($application->has($command), $command);
        }
    }
}
