<?php

/*
 * The common Castor tasks of a Symfony application: the tests by suite, the coverage and its threshold, Infection, the
 * taint analysis, the lints, the static analysis, the fixers and the local continuous integration, each through the
 * tools and the `phil-dev` checks of phil/dev-tools.
 *
 * A project's castor.php imports this file from the Composer package, then keeps only its own tasks:
 *
 *     defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);
 *     const PHIL_CASTOR = ['suites' => ['Unit', 'Integration', 'Functional']];
 *     import(__DIR__.'/vendor/phil/dev-tools/resources/castor/tasks.php');
 *
 * See doc/castor.md for the tasks, the PHIL_CASTOR settings and the helpers a project's own tasks call.
 */

declare(strict_types=1);

namespace Phil\DevTools\Castor;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Phil\DevTools\Exception\DevToolsException;
use Phil\DevTools\Exception\InvalidCastorSettings;

use function Castor\context;
use function Castor\exit_code;
use function Castor\io;
use function Castor\run;
use function Castor\task;

// Every task runs from the directory of the project, whichever directory castor is called from.
\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

// Castor runs without the project's autoloader, whose Symfony components could clash with its own: the few classes
// the tasks read depend on nothing but PHP and are required by their path.
interface_exists(DevToolsException::class, false) || require \dirname(__DIR__, 2).'/src/Exception/DevToolsException.php';
class_exists(InvalidCastorSettings::class, false) || require \dirname(__DIR__, 2).'/src/Exception/InvalidCastorSettings.php';
class_exists(Settings::class, false) || require \dirname(__DIR__, 2).'/src/Castor/Settings.php';
class_exists(Commands::class, false) || require \dirname(__DIR__, 2).'/src/Castor/Commands.php';

// The tools, each installed in its own vendor-bin/<tool>/ by bamarni/composer-bin-plugin, and phil-dev.
const PHPUNIT = 'vendor/bin/phpunit';
const PHIL_DEV = 'vendor/bin/phil-dev';
const PHP_CS_FIXER = 'vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer';
const PHPSTAN = 'vendor-bin/phpstan/vendor/bin/phpstan';
const RECTOR = 'vendor-bin/rector/vendor/bin/rector';
const ARKITECT = 'vendor-bin/arkitect/vendor/bin/phparkitect';
const CAPTAINHOOK = 'vendor-bin/captainhook/vendor/bin/captainhook';
const COVERAGE_DIRECTORY = 'var/coverage';
const COVERAGE_TEXT_REPORT = 'var/coverage/coverage-text.txt';

// Helpers ————————————————————————————————————————————————————————————————————

/**
 * The settings of the project: its PHIL_CASTOR constant over the defaults.
 */
function settings(): Settings
{
    /** @var array<array-key, mixed> $given */
    $given = \defined('PHIL_CASTOR') ? \constant('PHIL_CASTOR') : [];

    return Settings::fromArray($given);
}

/**
 * The description of the task as a title, when that task is the one the person called and not a step of another.
 */
function title(string $name): void
{
    $task = task();

    if (null !== $task && $task->getName() === $name) {
        io()->title($task->getDescription());
    }
}

/**
 * Says how a task ended, and passes its exit code on.
 */
function success(int $exitCode): int
{
    if (0 === $exitCode) {
        io()->success('Done!');
    } else {
        io()->error(\sprintf('Failure (exit code %d returned).', $exitCode));
    }

    return $exitCode;
}

function aborted(string $message = 'Aborted'): void
{
    io()->warning($message);
}

/**
 * One exit code for several steps: 0 only when every step succeeded.
 */
function aggregate(int ...$exitCodes): int
{
    return Commands::aggregate(...$exitCodes);
}

/**
 * Calls the functions of the project a setting names, after the common steps, and returns their exit codes; a function
 * that returns nothing counts as a success.
 *
 * @param list<non-empty-string> $functions
 *
 * @return list<int>
 */
function also(string $setting, array $functions): array
{
    $exitCodes = [];

    foreach ($functions as $function) {
        if (!\is_callable($function)) {
            io()->error(\sprintf('PHIL_CASTOR "%s" names "%s", which is not a function.', $setting, $function));
            $exitCodes[] = 1;

            continue;
        }

        $exitCode = $function();
        $exitCodes[] = \is_int($exitCode) ? $exitCode : 0;
    }

    return $exitCodes;
}

