<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Exception\InvalidManifest;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bench', description: 'Runs the benchmarks, stores them as the baseline, or compares them with it', help: <<<'TXT'
    Runs PHPBench, installed in vendor-bin/phpbench, with the phpbench.json of the root.

    --baseline stores the run under the tag phil_baseline, in var/phpbench: timings belong to the machine that
    measured them, so the baseline stays on it, made on the commit the comparison starts from.
    --compare runs again against that baseline, and fails when the mode of a subject's time is slower than the
    baseline's by more than the threshold: --threshold, else the root's bench.threshold, else 10 %.
    TXT)]
final class BenchCommand extends WorkspaceCommand
{
    public const string BASELINE = 'phil_baseline';

    public const int DEFAULT_THRESHOLD = 10;

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('baseline', null, InputOption::VALUE_NONE, 'Store this run as the baseline')
            ->addOption('compare', null, InputOption::VALUE_NONE, 'Compare this run with the baseline')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'For --compare: the slowdown allowed, in percent')
            ->addArgument('arguments', InputArgument::IS_ARRAY, 'More arguments for PHPBench, after --');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = $this->workspace()->root;
        $command = [$this->tool('phpbench', 'phpbench'), 'run', '--report=phil'];

        if (true === $input->getOption('baseline')) {
            $command[] = '--tag='.self::BASELINE;
            $command[] = '--store';
        } elseif (true === $input->getOption('compare')) {
            $threshold = $this->threshold($input->getOption('threshold') ?? $root->manifest->get('bench', 'threshold'));
            $command[] = '--ref='.self::BASELINE;
            $command[] = \sprintf('--assert=mode(variant.time.avg) <= mode(baseline.time.avg) +/- %d%%', $threshold);
        }

        /** @var list<string> $arguments */
        $arguments = $input->getArgument('arguments');

        return $this->runner->run([...$command, ...$arguments], $root->dir)->exitCode;
    }

    private function threshold(mixed $value): int
    {
        if (null === $value) {
            return self::DEFAULT_THRESHOLD;
        }

        $threshold = self::wholeNumber($value);

        if (null === $threshold || $threshold < 0) {
            throw new InvalidManifest('The bench threshold is a percentage, zero or more.');
        }

        return $threshold;
    }
}
