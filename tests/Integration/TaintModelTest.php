<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Integration;

use Phil\DevTools\Command\TaintCommand;
use Phil\DevTools\Config\PsalmConfig;
use Phil\DevTools\Console\Application;
use Phil\DevTools\DevTools;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The taint model of `phil-dev taint` run by Psalm on tests/Fixtures/taint: each line of `src/Links.php` that ends
 * with "// reported" is reported as `request_host` reaching an email, and no other line is.
 */
#[CoversClass(PsalmConfig::class)]
#[CoversClass(TaintCommand::class)]
final class TaintModelTest extends TestCase
{
    use TemporaryDirectory;

    public function testTheHostOfTheRequestIsFollowedToTheEmail(): void
    {
        $psalm = DevTools::directory().'/vendor-bin/psalm/vendor/bin/psalm';

        if (!is_file($psalm)) {
            self::markTestSkipped('Psalm is not installed in vendor-bin/psalm.');
        }

        $root = $this->fixture('taint');
        $this->put($root.'/vendor-bin/psalm/vendor/bin/psalm', "#!/bin/sh\nexec ".escapeshellarg(\PHP_BINARY).' '.escapeshellarg($psalm)." \"\$@\"\n");
        chmod($root.'/vendor-bin/psalm/vendor/bin/psalm', 0o755);
        $tester = new CommandTester(new Application($root)->find('taint'));

        $exitCode = $tester->execute(['arguments' => ['--no-cache', '--threads=1']]);

        $expected = [];

        foreach (explode("\n", (string) file_get_contents($root.'/src/Links.php')) as $index => $line) {
            if (str_ends_with($line, '// reported')) {
                $expected[] = \sprintf('%s/src/Links.php:%d TaintedCustom: Detected tainted %s', $root, $index + 1, PsalmConfig::REQUEST_HOST);
            }
        }

        self::assertSame(1, $exitCode, $tester->getDisplay());
        self::assertCount(9, $expected);
        self::assertSame([...$expected, '9 tainted paths.'], explode("\n", trim($tester->getDisplay())));
    }
}
