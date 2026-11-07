<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\Tooling;
use Phil\DevTools\DevTools;
use Phil\DevTools\Witness\WitnessChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'witnesses', description: 'Checks that PHPStan still reports each forbidden call in its witness', help: <<<'TXT'
    Runs PHPStan on the tests/Witnesses/PhpStan directory of each package with the forbidden calls only, and
    passes when each witness is stopped by the rule its docblock announces ("Expected identifier: `…`"). A
    workspace without witnesses passes.
    TXT)]
final class WitnessesCommand extends WorkspaceCommand
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = $this->workspace()->root->dir;
        $tooling = new Tooling($this->workspace());
        $witnesses = [];

        foreach ($tooling->witnessDirectories() as $directory) {
            $files = glob($directory.'/*.php');
            array_push($witnesses, ...(false === $files ? [] : $files));
        }

        if ([] === $witnesses) {
            $output->writeln('No witnesses to check.');

            return self::SUCCESS;
        }

        $result = $this->runner->capture([
            $this->tool('phpstan', 'phpstan'),
            'analyse',
            '--configuration='.DevTools::directory().'/config/phpstan-witnesses.neon',
            '--error-format=json',
            '--no-progress',
            '--no-ansi',
        ], $root, ['PHIL_DEV_ROOT' => $root]);

        $failures = new WitnessChecker()->check($witnesses, $result->output);

        foreach ($failures as $failure) {
            $output->writeln($failure);
        }

        if ([] !== $failures) {
            return self::FAILURE;
        }

        $output->writeln(\sprintf('Each witness of PHPStan is stopped by its rule (%d).', \count($witnesses)));

        return self::SUCCESS;
    }
}
