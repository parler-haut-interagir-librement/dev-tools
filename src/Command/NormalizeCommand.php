<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'normalize', description: 'Checks, or fixes, that every composer.json is normalized', help: <<<'TXT'
    Runs ergebnis/composer-normalize, installed in vendor-bin/composer-normalize, on the composer.json of the
    root and of each member analysed: the order of the keys and of the packages, the indentation. Without
    --fix it only shows the difference and fails on it. The lock files are left alone: the packages commit none.
    A gate, turned off by the root's gates.normalize: false.
    TXT)]
final class NormalizeCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this->addOption('fix', null, InputOption::VALUE_NONE, 'Normalize the files instead of checking them');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->gateIsOn('normalize', $output)) {
            return self::SUCCESS;
        }

        $root = $this->workspace()->root;
        $status = self::SUCCESS;

        foreach ($this->workspace()->analysed() as $package) {
            $command = [
                ...$this->composer(),
                '--working-dir='.$root->path('vendor-bin/composer-normalize'),
                'normalize',
                '--no-check-lock',
                '--no-update-lock',
                '--indent-size=4',
                '--indent-style=space',
                ...(true === $input->getOption('fix') ? [] : ['--dry-run', '--diff']),
                $package->path('composer.json'),
            ];

            if (!$this->runner->run($command, $root->dir)->successful()) {
                $status = self::FAILURE;
            }
        }

        return $status;
    }
}
