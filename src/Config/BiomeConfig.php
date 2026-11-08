<?php

declare(strict_types=1);

namespace Phil\DevTools\Config;

use JsonException;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Workspace\Workspace;

/**
 * The Biome configuration of the Stimulus controllers and other scripts of a workspace: the recommended lint rules,
 * and a format close to the PHP code style — four spaces, single quotes, 120 columns. Each manifest's `js.rules`
 * changes the level of a rule by group, such as `['style' => ['noNonNullAssertion' => 'off']]`.
 */
final readonly class BiomeConfig
{
    /**
     * The Biome version downloaded when the root's manifest does not name one under `js.biome`.
     */
    public const string VERSION = '2.6.6';

    public function __construct(private Workspace $workspace)
    {
    }

    public function version(): string
    {
        $version = $this->workspace->root->manifest->get('js', 'biome');

        if (null === $version) {
            return self::VERSION;
        }

        if (!\is_string($version) || 1 !== preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new InvalidManifest('js.biome must be a Biome version, such as 2.6.6.');
        }

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $rules = ['recommended' => true];

        foreach ($this->workspace->analysed() as $package) {
            $rules = InfectionConfig::merge($rules, $package->manifest->map('js', 'rules'));
        }

        return [
            'vcs' => ['enabled' => false],
            'files' => ['ignoreUnknown' => true],
            'formatter' => ['enabled' => true, 'indentStyle' => 'space', 'indentWidth' => 4, 'lineWidth' => 120],
            'linter' => ['enabled' => true, 'rules' => $rules],
            'javascript' => ['formatter' => ['quoteStyle' => 'single']],
        ];
    }

    public function json(): string
    {
        try {
            return json_encode($this->build(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n";
        } catch (JsonException $jsonException) {
            throw new InvalidManifest('The js sections cannot be written as JSON: '.$jsonException->getMessage(), $jsonException->getCode(), previous: $jsonException);
        }
    }
}
