<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\DeptracConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'deptrac', description: "Checks the layers, generated from each package, then the root's own Deptrac configurations", help: <<<'TXT'
    Generates var/phil-dev/deptrac.yaml from the composer.json and phil-dev.php of every package the workspace
    knows — the members of a monorepo and the packages installed under vendor/ that ship a phil-dev.php — and
    runs Deptrac on it, then on each file of the root's deptrac.configs.
    TXT)]
final class DeptracCommand extends WorkspaceCommand
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();
        $generator = new DeptracConfig($workspace);
        $configurations = [$this->generate('deptrac.yaml', $generator->yaml()), ...$generator->extraConfigurations()];
        $status = self::SUCCESS;

        foreach ($configurations as $index => $configuration) {
            $result = $this->runner->run([
                $this->tool('deptrac', 'deptrac'),
                'analyse',
                '--config-file='.$configuration,
                '--cache-file='.$workspace->root->path('var/phil-dev/deptrac-'.$index.'.cache'),
                '--fail-on-uncovered',
                '--report-uncovered',
                '--no-progress',
            ], $workspace->root->dir);

            if (!$result->successful()) {
                $status = self::FAILURE;
            }
        }

        return $status;
    }
}
