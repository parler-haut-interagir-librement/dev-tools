<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\ChurnConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'hotspots', description: 'Lists the files that change most and are the most complex', help: <<<'TXT'
    Generates var/phil-dev/churn.yml and runs churn-php, installed in vendor-bin/churn, on the production code
    of every package analysed: the files that change often and are complex come first, the places a
    refactoring pays most. It reports and never fails, so it runs as its own script.
    TXT)]
final class HotspotsCommand extends WorkspaceCommand
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();
        $config = $this->generate('churn.yml', new ChurnConfig($workspace)->yaml());

        return $this->runner->run([$this->tool('churn', 'churn'), 'run', '--configuration='.$config], $workspace->root->dir)->exitCode;
    }
}
