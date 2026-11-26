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
                ['action' => 'php vendor/bin/phil-dev staged php-cs-fixer', 'conditions' => $of('php')],
                ['action' => 'php vendor/bin/phil-dev staged phpstan', 'conditions' => $of('php')],
                ['action' => 'php vendor/bin/phil-dev staged twig-cs-fixer', 'conditions' => $of('twig')],
                ['action' => 'php vendor/bin/phil-dev normalize', 'conditions' => $of('json')],
                ['action' => 'php vendor/bin/phil-dev check-docs', 'conditions' => $of('md')],
                ['action' => 'php vendor/bin/phil-dev check-translations', 'conditions' => $of('md')],
            ],
        ], $config['pre-commit']);
    }

    public function testTheMonorepoConfigurationOfTheWorkflowIsTheGeneratedOne(): void
    {
        $workflow = (string) file_get_contents(\dirname(__DIR__, 3).'/doc/workflow.md');

        self::assertSame(1, preg_match('/### The hooks of a monorepo.*?```json\n(.*?)\n```/s', $workflow, $match));
        self::assertSame(new CaptainHookConfig('vendor/bin/phil-dev')->build(), json_decode($match[1], true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testTheJson(): void
    {
        $json = new CaptainHookConfig('bin/phil-dev')->json();

        self::assertStringStartsWith("{\n    \"config\": {\n", $json);
        self::assertStringContainsString('"action": "php bin/phil-dev normalize",', $json);
        self::assertStringContainsString('"exec": "\\\\CaptainHook\\\\App', $json);
        self::assertStringEndsWith("}\n", $json);
    }

    public function testNoStagedFileNameReachesAShell(): void
    {
        self::assertStringNotContainsString('STAGED_FILES', new CaptainHookConfig('vendor/bin/phil-dev')->json());
    }

    public function testABinaryThatCannotBeWritten(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('The CaptainHook configuration cannot be written as JSON: Malformed UTF-8 characters, possibly incorrectly encoded');

        new CaptainHookConfig("\xB1")->json();
    }
}