/**
 * @param list<string> $suites
 */
function phpunit(array $suites, string $filter = '', string $options = ''): int
{
    $exitCode = exit_code(Commands::phpunit(PHPUNIT, $suites, $filter, $options));
    io()->newLine();

    return $exitCode;
}

// Tests ——————————————————————————————————————————————————————————————————————

#[AsTask(name: 'all', namespace: 'test', description: 'Run every test suite, eg: "castor test --filter=slug --options=--testdox"', aliases: ['test'])]
function test_all(
    #[AsOption(description: 'A PHPUnit filter')] string $filter = '',
    #[AsOption(description: 'More PHPUnit options')] string $options = '--stop-on-failure',
): int {
    title('test:all');
    $settings = settings();

    return aggregate(phpunit($settings->suites, $filter, $options), ...also('test_also', $settings->testAlso));
}

#[AsTask(name: 'suite', namespace: 'test', description: 'Run one test suite of phpunit.dist.xml, eg: "castor test:suite Unit"')]
function test_suite(
    #[AsArgument(description: 'The name of the suite')] string $suite,
    #[AsOption(description: 'A PHPUnit filter')] string $filter = '',
    #[AsOption(description: 'More PHPUnit options')] string $options = '',
): int {
    title('test:suite');

    return phpunit([$suite], $filter, $options);
}

