<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use FilesystemIterator;
use Override;
use Phil\DevTools\Files;
use Phil\DevTools\Matrix\Matrix;
use Phil\DevTools\Split\ArchiveRules;
use Phil\DevTools\Split\SecurityFiles;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\Workspace;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check-packages', description: 'Installs each package on its own, then checks its dependencies and runs its tests', help: <<<'TXT'
    The quality checks of a monorepo cover the code of its packages, not their composer.json: the root
    installs its own dependencies, which hide a require a package forgot. This command copies each package
    to a new directory, installs it on its own, then runs composer-dependency-analyser with the package's
    configuration and its tests without the root's configuration — what the first project that installs it
    will see.

    The packages of the monorepo are found on no public repository until they are published: the copy
    gets a path repository on each glob of the root's packages, copied rather than linked, as a vcs
    repository would once the package has moved, each at the branch its branch alias names (dev-main), whatever
    branch the checkout is on. The root's other path repositories — a neighbouring monorepo, a package beside the
    root — are given to the copy too, with their URL made absolute, copied as well. It needs the network for
    everything else. The tests of each copy run with TMPDIR
    inside the copy.

    The polyfills of the running PHP version and of the earlier ones, symfony/polyfill-php86 on PHP 8.6,
    polyfill nothing there, so composer-dependency-analyser would find them unused, though the package requires
    them for the versions before: a configuration written in the copy wraps the package's own and ignores that
    error for them.

    The archive Composer would make of each package must keep what it needs: the paths its autoload and
    autoload-dev name — the tests autoload-dev points to ship with the code — and its LICENSE and README.md. A
    path its .gitattributes marks export-ignore, or its archive.exclude names, fails the package.

    It also warns, without failing, about a package without a SECURITY.md, or whose README shows no security badge:
    phil-dev split --security-files adds both.
    TXT)]
final class CheckPackagesCommand extends WorkspaceCommand
{
    /** @var list<string> */
    private const array LEFT_OUT = ['vendor', 'var', 'composer.lock', '.phpunit.cache', '.php-cs-fixer.cache'];

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('packages', InputArgument::IS_ARRAY, 'The packages to check, by directory or Composer name; all by default')
            ->addOption('keep', null, InputOption::VALUE_NONE, 'Keep each temporary copy, to look into a failure')
            ->addOption('symfony', null, InputOption::VALUE_REQUIRED, 'Install each package with a Symfony version of the matrix, "floor" or "next"');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();
        /** @var list<string> $names */
        $names = $input->getArgument('packages');
        $analyser = $workspace->root->path('vendor/bin/composer-dependency-analyser');
        $failures = [];
        $symfony = $input->getOption('symfony');
        $matrix = Matrix::load();
        $target = \is_string($symfony) ? $matrix->find('symfony', $symfony)->version : null;
        $floor = $matrix->floor('symfony')->version;
        $repositories = $this->repositorySteps($workspace);

        foreach ($workspace->targets($names) as $package) {
            $output->writeln('== '.$package->name);

            foreach ($this->securityWarnings($package) as $warning) {
                $output->writeln('   warning: '.$warning);
            }

            $composer = json_decode(Files::read($package->path('composer.json')), true);
            $composer = \is_array($composer) ? $composer : [];

            foreach (ArchiveRules::of($package->dir, $composer)->missing($package->dir, $composer) as $missing) {
                $failures[] = $package->name.': '.$missing;
            }

            $work = tempnam(sys_get_temp_dir(), 'phil-dev-');

            if (false === $work) {
                throw new RuntimeException('Cannot make a temporary directory.');
            }

            unlink($work);
            $this->copy($package, $work);

            if (null !== $target) {
                $this->requireSymfony($work.'/composer.json', $floor, $target);
            }

            $steps = $repositories;
            $steps[] = ['composer', 'install', '--no-interaction', '--no-progress', '--quiet'];
            $steps[] = [\PHP_BINARY, $analyser, ...$this->analyserOptions($composer, $work)];
            // A package's own PHPUnit configuration when it has one, else every test under tests/.
            $steps[] = [] === array_filter(['phpunit.xml', 'phpunit.dist.xml', 'phpunit.xml.dist'], static fn (string $file): bool => is_file($work.'/'.$file))
                ? ['vendor/bin/phpunit', '--no-configuration', 'tests']
                : ['vendor/bin/phpunit'];
            // The test kernels of the copy keep their cache in it, never beside those of another checkout.
            $environment = ['TMPDIR' => $work.'/var/tmp'];
            mkdir($environment['TMPDIR'], recursive: true);

            foreach ($steps as $step) {
                if (!$this->runner->run($step, $work, $environment)->successful()) {
                    $failures[] = \sprintf('%s: %s failed', $package->name, implode(' ', \array_slice($step, 0, 2)));

                    break;
                }
            }

            if (true === $input->getOption('keep')) {
                $output->writeln('   kept in '.$work);
            } else {
                Files::removeDirectory($work);
            }
        }

        if ([] === $failures) {
            $output->writeln('Every package installs and passes on its own.');

            return self::SUCCESS;
        }

        foreach ($failures as $failure) {
            $output->writeln($failure);
        }

