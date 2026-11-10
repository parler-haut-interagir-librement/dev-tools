<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Config;

use Phil\DevTools\Config\CaptainHookConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CaptainHookConfig::class)]
final class CaptainHookConfigTest extends TestCase
{
    public function testTheFastGatesOnTheStagedFiles(): void
    {
        $config = new CaptainHookConfig('vendor/bin/phil-dev')->build();
        $of = static fn (string $type): array => [['exec' => '\CaptainHook\App\Hook\Condition\FileStaged\OfType', 'args' => [$type]]];

        self::assertSame(['ansi-colors' => true, 'fail-on-first-error' => false, 'run' => ['mode' => 'shell', 'path' => 'vendor-bin/captainhook/vendor/bin/captainhook']], $config['config']);
        self::assertSame([
            'enabled' => true,
            'actions' => [
                ['action' => 'vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer check --diff --path-mode=intersection -- {$STAGED_FILES|of-type:php}', 'conditions' => $of('php')],
                ['action' => 'vendor-bin/phpstan/vendor/bin/phpstan analyse --no-progress -- {$STAGED_FILES|of-type:php}', 'conditions' => $of('php')],
                ['action' => 'vendor-bin/twig/vendor/bin/twig-cs-fixer lint --no-cache {$STAGED_FILES|of-type:twig}', 'conditions' => $of('twig')],
                ['action' => 'php vendor/bin/phil-dev normalize', 'conditions' => $of('json')],
                ['action' => 'php vendor/bin/phil-dev check-docs', 'conditions' => $of('md')],
                ['action' => 'php vendor/bin/phil-dev check-translations', 'conditions' => $of('md')],
            ],
        ], $config['pre-commit']);
    }

    public function testTheJson(): void
    {
        $json = new CaptainHookConfig('bin/phil-dev')->json();

        self::assertStringStartsWith("{\n    \"config\": {\n", $json);
        self::assertStringContainsString('"action": "php bin/phil-dev normalize",', $json);
        self::assertStringContainsString('"exec": "\\\\CaptainHook\\\\App', $json);
        self::assertStringEndsWith("}\n", $json);
    }

    public function testABinaryThatCannotBeWritten(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('The CaptainHook configuration cannot be written as JSON: Malformed UTF-8 characters, possibly incorrectly encoded');

        new CaptainHookConfig("\xB1")->json();
    }
}
