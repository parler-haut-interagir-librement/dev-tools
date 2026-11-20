<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\Tooling;
use Phil\DevTools\Config\TwigStanConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'twigstan', description: 'Analyses the Twig templates with TwigStan', help: <<<'TXT'
    Generates var/phil-dev/twigstan.php and its PHPStan configuration, then runs TwigStan, installed in
    vendor-bin/twig: the templates are compiled to PHP and analysed with the types of the context the PHP code
    renders them with. A package whose templates need its own Twig extensions names, under twig.loader, a PHP
    file that returns its Twig\Environment; the shared loader only knows the template directories.

    TwigStan is advisory: it runs as its own script, not in the gates, and passes whatever it finds — the shared
    loader lacks the Twig extensions of the bundles, so the analysis stops short of a verdict on their templates.
    --strict returns TwigStan's own exit code. A workspace without templates passes.
    TXT)]
final class TwigStanCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this->addOption('strict', null, InputOption::VALUE_NONE, 'Fail when TwigStan fails, instead of only reporting it');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();

        if ([] === new Tooling($workspace)->templateDirectories()) {
            $output->writeln('No Twig template: nothing to analyse.');

            return self::SUCCESS;
        }

        $config = new TwigStanConfig($workspace);
        $neon = $this->generate('twigstan.neon', $config->neon());
        $php = $this->generate('twigstan.php', $config->php($neon));

        $exitCode = $this->runner->run(
            [$this->tool('twig', 'twigstan'), 'analyze', '--configuration='.$php],
            $workspace->root->dir,
            $config->environment(),
        )->exitCode;

        if (self::SUCCESS === $exitCode || true === $input->getOption('strict')) {
            return $exitCode;
        }

        $output->writeln(\sprintf('TwigStan ended with code %d: advisory, it does not fail the check (--strict to fail).', $exitCode));

        return self::SUCCESS;
    }
}
