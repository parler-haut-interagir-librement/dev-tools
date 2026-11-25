<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Conventions\ConfigurationConventions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check-config', description: 'Warns about a bundle configuration that strays from the conventions', help: <<<'TXT'
    Reads the configuration tree of each bundle, from its source without loading it, and warns when:

      - its root key is not <vendor>_<name> (phil/rate-limit-bundle: phil_rate_limit);
      - an option has no default and is not declared required;
      - an option is not named in the English documentation, doc/*.md or README.md;
      - the latest Flex recipe, recipe/<version>/config/packages/<root key>.yaml, sets an option to its default.

    The warnings fail nothing; --strict makes them fail the command. check-packages prints them too. In a
    monorepo, the members are checked; a package that is no bundle, or has no configuration, is skipped.
    TXT)]
final class CheckConfigCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('packages', InputArgument::IS_ARRAY, 'The packages to check, by directory or Composer name; all by default')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Fail when a package strays from the conventions');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conventions = new ConfigurationConventions();
        /** @var list<string> $names */
        $names = $input->getArgument('packages');
        $count = 0;

        foreach ($this->workspace()->targets($names) as $package) {
            $warnings = $conventions->check($package);

            if ([] === $warnings) {
                continue;
            }

            $output->writeln('== '.$package->name);

            foreach ($warnings as $warning) {
                $output->writeln('   warning: '.$warning);
                ++$count;
            }
        }

        if (0 === $count) {
            $output->writeln('Every bundle configuration follows the conventions.');

            return self::SUCCESS;
        }

        $output->writeln(\sprintf('%d warnings on the configuration conventions.', $count));

        return true === $input->getOption('strict') ? self::FAILURE : self::SUCCESS;
    }
}
