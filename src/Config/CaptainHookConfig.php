<?php

declare(strict_types=1);

namespace Phil\DevTools\Config;

use JsonException;
use RuntimeException;

/**
 * The CaptainHook configuration `init` writes: before each commit, the fast gates on the staged files only — the code
 * style and PHPStan on the PHP files, the code style of the Twig templates, the normalized `composer.json`, the
 * documentation checks and those of its translations. Each action runs only when a file of its type is staged; the slow gates stay in
 * `composer gates`.
 */
final readonly class CaptainHookConfig
{
    private const string OF_TYPE = '\\CaptainHook\\App\\Hook\\Condition\\FileStaged\\OfType';

    /**
     * @param string $binary the path the project calls `phil-dev` by
     */
    public function __construct(private string $binary)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return [
            'config' => [
                'ansi-colors' => true,
                'fail-on-first-error' => false,
                // CaptainHook lives in its own vendor-bin: the hooks call it there.
                'run' => ['mode' => 'shell', 'path' => 'vendor-bin/captainhook/vendor/bin/captainhook'],
            ],
            'pre-commit' => [
                'enabled' => true,
                'actions' => [
                    $this->action('vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer check --diff --path-mode=intersection -- {$STAGED_FILES|of-type:php}', 'php'),
                    $this->action('vendor-bin/phpstan/vendor/bin/phpstan analyse --no-progress -- {$STAGED_FILES|of-type:php}', 'php'),
                    $this->action('vendor-bin/twig/vendor/bin/twig-cs-fixer lint --no-cache {$STAGED_FILES|of-type:twig}', 'twig'),
                    $this->action('php '.$this->binary.' normalize', 'json'),
                    $this->action('php '.$this->binary.' check-docs', 'md'),
                    $this->action('php '.$this->binary.' check-translations', 'md'),
                ],
            ],
        ];
    }

    public function json(): string
    {
        try {
            return json_encode($this->build(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n";
        } catch (JsonException $jsonException) {
            throw new RuntimeException('The CaptainHook configuration cannot be written as JSON: '.$jsonException->getMessage(), $jsonException->getCode(), previous: $jsonException);
        }
    }

    /**
     * @return array{action: string, conditions: list<array{exec: string, args: list<string>}>}
     */
    private function action(string $command, string $type): array
    {
        return ['action' => $command, 'conditions' => [['exec' => self::OF_TYPE, 'args' => [$type]]]];
    }
}
