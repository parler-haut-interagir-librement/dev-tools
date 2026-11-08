<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Exception\WorkspaceNotFound;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'fuzz', description: 'Fuzzes the parsers the packages declare, each for a time budget', help: <<<'TXT'
    Runs nikic/php-fuzzer, installed in vendor-bin/fuzzer, on each fuzz target the packages analysed declare
    under fuzz.targets — a name and a PHP file that gives $config its target —, for the time budget of
    --budget, else of the manifest's fuzz.budget, else 60 seconds each. The corpus of a target lives in
    fuzz.corpus/<name> (tests/Fuzz/corpus/<name> by default): its seeds, and the inputs the fuzzer finds that
    reach new code. A crash fails the run and leaves its input in var/phil-dev/fuzz/<name>/.

    The target file loads the classes with require getenv('PHIL_DEV_AUTOLOAD'). Fuzzing is slow by nature:
    it runs as its own script, once a week and after a change to a parser.
    TXT)]
final class FuzzCommand extends WorkspaceCommand
{
    public const string AUTOLOAD_VARIABLE = 'PHIL_DEV_AUTOLOAD';

    public const int DEFAULT_BUDGET = 60;

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('targets', InputArgument::IS_ARRAY, 'The targets to fuzz, by name; all by default')
            ->addOption('budget', null, InputOption::VALUE_REQUIRED, 'The seconds each target is fuzzed for')
            ->addOption('max-runs', null, InputOption::VALUE_REQUIRED, 'Stop each target after this many runs');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = $this->workspace()->root;
        /** @var list<string> $names */
        $names = $input->getArgument('targets');
        $targets = $this->targets($input->getOption('budget'), $names);

        if ([] === $targets) {
            $output->writeln('No fuzz target declared: nothing to fuzz.');
        }

        $maxRuns = $input->getOption('max-runs');
        $failures = [];

        foreach ($targets as $name => $target) {
            $work = $root->path('var/phil-dev/fuzz/'.$name);

            foreach ([$work, $target['corpus']] as $directory) {
                if (!is_dir($directory)) {
                    mkdir($directory, recursive: true);
                }
            }

            $output->writeln(\sprintf('== %s, for %d seconds', $name, $target['budget']));
            $result = $this->runner->run(
                [$this->tool('fuzzer', 'php-fuzzer'), 'fuzz', $target['file'], $target['corpus'], ...(\is_string($maxRuns) ? ['--max-runs', $maxRuns] : [])],
                $work,
                [self::AUTOLOAD_VARIABLE => $root->path('vendor/autoload.php')],
                $target['budget'],
            );

            if (!$result->successful()) {
                $failures[] = \sprintf('%s crashed: its input is in %s', $name, $work);
            }
        }

        foreach ($failures as $failure) {
            $output->writeln($failure);
        }

        return [] === $failures ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The targets of every package analysed, or those named, each with its file, its corpus and its budget.
     *
     * @param list<string> $names
     *
     * @return array<string, array{file: string, corpus: string, budget: positive-int}>
     */
    private function targets(mixed $budgetOption, array $names): array
    {
        $targets = [];

        foreach ($this->workspace()->analysed() as $package) {
            $budget = $this->budget($budgetOption ?? $package->manifest->get('fuzz', 'budget'));
            $corpus = $package->manifest->get('fuzz', 'corpus') ?? 'tests/Fuzz/corpus';

            if (!\is_string($corpus)) {
                throw new InvalidManifest(\sprintf('fuzz.corpus of %s must be a directory.', $package->name));
            }

            foreach ($package->manifest->map('fuzz', 'targets') as $name => $file) {
                if (!\is_string($name) || !\is_string($file)) {
                    throw new InvalidManifest(\sprintf('fuzz.targets of %s maps a name to the PHP file of the target.', $package->name));
                }

                $targets[$name] = ['file' => $package->path($file), 'corpus' => $package->path($corpus.'/'.$name), 'budget' => $budget];
            }
        }

        foreach ($names as $name) {
            if (!isset($targets[$name])) {
                throw new WorkspaceNotFound(\sprintf('No fuzz target "%s"; the targets are %s.', $name, [] === $targets ? 'none' : implode(', ', array_keys($targets))));
            }
        }

        return [] === $names ? $targets : array_intersect_key($targets, array_flip($names));
    }

    /**
     * @return positive-int
     */
    private function budget(mixed $value): int
    {
        if (null === $value) {
            return self::DEFAULT_BUDGET;
        }

        $budget = self::wholeNumber($value);

        if (null === $budget || $budget < 1) {
            throw new InvalidManifest('The fuzz budget is a number of seconds, one or more.');
        }

        return $budget;
    }
}
