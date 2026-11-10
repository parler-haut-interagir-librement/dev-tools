<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Docs\Finding;
use Phil\DevTools\Docs\TranslationChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check-translations', description: 'Compares the English documentation of each package with its French mirror', help: <<<'TXT'
    Compares README.md and doc/**/*.md, doc/fr/ left out, with README.fr.md and doc/fr/**:

      translation-missing   an English document without its mirror
      translation-orphan    a mirror without its English document
      translation-headings  not as many headings of a level as the English
      translation-code      a fenced code block that differs from the English
      translation-late      the English has changed since the translation, by the SHA-256 the mirror carries on its
                            first line; reported, it does not fail

    A package fails the gate unless its docs.translations, else the root's, is "advisory". An advisory package is
    summed up on one line; -v lists its findings. After translating, phil-dev stamp-translations records the English
    the mirror now follows.

    In a monorepo, the members are checked, not the root; PHIL_DEV_PACKAGES narrows them.
    TXT)]
final class CheckTranslationsCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this->addArgument('packages', InputArgument::IS_ARRAY, 'The packages to check, by directory or Composer name; all by default');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();
        $checker = new TranslationChecker();
        /** @var list<string> $names */
        $names = $input->getArgument('packages');
        $blocking = 0;
        $late = 0;
        $advisory = 0;

        foreach ($workspace->targets($names) as $package) {
            $findings = $checker->check($package);
            $label = '' === $package->relative ? $package->name : $package->relative;

            if (!$checker->blocks($package, $workspace->root)) {
                $advisory += \count($findings);
                $this->summarize($output, $label, $findings);

                continue;
            }

            foreach ($findings as $finding) {
                $output->writeln(\sprintf('%s: %s', $label, $finding));
                TranslationChecker::LATE === $finding->code ? ++$late : ++$blocking;
            }
        }

        if (0 === $blocking + $late + $advisory) {
            $output->writeln('The translations pass every check.');

            return self::SUCCESS;
        }

        $output->writeln(\sprintf('%d blocking, %d late, %d advisory.', $blocking, $late, $advisory));

        return 0 === $blocking ? self::SUCCESS : self::FAILURE;
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
