<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\DocsLintConfig;
use Phil\DevTools\Docs\FrenchDictionary;
use Phil\DevTools\Process\Executables;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'lint-docs', description: 'Lints the prose of the documentation with cspell, markdownlint and Vale', help: <<<'TXT'
    Runs, on the README, CHANGELOG, CONTRIBUTING, SECURITY, doc/ and French mirrors of each package, with the
    configurations generated under var/phil-dev/docs/:

      cspell        the spelling, in English, or in French and English for the mirrors and for a package whose
                    docs.language is fr, with the shared words and those of docs.words;
      markdownlint  the Markdown, with lines of 120 columns outside code and tables;
      Vale          the style of the English documentation.

    These are not PHP tools: each is used when it is on the PATH — cspell and markdownlint-cli2 also through
    npx when Node.js is installed —, and skipped with a note otherwise. The French dictionary of cspell is
    @cspell/dict-fr-fr, at the version the package.json of phil/dev-tools pins: read from the node_modules of
    the root, else installed by npm under var/phil-dev/node/ the first time a French page is read. They read every page, so they run as their own script; check-docs stays the gate.
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
        $dictionary = null;
        $dictionarySought = false;

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
            $translations = $config->translations($package);
            $slug = str_replace('/', '-', $package->name);

            if (null !== $cspell && !$dictionarySought && ([] !== $translations || 'fr' === $config->language($package))) {
                $dictionary = $this->frenchDictionary($executables, $output);
                $dictionarySought = true;
            }

            if (null !== $cspell) {
                $cspellConfig = $this->generate('docs/cspell-'.$slug.'.json', $config->cspell($package, dictionary: $dictionary));
                $runs[] = [...$cspell, 'lint', '--no-progress', '--config', $cspellConfig, ...$files];
            }

            if (null !== $cspell && [] !== $translations) {
                $frenchConfig = $this->generate('docs/cspell-'.$slug.'-fr.json', $config->cspell($package, true, $dictionary));
                $runs[] = [...$cspell, 'lint', '--no-progress', '--config', $frenchConfig, ...$translations];
            }

            if (null !== $markdownlint) {
                $runs[] = [...$markdownlint, '--config', $markdownlintConfig, ...$files, ...$translations];
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
     * The `cspell-ext.json` of the French dictionary: in the `node_modules` of the root — phil/dev-tools itself while
     * it is developed — or of `var/phil-dev/node/`, else installed there by npm. Null when it cannot be had, cspell
     * then resolving the package's name itself, as for a global install.
     */
    private function frenchDictionary(Executables $executables, OutputInterface $output): ?string
    {
        $root = $this->workspace()->root;
        $dictionary = new FrenchDictionary();
        $installed = $root->path(FrenchDictionary::DIRECTORY);
        $found = $dictionary->find([$root->dir, $installed]);
        $npm = $executables->find('npm');

        if (null === $found && null !== $npm) {
            $output->writeln(\sprintf('Installing %s under %s.', FrenchDictionary::PACKAGE, FrenchDictionary::DIRECTORY));
            $this->runner->run($dictionary->installCommand($npm, $installed), $root->dir);
            $found = $dictionary->find([$installed]);
        }

        if (null === $found) {
            $output->writeln(\sprintf('%s is not installed: cspell looks for it itself.', FrenchDictionary::PACKAGE));
        }

        return $found;
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
