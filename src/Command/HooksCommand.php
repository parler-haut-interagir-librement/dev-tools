<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use JsonException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'hooks', description: 'Installs the git hooks of captainhook.json in the repository every worktree shares, or checks that a checkout has the tools they call', help: <<<'TXT'
    The hooks of a clone live in the git directory its worktrees share, and each runs from the checkout that commits:
    CaptainHook, then the tools of the actions, from that checkout's own vendor-bin/. Before installing, the command
    checks that the checkout has them — CaptainHook at the path of config.run.path, and the tool under vendor-bin/
    of each action that runs `staged <tool>` — and names the `composer bin <tool> install` that installs each one
    missing. It then runs CaptainHook's install with --git-directory set to the shared git directory, so that the
    hooks land there from the main checkout as from a worktree.

    With --check, it only checks: run it in a new worktree before its first commit.

        phil-dev hooks
        phil-dev hooks --check
    TXT)]
final class HooksCommand extends WorkspaceCommand
{
    public const string CONFIGURATION = 'captainhook.json';

    /** Where CaptainHook lives when `config.run.path` says nothing. */
    public const string CAPTAINHOOK = 'vendor-bin/captainhook/vendor/bin/captainhook';

    #[Override]
    protected function configure(): void
    {
        $this->addOption('check', null, InputOption::VALUE_NONE, 'Only check that this checkout has the tools the hooks call');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = $this->workspace()->root;
        $file = $root->path(self::CONFIGURATION);
        $configuration = is_file($file) ? $this->read($file) : null;

        if (null === $configuration) {
            $output->writeln(\sprintf('<error>%s is missing or is not a JSON object: phil-dev init writes it.</error>', self::CONFIGURATION));

            return self::FAILURE;
        }

        $missing = [];

        foreach (self::tools($configuration) as $binary => $directory) {
            if (!is_file(str_starts_with($binary, '/') ? $binary : $root->path($binary))) {
                $missing[] = \sprintf('%s is missing: composer bin %s install', $binary, $directory);
            }
        }

        if ([] !== $missing) {
            $output->writeln('<error>The hooks cannot run in this checkout.</error>');
            $output->writeln($missing);

            return self::FAILURE;
        }

        if (true === $input->getOption('check')) {
            $output->writeln('The hooks can run in this checkout.');

            return self::SUCCESS;
        }

        $common = $this->runner->capture(['git', 'rev-parse', '--path-format=absolute', '--git-common-dir'], $root->dir);
        $directory = trim($common->output);

        if (!$common->successful() || '' === $directory) {
            $output->writeln('<error>This directory is not a git checkout.</error>');

            return self::FAILURE;
        }

        $captainHook = self::captainHook($configuration);

        return $this->runner->run([
            str_starts_with($captainHook, '/') ? $captainHook : $root->path($captainHook),
            'install',
            '--force',
            '--configuration='.self::CONFIGURATION,
            '--git-directory='.$directory,
        ], $root->dir)->exitCode;
    }

    /**
     * The binaries the hooks call, relative to the root, each with its directory under `vendor-bin/`: CaptainHook, and
     * the tool of each action that runs `staged <tool>`.
     *
     * @param array<array-key, mixed> $configuration
     *
     * @return array<string, string>
     */
    public static function tools(array $configuration): array
    {
        $captainHook = self::captainHook($configuration);
        $tools = [$captainHook => 1 === preg_match('#^vendor-bin/([^/]+)/#', $captainHook, $match) ? $match[1] : 'captainhook'];

        foreach ($configuration as $hook) {
            $actions = \is_array($hook) && \is_array($hook['actions'] ?? null) ? $hook['actions'] : [];

            foreach ($actions as $action) {
                $command = \is_array($action) ? $action['action'] ?? null : null;

                if (!\is_string($command) || 1 !== preg_match('/\bstaged\s+([a-z-]+)/', $command, $staged) || !isset(StagedCommand::TOOLS[$staged[1]])) {
                    continue;
                }

                [$directory, $binary] = StagedCommand::TOOLS[$staged[1]];
                $tools['vendor-bin/'.$directory.'/vendor/bin/'.$binary] = $directory;
            }
        }

        return $tools;
    }

    /**
     * The path of CaptainHook the hooks call: `config.run.path`, or its place under `vendor-bin/`.
     *
     * @param array<array-key, mixed> $configuration
     */
    private static function captainHook(array $configuration): string
    {
        $config = $configuration['config'] ?? null;
        $run = \is_array($config) ? $config['run'] ?? null : null;
        $path = \is_array($run) ? $run['path'] ?? null : null;

        return \is_string($path) && '' !== $path ? $path : self::CAPTAINHOOK;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function read(string $file): ?array
    {
        try {
            $data = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return \is_array($data) ? $data : null;
    }
}
