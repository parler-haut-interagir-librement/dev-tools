<?php

declare(strict_types=1);

namespace Phil\DevTools\Console;

use Phil\DevTools\Command\AuditCommand;
use Phil\DevTools\Command\BcCommand;
use Phil\DevTools\Command\BenchCommand;
use Phil\DevTools\Command\CheckDocsCommand;
use Phil\DevTools\Command\CheckPackagesCommand;
use Phil\DevTools\Command\CheckTranslationsCommand;
use Phil\DevTools\Command\DeptracCommand;
use Phil\DevTools\Command\FuzzCommand;
use Phil\DevTools\Command\GenerateCommand;
use Phil\DevTools\Command\HotspotsCommand;
use Phil\DevTools\Command\InfectionCommand;
use Phil\DevTools\Command\InitCommand;
use Phil\DevTools\Command\JsCommand;
use Phil\DevTools\Command\LintAppsCommand;
use Phil\DevTools\Command\LintDocsCommand;
use Phil\DevTools\Command\MatrixCommand;
use Phil\DevTools\Command\NewCommand;
use Phil\DevTools\Command\NormalizeCommand;
use Phil\DevTools\Command\PhpCommand;
use Phil\DevTools\Command\SbomCommand;
use Phil\DevTools\Command\ServicesCommand;
use Phil\DevTools\Command\SplitCommand;
use Phil\DevTools\Command\StampTranslationsCommand;
use Phil\DevTools\Command\TaintCommand;
use Phil\DevTools\Command\TestCommand;
use Phil\DevTools\Command\TwigCommand;
use Phil\DevTools\Command\TwigStanCommand;
use Phil\DevTools\Command\UnusedCommand;
use Phil\DevTools\Command\WitnessesCommand;
use Phil\DevTools\Command\WorkerBenchCommand;
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
            new CheckTranslationsCommand($root, $runner),
            new StampTranslationsCommand($root, $runner),
            new CheckPackagesCommand($root, $runner),
            new InfectionCommand($root, $runner),
            new DeptracCommand($root, $runner),
            new WitnessesCommand($root, $runner),
            new GenerateCommand($root, $runner),
            new ServicesCommand($root, $runner),
            new PhpCommand($root, $runner),
            new MatrixCommand($root, $runner),
            new TestCommand($root, $runner),
            new AuditCommand($root, $runner),
            new NormalizeCommand($root, $runner),
            new UnusedCommand($root, $runner),
            new TwigCommand($root, $runner),
            new TwigStanCommand($root, $runner),
            new JsCommand($root, $runner),
            new TaintCommand($root, $runner),
            new FuzzCommand($root, $runner),
            new SbomCommand($root, $runner),
            new BcCommand($root, $runner),
            new BenchCommand($root, $runner),
            new WorkerBenchCommand($root, $runner),
            new SplitCommand($root, $runner),
            new LintDocsCommand($root, $runner),
            new LintAppsCommand($root, $runner),
            new HotspotsCommand($root, $runner),
        ]);
    }
}
