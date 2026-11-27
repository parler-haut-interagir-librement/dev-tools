<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Docs\Finding;
use Phil\DevTools\Docs\TranslationChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check-translations', description: 'Compares the English documentation of each package with its French mirror', help: <<<'TXT'
    Compares README.md and doc/**/*.md, doc/fr/ left out, with README.fr.md and doc/fr/**, and every other
    document at the root (CHANGELOG.md, CONTRIBUTING.md, SECURITY.md…; AGENTS.md and LICENSE.md left out) with its
    French twin beside it, CHANGELOG.fr.md:

      translation-missing       an English document without its mirror
      translation-twin-missing  a document at the root without its twin; summed up on one line per package
                                (-v lists them), it fails only where docs.twins is "blocking", or with
                                --require-twins
      translation-orphan        a mirror or a twin without its English document
      translation-headings      not as many headings of a level as the English
      translation-code          a fenced code block that differs from the English
      translation-late          the English has changed since the translation, by the SHA-256 the mirror carries on
                                its first line; reported, it does not fail

    A package fails the gate unless its docs.translations, else the root's, is "advisory". An advisory package is
    summed up on one line; -v lists its findings. After translating, phil-dev stamp-translations records the English
    the mirror now follows.

    In a monorepo, the members are checked, and the documents at the root of the repository, README.md included,
    with their twins; PHIL_DEV_PACKAGES, or the packages named, narrow them to those members.
    TXT)]
final class CheckTranslationsCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('packages', InputArgument::IS_ARRAY, 'The packages to check, by directory or Composer name; all by default')
            ->addOption('require-twins', null, InputOption::VALUE_NONE, 'Fail on a document at the root without its French twin, whatever docs.twins says');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();
        $checker = new TranslationChecker();
        /** @var list<string> $names */
        $names = $input->getArgument('packages');
        $requireTwins = true === $input->getOption('require-twins');
        $counts = ['blocking' => 0, 'late' => 0, 'missing' => 0, 'advisory' => 0];
        $checks = [];

        foreach ($workspace->targets($names) as $package) {
            $checks[] = [$package, '' === $package->relative ? $package->name : $package->relative, [...$checker->check($package), ...$checker->checkTwins($package)]];
        }

        // The documents at the root of a monorepo belong to no member: they are checked when no member is singled out.
        if ($workspace->isMonorepo() && [] === $names && \in_array($workspace->root, $workspace->analysed(), true)) {
            $checks[] = [$workspace->root, $workspace->root->name, $checker->checkTwins($workspace->root, readme: true)];
        }

        foreach ($checks as [$package, $label, $findings]) {
            if (!$checker->blocks($package, $workspace->root)) {
                $counts['advisory'] += \count($findings);
                $this->summarize($output, $label, $findings);

                continue;
            }

            $twinsBlock = $requireTwins || $checker->twinsBlock($package, $workspace->root);
            $missing = [];

            foreach ($findings as $finding) {
                if (TranslationChecker::TWIN_MISSING === $finding->code && !$twinsBlock && !$output->isVerbose()) {
                    $missing[] = $finding->file;
                    ++$counts['missing'];

                    continue;
                }

                $output->writeln(\sprintf('%s: %s', $label, $finding));
                ++$counts[match (true) {
                    TranslationChecker::LATE === $finding->code => 'late',
                    TranslationChecker::TWIN_MISSING === $finding->code && !$twinsBlock => 'missing',
                    default => 'blocking',
                }];
            }

            if ([] !== $missing) {
                $output->writeln(\sprintf('%s: no French twin of %s', $label, implode(', ', $missing)));
            }
        }

        if (0 === array_sum($counts)) {
            $output->writeln('The translations pass every check.');

            return self::SUCCESS;
        }

        $output->writeln(\sprintf('%d blocking, %d late, %d missing, %d advisory.', $counts['blocking'], $counts['late'], $counts['missing'], $counts['advisory']));

        return 0 === $counts['blocking'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param list<Finding> $findings
     */
    private function summarize(OutputInterface $output, string $label, array $findings): void
    {
        if ([] === $findings) {
            return;
        }

        if ($output->isVerbose()) {
            foreach ($findings as $finding) {
                $output->writeln(\sprintf('%s: advisory %s', $label, $finding));
            }

            return;
        }

        $counts = array_count_values(array_map(static fn (Finding $finding): string => $finding->code, $findings));
        ksort($counts);
        $parts = array_map(static fn (string $code, int $count): string => $count.' '.$code, array_keys($counts), $counts);
        $output->writeln(\sprintf('%s: advisory, %s', $label, implode(', ', $parts)));
    }
}
