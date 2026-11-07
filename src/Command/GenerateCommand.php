<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\DeptracConfig;
use Phil\DevTools\Config\InfectionConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'generate', description: 'Writes the generated Deptrac and Infection configurations under var/phil-dev/')]
final class GenerateCommand extends WorkspaceCommand
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();

        $output->writeln($this->generate('deptrac.yaml', new DeptracConfig($workspace)->yaml()));
        $output->writeln($this->generate('infection.json', new InfectionConfig($workspace)->json()));

        return self::SUCCESS;
    }
}
