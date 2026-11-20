<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\BiomeConfig;
use Phil\DevTools\Config\Tooling;
use Phil\DevTools\DevTools;
use Phil\DevTools\Process\Executables;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\Workspace;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'js', description: 'Checks, or fixes, the Stimulus controllers and other scripts with Biome, then runs their tests with Node.js', help: <<<'TXT'
    Generates var/phil-dev/biome.json and runs Biome on the scripts of every package analysed: the
    directories of each manifest's js.paths, else assets when it exists. Then, unless --fix, it runs the tests of
    each package analysed that has a tests/js directory with the test runner of Node.js, from the package's
    directory: node --test 'tests/js/**/*.test.mjs', after --import ./tests/js/register.mjs when the package has
    that file, the module hooks that resolve its imports to the stubs of its tests. A workspace without scripts or
    script tests passes.

    Biome is a single binary, without Node.js: the first run downloads it to var/phil-dev/bin through
    kocal/biome-js-bundle, installed in vendor-bin/biome, at the version of the root's js.biome or the one
    phil-dev pins. The tests need node on the PATH: without it, a workspace that has script tests fails. A gate,
    turned off by the root's gates.js: false.
    TXT)]
final class JsCommand extends WorkspaceCommand
{
    /**
     * The directory of the script tests of a package, and the module hooks it may hold.
     */
    public const string TESTS = 'tests/js';

    public const string HOOKS = 'tests/js/register.mjs';

    #[Override]
    protected function configure(): void
    {
        $this->addOption('fix', null, InputOption::VALUE_NONE, 'Apply the safe fixes and the format instead of checking, without running the tests');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->gateIsOn('js', $output)) {
            return self::SUCCESS;
        }

        $workspace = $this->workspace();
        $directories = new Tooling($workspace)->scriptDirectories();
        $fix = true === $input->getOption('fix');
        $tested = $fix ? [] : array_values(array_filter($workspace->analysed(), static fn (Package $package): bool => is_dir($package->path(self::TESTS))));

        if ([] === $directories && [] === $tested) {
            $output->writeln('No script: nothing to check.');

            return self::SUCCESS;
        }

        $exitCode = [] === $directories ? self::SUCCESS : $this->biome($workspace, $directories, $fix);

        if (self::SUCCESS !== $exitCode || [] === $tested) {
            return $exitCode;
        }

        $node = Executables::fromEnvironment()->find('node');

        if (null === $node) {
            $output->writeln('Node.js is not on the PATH: the script tests of '.implode(', ', array_map(static fn (Package $package): string => $package->name, $tested)).' cannot run.');

            return self::FAILURE;
        }

        $failed = [];

        foreach ($tested as $package) {
            $hooks = is_file($package->path(self::HOOKS)) ? ['--import', './'.self::HOOKS] : [];

            if (!$this->runner->run([$node, ...$hooks, '--test', self::TESTS.'/**/*.test.mjs'], $package->dir)->successful()) {
                $failed[] = $package->name;
            }
        }

        if ([] === $failed) {
            return self::SUCCESS;
        }

        $output->writeln('The script tests of '.implode(', ', $failed).' failed.');

        return self::FAILURE;
    }

    /**
     * Biome on the script directories, downloaded the first time: its exit code, or that of the failed download.
     *
     * @param non-empty-list<string> $directories
     */
    private function biome(Workspace $workspace, array $directories, bool $fix): int
    {
        $root = $workspace->root;
        $biomeConfig = new BiomeConfig($workspace);
        $config = $this->generate('biome.json', $biomeConfig->json());
        $binaries = $root->path('var/phil-dev/bin');
        $biome = $binaries.'/biome';

        if (!is_file($biome)) {
            if (!is_dir($binaries)) {
                mkdir($binaries, recursive: true);
            }

            $download = $this->runner->run([\PHP_BINARY, DevTools::resource('biome/download.php'), $root->path('vendor-bin/biome/vendor/autoload.php'), $binaries, $biomeConfig->version()], $root->dir);

            if (!$download->successful()) {
                return $download->exitCode;
            }
        }

        return $this->runner->run([
            $biome,
            'check',
            '--config-path='.$config,
            ...($fix ? ['--write'] : []),
            ...$directories,
        ], $root->dir)->exitCode;
    }
}