#[AsTask(name: 'unit', namespace: 'test', description: 'Run the unit tests only', aliases: ['test-unit'])]
function test_unit(#[AsOption] string $filter = '', #[AsOption] string $options = ''): int
{
    title('test:unit');

    return phpunit(['Unit'], $filter, $options);
}

#[AsTask(name: 'integration', namespace: 'test', description: 'Run the integration tests only', aliases: ['test-integration'])]
function test_integration(#[AsOption] string $filter = '', #[AsOption] string $options = ''): int
{
    title('test:integration');

    return phpunit(['Integration'], $filter, $options);
}

#[AsTask(name: 'functional', namespace: 'test', description: 'Run the functional tests only', aliases: ['test-functional'])]
function test_functional(#[AsOption] string $filter = '', #[AsOption] string $options = ''): int
{
    title('test:functional');

    return phpunit(['Functional'], $filter, $options);
}

#[AsTask(name: 'api', namespace: 'test', description: 'Run the API tests only', aliases: ['test-api'])]
function test_api(#[AsOption] string $filter = '', #[AsOption] string $options = ''): int
{
    title('test:api');

    return phpunit(['Api'], $filter, $options);
}

#[AsTask(name: 'e2e', namespace: 'test', description: 'Run the end-to-end tests only', aliases: ['test-e2e'])]
function test_e2e(#[AsOption] string $filter = '', #[AsOption] string $options = ''): int
{
    title('test:e2e');

    return phpunit(['E2E'], $filter, $options);
}

#[AsTask(name: 'next', namespace: 'test', description: 'Run the tests on the next PHP version, in its Docker image')]
function test_next(): int
{
    title('test:next');

    return success(exit_code(\sprintf('%s php next -- %s', PHIL_DEV, PHPUNIT)));
}

#[AsTask(name: 'coverage', namespace: 'test', description: 'Generate the HTML code coverage report (var/coverage) and check its threshold', aliases: ['coverage'])]
function coverage(): int
{
    title('test:coverage');
    $settings = settings();
    exit_code(\sprintf('rm -rf ./%s/*', COVERAGE_DIRECTORY));

    $exitCode = exit_code(
        Commands::phpunit('php -d memory_limit=-1 '.PHPUNIT, $settings->suites, options: \sprintf('--coverage-html=%s --coverage-text=%s', COVERAGE_DIRECTORY, COVERAGE_TEXT_REPORT)),
        context: context()->withEnvironment(['XDEBUG_MODE' => 'coverage']),
    );

    if (0 !== $exitCode) {
        return success($exitCode);
    }

    $report = is_file(COVERAGE_TEXT_REPORT) ? (string) file_get_contents(COVERAGE_TEXT_REPORT) : '';
    $covered = Commands::coveredLines($report);

    if (!Commands::reaches($report, $settings->coverageThreshold)) {
        io()->error(null === $covered
            ? \sprintf('%s holds no coverage summary: is a coverage driver (Xdebug or PCOV) enabled?', COVERAGE_TEXT_REPORT)
            : \sprintf('%.2f %% of the lines are covered, under the threshold of %d %%.', $covered, $settings->coverageThreshold));

        return 1;
    }

    io()->writeln(\sprintf('%.2f %% of the lines are covered; the threshold is %d %%.', $covered, $settings->coverageThreshold));

    return success(0);
}

#[AsTask(name: 'infection', namespace: 'test', description: 'Mutation testing with Infection', aliases: ['infection'])]
function infection(): int
{
    title('test:infection');

    return success(exit_code(\sprintf('%s infection', PHIL_DEV)));
}

// Security ———————————————————————————————————————————————————————————————————

#[AsTask(name: 'taint', namespace: 'security', description: 'Follow the untrusted inputs to HTML, SQL, shells and files with the taint analysis of Psalm', aliases: ['taint'])]
function taint(): int
{
    title('security:taint');

    return success(exit_code(\sprintf('%s taint', PHIL_DEV)));
}

// Lints and static analysis ——————————————————————————————————————————————————

#[AsTask(name: 'php', namespace: 'lint', description: 'Check the code style of the PHP files with PHP-CS-Fixer', aliases: ['lint-php'])]
function lint_php(): int
{
    title('lint:php');

    return success(exit_code(\sprintf('%s check --diff', PHP_CS_FIXER)));
}

#[AsTask(name: 'twig', namespace: 'lint', description: 'Lint the Twig templates and check their code style', aliases: ['lint-twig'])]
function lint_twig(): int
{
    title('lint:twig');
    $templates = settings()->templates;

    if (!is_dir($templates)) {
        io()->note(\sprintf('No "%s" directory: no Twig template to lint.', $templates));

        return 0;
    }

    return success(aggregate(
        exit_code(\sprintf('bin/console lint:twig %s', escapeshellarg($templates))),
        exit_code(\sprintf('%s twig', PHIL_DEV)),
    ));
}

#[AsTask(name: 'js', namespace: 'lint', description: 'Check the Stimulus controllers and the styles with Biome', aliases: ['lint-js'])]
function lint_js(): int
{
    title('lint:js');

    return success(exit_code(\sprintf('%s js', PHIL_DEV)));
}

#[AsTask(name: 'container', namespace: 'lint', description: 'Lint the Symfony container', aliases: ['lint-container'])]
function lint_container(): int
{
    title('lint:container');

    return success(exit_code('bin/console lint:container'));
}

#[AsTask(name: 'yaml', namespace: 'lint', description: 'Lint the YAML files', aliases: ['lint-yaml'])]
function lint_yaml(): int
{
    title('lint:yaml');
    $paths = settings()->existingYaml((string) getcwd());

    if ([] === $paths) {
        io()->note('No YAML file or directory to lint.');

        return 0;
    }

    return success(exit_code(\sprintf('bin/console lint:yaml %s --parse-tags', implode(' ', array_map(escapeshellarg(...), $paths)))));
}

#[AsTask(name: 'composer', namespace: 'lint', description: 'Check the dependencies: analysis, advisories, unused packages, normalized composer.json', aliases: ['lint-composer'])]
function lint_composer(): int
{
    title('lint:composer');

    return success(aggregate(
        exit_code('vendor/bin/composer-dependency-analyser'),
        exit_code(\sprintf('%s audit', PHIL_DEV)),
        exit_code(\sprintf('%s unused', PHIL_DEV)),
        exit_code(\sprintf('%s normalize', PHIL_DEV)),
    ));
}

#[AsTask(name: 'analyse', namespace: 'lint', description: 'Run PHPStan at the maximum level, then check its witnesses', aliases: ['analyse', 'stan'])]
function analyse(): int
{
    title('lint:analyse');

    if (!file_exists(settings()->container)) {
        io()->note('PHPStan reads the container of the dev environment. Generating it...');
        run('bin/console cache:warmup', context: context()->withEnvironment(['APP_ENV' => 'dev', 'APP_DEBUG' => '1']));
    }

    return success(aggregate(
        exit_code(\sprintf('%s analyse --no-progress --memory-limit=1G', PHPSTAN)),
        exit_code(\sprintf('%s witnesses', PHIL_DEV)),
    ));
}

#[AsTask(name: 'rector', namespace: 'lint', description: 'Check that Rector has nothing left to change', aliases: ['lint-rector'])]
function lint_rector(): int
{
    title('lint:rector');

    return success(exit_code(\sprintf('%s process --dry-run', RECTOR)));
}

#[AsTask(name: 'architecture', namespace: 'lint', description: 'Check the shape of the classes with Arkitect and the layers with Deptrac', aliases: ['lint-architecture'])]
function lint_architecture(): int
{
    title('lint:architecture');

    return success(aggregate(
        exit_code(\sprintf('%s check --config=arkitect.php', ARKITECT)),
        exit_code(\sprintf('%s deptrac', PHIL_DEV)),
    ));
}

#[AsTask(name: 'all', namespace: 'lint', description: 'Run every lint and the static analysis', aliases: ['lint'])]
function lint_all(): int
{
    title('lint:all');

    return success(aggregate(
        lint_composer(),
        lint_php(),
        lint_twig(),
        lint_js(),
        lint_yaml(),
        lint_container(),
        analyse(),
        lint_rector(),
        lint_architecture(),
        ...also('lint_also', settings()->lintAlso),
    ));
}

// Fixers —————————————————————————————————————————————————————————————————————

#[AsTask(name: 'php', namespace: 'fix', description: 'Fix the code style of the PHP files with PHP-CS-Fixer', aliases: ['fix-php'])]
function fix_php(): int
{
    title('fix:php');

    return success(exit_code(\sprintf('%s fix', PHP_CS_FIXER)));
}

#[AsTask(name: 'rector', namespace: 'fix', description: 'Apply the changes of Rector', aliases: ['fix-rector'])]
function fix_rector(): int
{
    title('fix:rector');

    return success(exit_code(\sprintf('%s process', RECTOR)));
}

#[AsTask(name: 'twig', namespace: 'fix', description: 'Fix the code style of the Twig templates', aliases: ['fix-twig'])]
function fix_twig(): int
{
    title('fix:twig');

    return success(exit_code(\sprintf('%s twig --fix', PHIL_DEV)));
}

#[AsTask(name: 'js', namespace: 'fix', description: 'Fix the Stimulus controllers and the styles with Biome', aliases: ['fix-js'])]
function fix_js(): int
{
    title('fix:js');

    return success(exit_code(\sprintf('%s js --fix', PHIL_DEV)));
}

#[AsTask(name: 'composer', namespace: 'fix', description: 'Normalize composer.json', aliases: ['fix-composer'])]
function fix_composer(): int
{
    title('fix:composer');

    return success(exit_code(\sprintf('%s normalize --fix', PHIL_DEV)));
}

#[AsTask(name: 'all', namespace: 'fix', description: 'Run every fixer', aliases: ['fix'])]
function fix_all(): int
{
    title('fix:all');

    return success(aggregate(fix_rector(), fix_php(), fix_twig(), fix_js(), fix_composer(), ...also('fix_also', settings()->fixAlso)));
}

// Continuous integration —————————————————————————————————————————————————————

#[AsTask(name: 'all', namespace: 'ci', description: 'Run the continuous integration locally: coverage, then every lint', aliases: ['ci'])]
function ci(): int
{
    title('ci:all');
    io()->section('Coverage');
    $coverage = coverage();
    io()->section('Lints');
    $lints = lint_all();

    return success(aggregate($coverage, $lints, ...also('ci_also', settings()->ciAlso)));
}

// Git hooks and versions —————————————————————————————————————————————————————

#[AsTask(name: 'hooks', namespace: 'helpers', description: 'Install the git hooks: the fast checks on the staged files before each commit', aliases: ['hooks'])]
function hooks(): int
{
    title('helpers:hooks');

    return success(exit_code(\sprintf('%s install --force --configuration=captainhook.json', CAPTAINHOOK)));
}

#[AsTask(name: 'versions', namespace: 'helpers', description: 'Output the versions of the stack', aliases: ['versions'])]
function versions(): int
{
    title('helpers:versions');

    return success(aggregate(
        exit_code('castor --version'),
        exit_code('php -v'),
        exit_code('composer --version'),
        exit_code('bin/console --version'),
        exit_code(\sprintf('%s --version', PHPUNIT)),
        exit_code(\sprintf('%s --version', PHPSTAN)),
        exit_code(\sprintf('%s --version', PHP_CS_FIXER)),
    ));
}
