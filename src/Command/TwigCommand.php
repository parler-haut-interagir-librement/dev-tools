<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\Tooling;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'twig', description: 'Checks, or fixes, the code style of the Twig templates', help: <<<'TXT'
    Runs Twig-CS-Fixer, installed in vendor-bin/twig, on the templates of every package analysed: the
    directories of each manifest's twig.paths, else templates, src/Resources/views and Resources/views when
    they exist. A workspace without templates passes. A gate, turned off by the root's gates.twig: false.
    TXT)]
final class TwigCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this->addOption('fix', null, InputOption::VALUE_NONE, 'Fix the templates instead of checking them');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->gateIsOn('twig', $output)) {
            return self::SUCCESS;
        }

        $workspace = $this->workspace();
        $directories = new Tooling($workspace)->templateDirectories();

        if ([] === $directories) {
            $output->writeln('No Twig template: nothing to check.');

            return self::SUCCESS;
        }

        return $this->runner->run([
            $this->tool('twig', 'twig-cs-fixer'),
            'lint',
            '--no-cache',
            ...(true === $input->getOption('fix') ? ['--fix'] : []),
            ...$directories,
        ], $workspace->root->dir)->exitCode;
    }
}