        return self::FAILURE;
    }

    /**
     * The `composer config` commands that give a copy the path repositories of a monorepo: one on each glob of its
     * members, each member at its `dev-main` branch, then one on each other path repository of the root.
     *
     * @return list<non-empty-list<string>>
     */
    private function repositorySteps(Workspace $workspace): array
    {
        if (!$workspace->isMonorepo()) {
            return [];
        }

        $steps = [];
        $versions = $this->siblingVersions($workspace);

        foreach ($workspace->packageGlobs() as $index => $glob) {
            $repository = json_encode(['type' => 'path', 'url' => $workspace->root->path($glob), 'options' => ['symlink' => false, 'versions' => $versions]], \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
            $steps[] = ['composer', 'config', 'repositories.siblings'.$index, $repository];
        }

        foreach ($workspace->neighbourRepositories() as $index => $url) {
            $repository = json_encode(['type' => 'path', 'url' => $url, 'options' => ['symlink' => false]], \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
            $steps[] = ['composer', 'config', 'repositories.neighbours'.$index, $repository];
        }

        return $steps;
    }

    /**
     * The version each member is installed at from the path repositories: its `dev-main` branch, which its branch
     * alias (`0.1.x-dev`) applies to. Composer would otherwise read the branch the checkout is on — `dev-b/topic` in a
     * worktree — which no alias covers, and no `^0.1@dev` of a sibling accepts.
     *
     * @return array<string, string>
     */
    private function siblingVersions(Workspace $workspace): array
    {
        $versions = [];

        foreach ($workspace->members as $member) {
            $composer = json_decode(Files::read($member->path('composer.json')), true);
            $extra = \is_array($composer) ? $composer['extra'] ?? null : null;
            $aliases = \is_array($extra) ? $extra['branch-alias'] ?? null : null;
            $branch = \is_array($aliases) ? array_key_first($aliases) : null;
            $versions[$member->name] = \is_string($branch) && str_starts_with($branch, 'dev-') ? $branch : 'dev-main';
        }

        return $versions;
    }

    /**
     * Pins the Symfony packages a package requires at the floor (`^8.2`) to the version given (`8.3.*`), in the copy,
     * and lets the release candidates of that version in.
     */
    private function requireSymfony(string $file, string $floor, string $target): void
    {
        $composer = json_decode(Files::read($file), true, flags: \JSON_THROW_ON_ERROR);

        if (!\is_array($composer)) {
            return;
        }

        foreach (['require', 'require-dev'] as $section) {
            $requires = $composer[$section] ?? null;

            if (!\is_array($requires)) {
                continue;
            }

            foreach ($requires as $name => $constraint) {
                if (\is_string($name) && \is_string($constraint) && str_starts_with($name, 'symfony/') && str_contains($constraint, $floor)) {
                    $requires[$name] = $target.'.*';
                }
            }

            $composer[$section] = $requires;
        }

        $composer['minimum-stability'] = 'dev';
        $composer['prefer-stable'] = true;
        file_put_contents($file, json_encode($composer, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));
    }

    /**
     * The `require` of a package that names a polyfill of PHP `$versionId` or earlier: on that version, its functions
     * and classes are PHP's own, and composer-dependency-analyser finds it unused.
     *
     * @return list<string>
     */
    private function idlePolyfills(mixed $requires, int $versionId): array
    {
        $idle = [];

        foreach (\is_array($requires) ? array_keys($requires) : [] as $name) {
            if (\is_string($name) && 1 === preg_match('{^symfony/polyfill-php([1-9])(\d)$}', $name, $match) && (int) $match[1] * 10000 + (int) $match[2] * 100 <= $versionId) {
                $idle[] = $name;
            }
        }

        sort($idle);

        return $idle;
    }

    /**
     * The options of composer-dependency-analyser for a copy: none, or a configuration that loads the package's own
     * (or the default one) and ignores the polyfills that polyfill nothing on the running PHP.
     *
     * @param array<mixed> $composer
     *
     * @return list<string>
     */
    private function analyserOptions(array $composer, string $work): array
    {
        $polyfills = $this->idlePolyfills($composer['require'] ?? null, \PHP_VERSION_ID);

        if ([] === $polyfills) {
            return [];
        }

        $directory = $work.'/var/phil-dev';

        if (!is_dir($directory) && !mkdir($directory, recursive: true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot make '.$directory.'.');
        }

        $file = $directory.'/composer-dependency-analyser.php';
        file_put_contents($file, \sprintf(<<<'PHP_WRAP'
            <?php

            declare(strict_types=1);

            use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
            use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

            // Written by phil-dev check-packages: these polyfills polyfill nothing on PHP %s, which runs the analysis.
            $own = __DIR__.'/../../composer-dependency-analyser.php';
            $configuration = is_file($own) ? require $own : new Configuration();

            return $configuration->ignoreErrorsOnPackages(%s, [ErrorType::UNUSED_DEPENDENCY]);

            PHP_WRAP, \PHP_MAJOR_VERSION.'.'.\PHP_MINOR_VERSION, "['".implode("', '", $polyfills)."']"));

        return ['--config='.$file];
    }

    /**
     * What the package misses of what a split repository shows of its security: its policy, and the badge of its README.
     *
     * @return list<string>
     */
    private function securityWarnings(Package $package): array
    {
        $warnings = [];

        if (!is_file($package->path('SECURITY.md'))) {
            $warnings[] = 'no SECURITY.md (phil-dev split --security-files adds it).';
        }

        $readme = $package->path('README.md');

        if (is_file($readme) && !SecurityFiles::hasBadge(Files::read($readme))) {
            $warnings[] = 'README.md shows no security badge (phil-dev split --security-files adds it).';
        }

        return $warnings;
    }

    /**
     * Copies the package, leaving out what an installation writes at its root.
     */
    private function copy(Package $package, string $target): void
    {
        mkdir($target);
        $filter = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($package->dir, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $item): bool => $item->getPath() !== $package->dir || !\in_array($item->getFilename(), self::LEFT_OUT, true),
        );
        $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $path = $target.substr($item->getPathname(), \strlen($package->dir));
            $item->isDir() ? mkdir($path) : copy($item->getPathname(), $path);
        }
    }
}
