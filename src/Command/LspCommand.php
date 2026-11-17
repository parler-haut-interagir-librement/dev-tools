<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Process\Executables;
use Phil\DevTools\Workspace\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'lsp', description: 'Checks each Symfony application with the diagnostics of Symfony Language Tools', help: <<<'TXT'
    Runs symfony lsp:check, the diagnostics of Symfony Language Tools through the Symfony CLI, on each Symfony
    application of the workspace: the root and each member whose composer.json type is project and that has a
    bin/console, which is how the tool finds an application. A bundle or a library is no application — the tool reads
    only the files an application owns — and is skipped with a note, even with a bin/console. Every diagnostic code
    blocks: the codes the tool lists are all given to --fail-on, and no baseline is
    read. A workspace without an application passes. Under GitHub Actions the report uses the github format, which
    annotates the files.

    The tool boots the application to index what it registers at runtime: it runs the application's code. Arguments
    after -- go to it, such as --source-only.

    A gate, turned off by the root's gates.lsp: false. The Symfony CLI must be on the PATH.
    TXT)]
final class LspCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this->addArgument('arguments', InputArgument::IS_ARRAY, 'More arguments for symfony lsp:check, after --');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->gateIsOn('lsp', $output)) {
            return self::SUCCESS;
        }

        $workspace = $this->workspace();
        $applications = [];

        foreach ([$workspace->root, ...$workspace->members] as $package) {
            if (!is_file($package->path('bin/console'))) {
                continue;
            }

            if ('project' !== $package->type) {
                $output->writeln(\sprintf('%s: a %s, not an application: skipped.', self::name($package), $package->type));

                continue;
            }

            $applications[] = $package;
        }

        if ([] === $applications) {
            $output->writeln('No Symfony application (bin/console): nothing to check.');

            return self::SUCCESS;
        }

        $symfony = Executables::fromEnvironment()->find('symfony');

        if (null === $symfony) {
            $output->writeln('<error>The Symfony CLI (symfony) is not on the PATH: install it from https://symfony.com/download.</error>');

            return self::FAILURE;
        }

        $codes = $this->runner->capture([$symfony, 'lsp:check', '--list-codes'], $workspace->root->dir);
        $list = array_values(array_filter(array_map(trim(...), explode("\n", $codes->output)), static fn (string $code): bool => 1 === preg_match('/^[a-z_]+\.[a-z_]+$/', $code)));

        if (!$codes->successful() || [] === $list) {
            $output->writeln('<error>symfony lsp:check --list-codes listed no diagnostic code.</error>');

            return self::FAILURE;
        }

        /** @var list<string> $arguments */
        $arguments = $input->getArgument('arguments');
        $format = false !== getenv('GITHUB_ACTIONS') ? 'github' : 'human';
        $status = self::SUCCESS;

        foreach ($applications as $application) {
            $output->writeln(\sprintf('== %s', self::name($application)));

            $run = $this->runner->run([$symfony, 'lsp:check', '--format='.$format, '--fail-on='.implode(',', $list), ...$arguments], $application->dir);

            if (!$run->successful()) {
                $status = self::FAILURE;
            }
        }

        return $status;
    }

    private static function name(Package $package): string
    {
        return $package->isRoot() ? 'the root' : $package->relative;
    }
}
