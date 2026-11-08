<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\PsalmConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'taint', description: 'Runs the taint analysis of Psalm on the production code', help: <<<'TXT'
    Generates var/phil-dev/psalm.xml from the composer.json and phil-dev.php of each package — the source
    directories of the packages analysed, each manifest's psalm.excludes and psalm.suppress — then runs Psalm,
    installed in vendor-bin/psalm, in taint analysis mode: it follows what comes from a request, a file or the
    environment to an HTML page, an SQL query, a shell, a file path or unserialize().

    Only the taint issues are reported: PHPStan stays the analyser of the gates, and what else Psalm finds is
    left to it. The analysis is slower and runs as its own script, before a release and once a week.
    TXT)]
final class TaintCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this->addArgument('arguments', InputArgument::IS_ARRAY, 'More arguments for Psalm, after --');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();
        $config = $this->generate('psalm.xml', new PsalmConfig($workspace)->xml());

        /** @var list<string> $arguments */
        $arguments = $input->getArgument('arguments');

        $result = $this->runner->capture([
            $this->tool('psalm', 'psalm'),
            '--config='.$config,
            '--taint-analysis',
            '--no-progress',
            '--output-format=json',
            ...$arguments,
        ], $workspace->root->dir);
        $issues = json_decode($result->output, true);

        if (!\is_array($issues)) {
            $output->writeln('Psalm gave no report: run it with --output-format=console to see why.');

            return self::FAILURE;
        }

        $taints = 0;

        foreach ($issues as $issue) {
            if (!\is_array($issue) || !\is_string($issue['type'] ?? null) || !str_starts_with($issue['type'], 'Tainted')) {
                continue;
            }

            ++$taints;
            $output->writeln(\sprintf('%s:%s %s: %s', $this->text($issue['file_path'] ?? null), $this->text($issue['line_from'] ?? null), $issue['type'], $this->text($issue['message'] ?? null)));
        }

        $output->writeln(0 === $taints ? 'No tainted data reaches a sink.' : \sprintf('%d tainted paths.', $taints));

        return 0 === $taints ? self::SUCCESS : self::FAILURE;
    }

    private function text(mixed $value): string
    {
        return \is_string($value) || \is_int($value) ? (string) $value : '?';
    }
}
