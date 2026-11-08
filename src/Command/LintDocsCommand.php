<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\DocsLintConfig;
use Phil\DevTools\Process\Executables;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'lint-docs', description: 'Lints the prose of the documentation with cspell, markdownlint and Vale', help: <<<'TXT'
    Runs, on the README, CHANGELOG, CONTRIBUTING, SECURITY and doc/ of each package, with the configurations
    generated under var/phil-dev/docs/:

      cspell        the spelling, in English, or in French and English for a package whose docs.language is fr,
                    with the shared words and those of docs.words;
      markdownlint  the Markdown, with lines of 120 columns outside code and tables;
      Vale          the style of the English documentation.

    These are not PHP tools: each is used when it is on the PATH — cspell and markdownlint-cli2 also through
    npx when Node.js is installed —, and skipped with a note otherwise. The French dictionary of cspell is
    @cspell/dict-fr-fr, installed globally. They read every page, so they run as their own script; check-docs
    stays the gate.
    TXT)]
final class LintDocsCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this->addArgument('packages', InputArgument::IS_ARRAY, 'The packages, by directory or Composer name; all by default');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();
        $config = new DocsLintConfig($workspace);
        $executables = Executables::fromEnvironment();
        $cspell = $this->locate($executables, 'cspell', 'cspell@10');
        $markdownlint = $this->locate($executables, 'markdownlint-cli2', 'markdownlint-cli2@0.24');
        $vale = $executables->find('vale');
        /** @var list<string> $names */
        $names = $input->getArgument('packages');
        $status = self::SUCCESS;

        foreach ([['cspell', $cspell], ['markdownlint-cli2', $markdownlint], ['vale', $vale]] as [$tool, $found]) {
            if (null === $found) {
                $output->writeln(\sprintf('%s is not installed: skipped.', $tool));
            }
        }

        $markdownlintConfig = $this->generate('docs/.markdownlint-cli2.jsonc', $config->markdownlint());
        $valeConfig = $this->generate('docs/vale.ini', $config->vale());

        foreach ($workspace->targets($names) as $package) {
            $files = $config->files($package);

            if ([] === $files) {
                continue;
            }

            $output->writeln('== '.$package->name);
            $runs = [];

            if (null !== $cspell) {
                $cspellConfig = $this->generate('docs/cspell-'.str_replace('/', '-', $package->name).'.json', $config->cspell($package));
                $runs[] = [...$cspell, 'lint', '--no-progress', '--config', $cspellConfig, ...$files];
            }

            if (null !== $markdownlint) {
                $runs[] = [...$markdownlint, '--config', $markdownlintConfig, ...$files];
            }

            if (null !== $vale && 'en' === $config->language($package)) {
                $runs[] = [$vale, '--config='.$valeConfig, ...$files];
            }

            foreach ($runs as $run) {
                if (!$this->runner->run($run, $workspace->root->dir)->successful()) {
                    $status = self::FAILURE;
                }
            }
        }

        return $status;
    }

    /**
     * The program on the `PATH`, else the package through npx, else null.
     *
     * @return non-empty-list<string>|null
     */
    private function locate(Executables $executables, string $program, string $package): ?array
    {
        $found = $executables->find($program);

        if (null !== $found) {
            return [$found];
        }

        $npx = $executables->find('npx');

        return null === $npx ? null : [$npx, '--yes', $package];
    }
}
