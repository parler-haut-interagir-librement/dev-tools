<?php

declare(strict_types=1);

namespace Phil\DevTools\Castor;

use Phil\DevTools\Exception\InvalidCastorSettings;

/**
 * What the common Castor tasks of `resources/castor/tasks.php` read of a project: the `PHIL_CASTOR` constant its
 * `castor.php` defines before importing them, every key optional.
 *
 * ```php
 * const PHIL_CASTOR = [
 *     'suites' => ['Unit', 'Integration', 'Functional'],
 *     'lint_also' => ['test_schema'],
 * ];
 * import(__DIR__.'/vendor/phil/dev-tools/resources/castor/tasks.php');
 * ```
 *
 * The class depends on nothing but PHP: the task file requires it by its path, since Castor runs without the
 * project's autoloader.
 */
final readonly class Settings
{
    /** The PHPUnit suites of `test:all`, in the order they run. */
    public const array DEFAULT_SUITES = ['Unit', 'Integration', 'Api', 'Functional', 'E2E'];

    /** The YAML files and directories of `lint:yaml`; those the project does not have are left out. */
    public const array DEFAULT_YAML = ['.github', 'config', 'compose.yaml', 'translations'];

    public const string DEFAULT_CONTAINER = 'var/cache/dev/App_KernelDevDebugContainer.xml';

    private const array KEYS = ['suites', 'coverage_threshold', 'yaml', 'templates', 'container', 'test_also', 'lint_also', 'fix_also', 'ci_also'];

    /**
     * @param non-empty-list<non-empty-string> $suites            the PHPUnit suites of `test:all`
     * @param int<0, 100>                      $coverageThreshold the percentage of covered lines `test:coverage` requires
     * @param list<non-empty-string>           $yaml              the YAML files and directories of `lint:yaml`
     * @param non-empty-string                 $templates         the directory of the Twig templates of `lint:twig`
     * @param non-empty-string                 $container         the XML dump of the dev container PHPStan reads
     * @param list<non-empty-string>           $testAlso          the functions of the project `test:all` calls after the suites
     * @param list<non-empty-string>           $lintAlso          the functions of the project `lint:all` calls after the common lints
     * @param list<non-empty-string>           $fixAlso           the functions of the project `fix:all` calls after the common fixers
     * @param list<non-empty-string>           $ciAlso            the functions of the project `ci` calls after the coverage and the lints
     */
    private function __construct(
        public array $suites,
        public int $coverageThreshold,
        public array $yaml,
        public string $templates,
        public string $container,
        public array $testAlso,
        public array $lintAlso,
        public array $fixAlso,
        public array $ciAlso,
    ) {
    }

    /**
     * The defaults, with what the project sets in their place.
     *
     * @param array<array-key, mixed> $given
     *
     * @throws InvalidCastorSettings when a key is unknown or a value has the wrong type
     */
    public static function fromArray(array $given): self
    {
        $unknown = array_diff(array_map(strval(...), array_keys($given)), self::KEYS);

        if ([] !== $unknown) {
            throw new InvalidCastorSettings(\sprintf('Unknown key(s) in PHIL_CASTOR: "%s"; the keys are "%s".', implode('", "', $unknown), implode('", "', self::KEYS)));
        }

        $suites = self::names($given, 'suites', self::DEFAULT_SUITES);

        if ([] === $suites) {
            throw new InvalidCastorSettings('PHIL_CASTOR "suites" names no PHPUnit suite.');
        }

        return new self(
            $suites,
            self::threshold($given['coverage_threshold'] ?? 100),
            self::names($given, 'yaml', self::DEFAULT_YAML),
            self::name($given, 'templates', 'templates'),
            self::name($given, 'container', self::DEFAULT_CONTAINER),
            self::names($given, 'test_also', []),
            self::names($given, 'lint_also', []),
            self::names($given, 'fix_also', []),
            self::names($given, 'ci_also', []),
        );
    }

    /**
     * The YAML paths of `lint:yaml` the project has, under its root.
     *
     * @return list<non-empty-string>
     */
    public function existingYaml(string $root): array
    {
        return array_values(array_filter($this->yaml, static fn (string $path): bool => file_exists($root.'/'.$path)));
    }

    /**
     * @return int<0, 100>
     */
    private static function threshold(mixed $value): int
    {
        if (!\is_int($value) || $value < 0 || $value > 100) {
            throw new InvalidCastorSettings('PHIL_CASTOR "coverage_threshold" is a whole percentage, from 0 to 100.');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $given
     * @param non-empty-string        $default
     *
     * @return non-empty-string
     */
    private static function name(array $given, string $key, string $default): string
    {
        $value = $given[$key] ?? $default;

        if (!\is_string($value) || '' === $value) {
            throw new InvalidCastorSettings(\sprintf('PHIL_CASTOR "%s" is a path, a non-empty string.', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $given
     * @param list<non-empty-string>  $default
     *
     * @return list<non-empty-string>
     */
    private static function names(array $given, string $key, array $default): array
    {
        $value = $given[$key] ?? $default;

        if (!\is_array($value) || !array_is_list($value)) {
            throw new InvalidCastorSettings(\sprintf('PHIL_CASTOR "%s" is a list of non-empty strings.', $key));
        }

        $names = [];

        foreach ($value as $name) {
            if (!\is_string($name) || '' === $name) {
                throw new InvalidCastorSettings(\sprintf('PHIL_CASTOR "%s" is a list of non-empty strings.', $key));
            }

            $names[] = $name;
        }

        return $names;
    }
}
