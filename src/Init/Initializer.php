<?php

declare(strict_types=1);

namespace Phil\DevTools\Init;

use Phil\DevTools\Config\CaptainHookConfig;
use Phil\DevTools\DevTools;
use Phil\DevTools\Files;
use Phil\DevTools\Matrix\Matrix;
use Phil\DevTools\Workspace\Manifest;

/**
 * Writes the thin configuration files of a package or a monorepo root, which call the shared ones, and adds the
 * Composer scripts, the bin plugin and the development dependencies to its `composer.json`.
 *
 * It is idempotent: a file already identical is left alone, and a file that differs is never overwritten unless the
 * change is forced — and `phil-dev.php`, which holds the package's own paths and exceptions, never. The same goes for
 * every value of `composer.json`.
 */
final readonly class Initializer
{
    /** @var list<string> */
    public const array TOOLS = [
        'arkitect', 'bc', 'biome', 'captainhook', 'churn', 'composer-normalize', 'composer-unused', 'deptrac', 'fuzzer',
        'infection', 'php-cs-fixer', 'phpbench', 'phpstan', 'psalm', 'rector', 'sbom', 'twig',
    ];

    /**
     * The development dependencies every project needs besides the tools of `vendor-bin/`.
     *
     * @var array<string, string>
     */
    public const array DEV_REQUIRES = [
        'bamarni/composer-bin-plugin' => '^1.9',
        'brianium/paratest' => '^7.27',
        'ergebnis/phpunit-slow-test-detector' => '^2.27',
        'phpunit/phpunit' => '^13.4',
        'shipmonk/composer-dependency-analyser' => '^1.8',
    ];

    /**
     * The gates `composer gates` runs, cheapest first: each is reliable and fast enough for every batch. The slow
     * checks — taint, fuzz, sbom, bc, bench:compare, twigstan, docs:lint, hotspots — are scripts of their own.
     *
     * @var list<string>
     */
    public const array GATES = [
        '@deps', '@deps:audit', '@deps:unused', '@deps:normalize', '@cs', '@twig', '@js', '@phpstan', '@rector',
        '@deptrac', '@arkitect', '@test', '@infection',
    ];

    public function __construct(private string $root)
    {
    }

    /**
     * @return list<InitResult>
     */
    public function run(bool $monorepo, bool $force): array
    {
        $results = [];

        foreach ($this->files($monorepo) as $target => $content) {
            $results[] = $this->write($target, $content, $force);
        }

        $results[] = $this->composer($monorepo, $force);

        return $results;
    }

    /**
     * The thin files, by path relative to the root.
     *
     * @return array<string, string>
     */
    public function files(bool $monorepo): array
    {
        $config = DevTools::relative($this->root, DevTools::directory().'/config');
        $files = [
            'phil-dev.php' => $this->template($monorepo ? 'phil-dev.monorepo.php.tpl' : 'phil-dev.package.php.tpl'),
            '.php-cs-fixer.dist.php' => $this->template('php-cs-fixer.php.tpl', ['config' => $config]),
            'rector.php' => $this->template('rector.php.tpl', ['config' => $config]),
            'arkitect.php' => $this->template('arkitect.php.tpl', ['config' => $config]),
            'phpstan.neon' => $this->template('phpstan.neon.tpl', ['config' => $config]),
            'phpbench.json' => $this->template('phpbench.json.tpl', ['config' => $config, 'bench' => $monorepo ? 'packages' : 'tests']),
            'phpunit.dist.xml' => $this->phpunit($monorepo),
            'captainhook.json' => new CaptainHookConfig(DevTools::binary($this->root))->json(),
        ];

        if (!$monorepo) {
            $files['composer-dependency-analyser.php'] = $this->template('composer-dependency-analyser.php.tpl');
        }

        foreach (self::TOOLS as $tool) {
            $files['vendor-bin/'.$tool.'/composer.json'] = $this->template('vendor-bin/'.$tool.'/composer.json.tpl', ['php' => Matrix::load()->floor('php')->version]);
        }

        return $files;
    }

    /**
     * The Composer scripts, by name.
     *
     * @return array<string, string|list<string>>
     */
    public function scripts(bool $monorepo): array
    {
        $binary = DevTools::binary($this->root);
        $bin = '@php '.$binary;
        $scripts = [
            'cs' => 'vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer check --diff',
            'cs:fix' => 'vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer fix',
            'phpstan' => [
                'vendor-bin/phpstan/vendor/bin/phpstan analyse --no-progress',
                $bin.' witnesses',
            ],
            'rector' => 'vendor-bin/rector/vendor/bin/rector process --dry-run',
            'rector:fix' => 'vendor-bin/rector/vendor/bin/rector process',
            'deptrac' => $bin.' deptrac',
            'arkitect' => 'vendor-bin/arkitect/vendor/bin/phparkitect check --config=arkitect.php',
            'twig' => $bin.' twig',
            'twig:fix' => $bin.' twig --fix',
            'twigstan' => $bin.' twigstan',
            'js' => $bin.' js',
            'js:fix' => $bin.' js --fix',
            'test' => $bin.' test',
            'test:unit' => $bin.' test --suite=Unit',
            'test:integration' => $bin.' test --suite=Integration',
            'test:services' => $bin.' services test',
            'test:next' => $bin.' php next',
            'infection' => $bin.' infection',
            'infection:changed' => $bin.' infection --changed',
            'infection:next' => $bin.' php next -- php '.$binary.' infection',
            'bench' => $bin.' bench',
            'bench:baseline' => $bin.' bench --baseline',
            'bench:compare' => $bin.' bench --compare',
            'bench:worker' => $bin.' bench:worker',
            'deps' => 'vendor/bin/composer-dependency-analyser',
            'deps:audit' => $bin.' audit',
            'deps:unused' => $bin.' unused',
            'deps:normalize' => $bin.' normalize',
            'deps:normalize:fix' => $bin.' normalize --fix',
            'taint' => $bin.' taint',
            'fuzz' => $bin.' fuzz',
            'sbom' => $bin.' sbom',
            'bc' => $bin.' bc',
            'hotspots' => $bin.' hotspots',
            'docs' => $bin.' check-docs',
            'docs:lint' => $bin.' lint-docs',
            'docs:translations' => $bin.' check-translations',
            'hooks' => 'vendor-bin/captainhook/vendor/bin/captainhook install --force --configuration=captainhook.json',
            'packages' => $bin.' check-packages',
            'packages:next' => $bin.' php next -- php '.$binary.' check-packages',
            'split' => $bin.' split',
            'gates' => self::GATES,
        ];

        if (!$monorepo) {
            unset($scripts['packages'], $scripts['packages:next'], $scripts['split']);
        }

        return $scripts;
    }

    /**
     * @return array<string, string>
     */
    public function descriptions(bool $monorepo): array
    {
        $descriptions = [
            'phpstan' => 'PHPStan at the maximum level, then the witnesses that prove each forbidden call is still reported.',
            'deptrac' => 'The layers, generated from the composer.json and phil-dev.php of each package.',
            'test:services' => 'The Integration suite against the MySQL, PostgreSQL and Gotenberg of phil-dev services.',
            'infection' => 'Mutation testing of the whole code at 100 % of covered mutants.',
            'infection:changed' => 'Mutation testing of the files changed since the base ref (PHIL_DEV_BASE_REF, else origin/main), once per batch.',
            'test:next' => 'The tests on the next PHP of the matrix, in its Docker image.',
            'infection:next' => 'Mutation testing of the whole code on the next PHP of the matrix, weekly and before a release.',
            'docs' => 'The documentation checks of each package.',
            'packages' => 'Each package installed on its own, with its dependency analysis and its tests.',
            'packages:next' => 'The same, on the next PHP of the matrix.',
            'gates' => 'Every quality gate, in order: the slow checks are scripts of their own.',
            'test' => 'The tests with ParaTest, one process per core, or PHPUnit when ParaTest is missing.',
            'twig' => 'The code style of the Twig templates, with Twig-CS-Fixer.',
            'twigstan' => 'The Twig templates analysed by TwigStan; a script of its own.',
            'js' => 'The Stimulus controllers and other scripts, checked by Biome.',
            'bench:baseline' => 'The benchmarks, stored as the baseline of this machine.',
            'bench:compare' => 'The benchmarks against the baseline, failing above the threshold of bench.threshold.',
            'bench:worker' => 'A page served by FrankenPHP in worker mode, measured.',
            'deps:audit' => 'The installed packages against the security advisories.',
            'deps:unused' => 'The required packages the code never uses, with composer-unused.',
            'deps:normalize' => 'Every composer.json checked against composer-normalize.',
            'deps:normalize:fix' => 'Every composer.json normalized.',
            'taint' => 'The taint analysis of Psalm on the production code; a script of its own.',
            'fuzz' => 'The fuzz targets of the packages, each for its time budget; a script of its own.',
            'sbom' => 'The CycloneDX bill of materials of each package, under var/phil-dev/sbom/.',
            'bc' => 'The public API against the latest tag, for backward-compatibility breaks.',
            'hotspots' => 'The files that change most and are the most complex, with churn-php.',
            'docs:lint' => 'The prose of the documentation, with cspell, markdownlint and Vale; a script of its own.',
            'docs:translations' => 'The French mirror of the documentation of each package, blocking or advisory by docs.translations.',
            'hooks' => 'Installs the git hooks of captainhook.json: the fast gates on the staged files before each commit.',
            'split' => 'The finished packages split to their public repositories; dry run unless --push is given.',
        ];

        if (!$monorepo) {
            unset($descriptions['packages'], $descriptions['packages:next'], $descriptions['split']);
        }

        return $descriptions;
    }

    private function composer(bool $monorepo, bool $force): InitResult
    {
        $composer = ComposerJson::read($this->root.'/composer.json');

        foreach (self::DEV_REQUIRES as $package => $constraint) {
            $composer->setIn('require-dev', $package, $constraint);
        }

        $composer->set('config.allow-plugins.bamarni/composer-bin-plugin', true, $force);
        $composer->set('config.process-timeout', 0, $force);
        $composer->set('extra.bamarni-bin', ['bin-links' => false, 'forward-command' => true, 'target-directory' => 'vendor-bin'], $force);

        foreach ($this->scripts($monorepo) as $name => $script) {
            $composer->setIn('scripts', $name, $script, $force);
        }

        foreach ($this->descriptions($monorepo) as $name => $description) {
            $composer->setIn('scripts-descriptions', $name, $description, $force);
        }

        $written = $composer->save();

        return new InitResult(
            'composer.json',
            $written ? InitStatus::Updated : ([] === $composer->kept() ? InitStatus::Unchanged : InitStatus::Kept),
            $composer->kept(),
        );
    }

    private function write(string $target, string $content, bool $force): InitResult
    {
        $path = $this->root.'/'.$target;

        if (is_file($path)) {
            if (file_get_contents($path) === $content) {
                return new InitResult($target, InitStatus::Unchanged);
            }

            // The manifest holds the package's own paths and exceptions: it is never replaced.
            if (!$force || Manifest::FILE === $target) {
                return new InitResult($target, InitStatus::Kept);
            }
        }

        $existed = is_file($path);

        if (!is_dir(\dirname($path))) {
            mkdir(\dirname($path), recursive: true);
        }

        file_put_contents($path, $content);

        return new InitResult($target, $existed ? InitStatus::Updated : InitStatus::Created);
    }

    private function phpunit(bool $monorepo): string
    {
        $unit = [];
        $integration = [];
        $source = [];

        foreach (is_dir($this->root.'/tests/Unit') ? ['tests/Unit'] : (is_dir($this->root.'/tests') ? ['tests'] : []) as $directory) {
            $unit[] = $directory;
        }

        foreach (['tests/Integration', 'tests/Functional'] as $directory) {
            if (is_dir($this->root.'/'.$directory)) {
                $integration[] = $directory;
            }
        }

        if (is_dir($this->root.'/src')) {
            $source[] = 'src';
        }

        if ($monorepo) {
            $unit[] = 'packages/*/tests/Unit';
            $integration[] = 'packages/*/tests/Integration';
            $integration[] = 'packages/*/tests/Functional';
            $source[] = 'packages/*/src';
        }

        return $this->template('phpunit.xml.tpl', [
            'unit' => $this->directories($unit),
            'integration' => $this->directories($integration),
            'source' => $this->directories($source),
            'extensions' => $this->extensions(),
        ]);
    }

    /**
     * The PHPUnit extensions: the slow-test detector, a development dependency of every project, and the recorder of
     * `#[UseRecord]`, which needs the extension of `symfony/phpunit-bridge` when it is installed.
     */
    private function extensions(): string
    {
        $extensions = ['        <bootstrap class="Ergebnis\\PHPUnit\\SlowTestDetector\\Extension" />'];

        if (is_dir($this->root.'/vendor/symfony/phpunit-bridge')) {
            $extensions[] = '        <bootstrap class="Symfony\\Bridge\\PhpUnit\\SymfonyExtension" />';
        }

        return "\n    <extensions>\n".implode("\n", $extensions)."\n    </extensions>\n";
    }

    /**
     * @param list<string> $directories
     */
    private function directories(array $directories): string
    {
        $xml = [];

        foreach ($directories as $directory) {
            $xml[] = '            <directory>'.$directory.'</directory>';
        }

        return implode("\n", $xml);
    }

    /**
     * @param array<string, string> $values
     */
    private function template(string $name, array $values = []): string
    {
        $content = Files::read(DevTools::resource('init/'.$name));

        foreach ($values as $key => $value) {
            $content = str_replace('{{'.$key.'}}', $value, $content);
        }

        // A placeholder left empty leaves a blank line in the XML.
        return (string) preg_replace("/\n\n(?=\\s*<\\/)/", "\n", $content);
    }
}
