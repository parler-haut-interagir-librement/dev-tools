<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Docs\DocsChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check-docs', description: 'Checks the documentation of each package', help: <<<'TXT'
    Checks, in each package: the file set (README, CHANGELOG, CODE_OF_CONDUCT, CONTRIBUTING, LICENSE,
    SECURITY, composer-dependency-analyser.php, doc/ with extension-points.md, src/, tests/), the relative
    links, the shape of the tables, the balance of bold, the README's index of doc/ and its sections, the
    index of doc/fr/ in README.fr.md, a
    CHANGELOG with one "Added" section until the packages are public (docs.public), the licence holder's wording for the
    vendor, and the terms no package names (docs.forbidden of the root's phil-dev.php, and --forbid).

    In a monorepo, the members are checked, not the root; PHIL_DEV_PACKAGES narrows them.
    TXT)]
final class CheckDocsCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('packages', InputArgument::IS_ARRAY, 'The packages to check, by directory or Composer name; all by default')
            ->addOption('forbid', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, "A regular expression of a term no package names, besides those of the root's docs.forbidden");
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();
        /** @var list<string> $names */
        $names = $input->getArgument('packages');
        /** @var list<string> $forbid */
        $forbid = $input->getOption('forbid');
        $checker = new DocsChecker([...$workspace->root->manifest->strings('docs', 'forbidden'), ...$forbid]);
        $count = 0;

        foreach ($workspace->targets($names) as $package) {
            foreach ($checker->check($package) as $finding) {
                $output->writeln(\sprintf('%s: %s', '' === $package->relative ? $package->name : $package->relative, $finding));
                ++$count;
            }
        }

        if (0 === $count) {
            $output->writeln('The documentation passes every check.');

            return self::SUCCESS;
        }

        $output->writeln(\sprintf('%d finding%s.', $count, 1 === $count ? '' : 's'));

        return self::FAILURE;
    }
}
