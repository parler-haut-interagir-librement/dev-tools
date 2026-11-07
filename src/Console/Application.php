<?php

declare(strict_types=1);

namespace Phil\DevTools\Console;

use Phil\DevTools\Command\CheckDocsCommand;
use Phil\DevTools\Command\CheckPackagesCommand;
use Phil\DevTools\Command\DeptracCommand;
use Phil\DevTools\Command\GenerateCommand;
use Phil\DevTools\Command\InfectionCommand;
use Phil\DevTools\Command\InitCommand;
use Phil\DevTools\Command\MatrixCommand;
use Phil\DevTools\Command\NewCommand;
use Phil\DevTools\Command\PhpCommand;
use Phil\DevTools\Command\ServicesCommand;
use Phil\DevTools\Command\WitnessesCommand;
use Phil\DevTools\DevTools;
use Phil\DevTools\Process\LocalProcessRunner;
use Phil\DevTools\Process\ProcessRunner;
use Symfony\Component\Console\Application as Console;

/**
 * `phil-dev`, with every command.
 */
final class Application extends Console
{
    public function __construct(?string $root = null, ProcessRunner $runner = new LocalProcessRunner())
    {
        parent::__construct(DevTools::NAME, DevTools::VERSION);

        $this->addCommands([
            new InitCommand($root, $runner),
            new NewCommand($root, $runner),
            new CheckDocsCommand($root, $runner),
            new CheckPackagesCommand($root, $runner),
            new InfectionCommand($root, $runner),
            new DeptracCommand($root, $runner),
            new WitnessesCommand($root, $runner),
            new GenerateCommand($root, $runner),
            new ServicesCommand($root, $runner),
            new PhpCommand($root, $runner),
            new MatrixCommand($root, $runner),
        ]);
    }
}
