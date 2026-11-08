<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\BiomeConfig;
use Phil\DevTools\Config\Tooling;
use Phil\DevTools\DevTools;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'js', description: 'Checks, or fixes, the Stimulus controllers and other scripts with Biome', help: <<<'TXT'
    Generates var/phil-dev/biome.json and runs Biome on the scripts of every package analysed: the
    directories of each manifest's js.paths, else assets when it exists. A workspace without scripts passes.

    Biome is a single binary, without Node.js: the first run downloads it to var/phil-dev/bin through
    kocal/biome-js-bundle, installed in vendor-bin/biome, at the version of the root's js.biome or the one
    phil-dev pins. A gate, turned off by the root's gates.js: false.
    TXT)]
final class JsCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this->addOption('fix', null, InputOption::VALUE_NONE, 'Apply the safe fixes and the format instead of checking');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->gateIsOn('js', $output)) {
            return self::SUCCESS;
        }

        $workspace = $this->workspace();
        $root = $workspace->root;
        $directories = new Tooling($workspace)->scriptDirectories();

        if ([] === $directories) {
            $output->writeln('No script: nothing to check.');

            return self::SUCCESS;
        }

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
            ...(true === $input->getOption('fix') ? ['--write'] : []),
            ...$directories,
        ], $root->dir)->exitCode;
    }
}
