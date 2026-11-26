<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Castor;

use Phil\DevTools\Castor\Settings;
use Phil\DevTools\Exception\InvalidCastorSettings;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Settings::class)]
#[CoversClass(InvalidCastorSettings::class)]
final class SettingsTest extends TestCase
{
    use TemporaryDirectory;

    public function testEveryKeyHasItsDefault(): void
    {
        $settings = Settings::fromArray([]);

        self::assertSame(['Unit', 'Integration', 'Api', 'Functional', 'E2E'], $settings->suites);
        self::assertSame(100, $settings->coverageThreshold);
        self::assertSame(['.github', 'config', 'compose.yaml', 'translations'], $settings->yaml);
        self::assertSame('templates', $settings->templates);
        self::assertSame('var/cache/dev/App_KernelDevDebugContainer.xml', $settings->container);
        self::assertSame([], $settings->testAlso);
        self::assertSame([], $settings->lintAlso);
        self::assertSame([], $settings->fixAlso);
        self::assertSame([], $settings->ciAlso);
    }

    public function testTheProjectSetsEachKey(): void
    {
        $settings = Settings::fromArray([
            'suites' => ['Unit', 'Functional'],
            'coverage_threshold' => 0,
            'yaml' => ['config'],
            'templates' => 'views',
            'container' => 'var/cache/dev/App_Shared_KernelDevDebugContainer.xml',
            'test_also' => ['test_schema'],
            'lint_also' => ['lint_translations'],
            'fix_also' => ['fix_translations'],
            'ci_also' => ['test_schema', 'audit'],
        ]);

        self::assertSame(['Unit', 'Functional'], $settings->suites);
        self::assertSame(0, $settings->coverageThreshold);
        self::assertSame(['config'], $settings->yaml);
        self::assertSame('views', $settings->templates);
        self::assertSame('var/cache/dev/App_Shared_KernelDevDebugContainer.xml', $settings->container);
        self::assertSame(['test_schema'], $settings->testAlso);
        self::assertSame(['lint_translations'], $settings->lintAlso);
        self::assertSame(['fix_translations'], $settings->fixAlso);
        self::assertSame(['test_schema', 'audit'], $settings->ciAlso);
        self::assertSame(100, Settings::fromArray(['coverage_threshold' => 100])->coverageThreshold);
        self::assertSame([], Settings::fromArray(['yaml' => []])->yaml);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function provideInvalidSettings(): iterable
    {
        yield 'unknown keys' => [['suite' => ['Unit'], 'coverage_threshold' => 90, 0 => 'x'], 'Unknown key(s) in PHIL_CASTOR: "suite", "0"; the keys are "suites", "coverage_threshold", "yaml", "templates", "container", "test_also", "lint_also", "fix_also", "ci_also".'];
        yield 'no suite' => [['suites' => []], 'PHIL_CASTOR "suites" names no PHPUnit suite.'];
        yield 'suites not a list' => [['suites' => ['a' => 'Unit']], 'PHIL_CASTOR "suites" is a list of non-empty strings.'];
        yield 'suites a string' => [['suites' => 'Unit'], 'PHIL_CASTOR "suites" is a list of non-empty strings.'];
        yield 'an empty suite' => [['suites' => ['Unit', '']], 'PHIL_CASTOR "suites" is a list of non-empty strings.'];
        yield 'a suite not a string' => [['suites' => ['Unit', 3]], 'PHIL_CASTOR "suites" is a list of non-empty strings.'];
        yield 'a function not a string' => [['lint_also' => [null]], 'PHIL_CASTOR "lint_also" is a list of non-empty strings.'];
        yield 'threshold above' => [['coverage_threshold' => 101], 'PHIL_CASTOR "coverage_threshold" is a whole percentage, from 0 to 100.'];
        yield 'threshold below' => [['coverage_threshold' => -1], 'PHIL_CASTOR "coverage_threshold" is a whole percentage, from 0 to 100.'];
        yield 'threshold a float' => [['coverage_threshold' => 90.5], 'PHIL_CASTOR "coverage_threshold" is a whole percentage, from 0 to 100.'];
        yield 'threshold a string' => [['coverage_threshold' => '90'], 'PHIL_CASTOR "coverage_threshold" is a whole percentage, from 0 to 100.'];
        yield 'empty templates' => [['templates' => ''], 'PHIL_CASTOR "templates" is a path, a non-empty string.'];
        yield 'container not a string' => [['container' => ['x']], 'PHIL_CASTOR "container" is a path, a non-empty string.'];
    }

    /**
     * @param array<array-key, mixed> $given
     */
    #[DataProvider('provideInvalidSettings')]
    public function testAnInvalidSettingIsRefused(array $given, string $message): void
    {
        $this->expectException(InvalidCastorSettings::class);
        $this->expectExceptionMessageIsOrContains($message);

        Settings::fromArray($given);
    }

    public function testOnlyTheYamlPathsTheProjectHasAreLinted(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root.'/config');
        touch($root.'/compose.yaml');

        self::assertSame(['config', 'compose.yaml'], Settings::fromArray([])->existingYaml($root));
        self::assertSame([], Settings::fromArray(['yaml' => ['translations']])->existingYaml($root));
    }
}
