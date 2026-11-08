<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'unused', description: 'Finds the required packages the code never uses', help: <<<'TXT'
    Runs composer-unused, installed in vendor-bin/composer-unused, on the root's composer.json: a package of
    require whose symbols no file of the autoloaded directories uses. It complements composer-dependency-
    analyser, which also finds the symbols used without being required. A package used only through
    configuration is named in the unused.ignore of a manifest. The members of a monorepo are checked on
    their own by check-packages. A gate, turned off by the root's gates.unused: false.
    TXT)]
final class UnusedCommand extends WorkspaceCommand
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->gateIsOn('unused', $output)) {
            return self::SUCCESS;
        }

        $root = $this->workspace()->root;
        $command = [$this->tool('composer-unused', 'composer-unused'), $root->path('composer.json'), '--no-progress'];

        foreach ($this->workspace()->analysed() as $package) {
            foreach ($package->manifest->strings('unused', 'ignore') as $ignored) {
                $command[] = '--excludePackage='.$ignored;
            }
        }

        return $this->runner->run($command, $root->dir)->exitCode;
    }
}
