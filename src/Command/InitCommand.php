<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Init\Initializer;
use Phil\DevTools\Init\InitResult;
use Phil\DevTools\Init\InitStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'init', description: 'Writes the thin configuration files and the Composer scripts of a package or a monorepo root', help: <<<'TXT'
    Writes, at the root, the thin files that call the shared configurations of phil/dev-tools —
    phil-dev.php, .php-cs-fixer.dist.php, rector.php, arkitect.php, phpstan.neon, phpunit.dist.xml,
    phpbench.json, composer-dependency-analyser.php and vendor-bin/*/composer.json — and adds the Composer
    scripts, the bin plugin and the development dependencies to composer.json.

    A directory with a packages/ directory is a monorepo root, unless --package says otherwise.
    A file that exists with other content is kept: --force replaces it. Then run composer update.
    TXT)]
final class InitCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Replace the files and values that differ')
            ->addOption('monorepo', null, InputOption::VALUE_NONE, 'Write the files of a monorepo root, whatever the directory holds')
            ->addOption('package', null, InputOption::VALUE_NONE, 'Write the files of a package on its own, whatever the directory holds');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = $this->root();
        $monorepo = true === $input->getOption('monorepo') || (true !== $input->getOption('package') && is_dir($root.'/packages'));
        $results = new Initializer($root)->run($monorepo, true === $input->getOption('force'));

        foreach ($results as $result) {
            $output->writeln(\sprintf('%-10s %s', $result->status->value, $result->file));

            foreach ($result->kept as $value) {
                $output->writeln(\sprintf('%-10s   %s', '', $value.' differs, kept'));
            }
        }

        $kept = array_any($results, static fn (InitResult $result): bool => InitStatus::Kept === $result->status || [] !== $result->kept);

        $output->writeln('');
        $output->writeln($kept
            ? 'Some files or values differ and were kept; run again with --force to replace them.'
            : 'Done. Run composer update to install the tools.');

        return self::SUCCESS;
    }
}
