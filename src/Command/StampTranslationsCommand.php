<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Docs\Mirrors;
use Phil\DevTools\Docs\SourceStamp;
use Phil\DevTools\Files;
use Phil\DevTools\Workspace\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'stamp-translations', description: 'Records, in each French mirror, the English document it now follows', help: <<<'TXT'
    Writes the SHA-256 of the English document on the first line of its French mirror, once the translation has
    caught up with it. A stamp says that a person, or an agent, has read this English: stamp only what was translated.

    The packages are named, so that a run never marks a whole monorepo as translated; --file narrows them further to
    documents given by their English or French path, relative to the package.

        phil-dev stamp-translations phil/slug
        phil-dev stamp-translations phil/slug --file=doc/installation.md
    TXT)]
final class StampTranslationsCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('packages', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The packages, by directory or Composer name')
            ->addOption('file', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A document to stamp, by its English or French path; all of the package by default');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $names */
        $names = $input->getArgument('packages');
        /** @var list<string> $files */
        $files = $input->getOption('file');
        $count = 0;

        foreach ($this->workspace()->targets($names) as $package) {
            foreach (Mirrors::mirrors($package) as $mirror) {
                if ($this->stamp($package, $mirror, $files)) {
                    $output->writeln(\sprintf('%s: %s', '' === $package->relative ? $package->name : $package->relative, $mirror));
                    ++$count;
                }
            }
        }

        $output->writeln(\sprintf('%d stamped.', $count));

        return self::SUCCESS;
    }

    /**
     * @param list<string> $files the documents asked for; every one when empty
     */
    private function stamp(Package $package, string $mirror, array $files): bool
    {
        $source = Mirrors::sourceOf($mirror);

        if (!is_file($package->path($source)) || ([] !== $files && !\in_array($mirror, $files, true) && !\in_array($source, $files, true))) {
            return false;
        }

        $current = Files::read($package->path($mirror));
        $stamped = SourceStamp::apply($current, Files::read($package->path($source)));

        if ($stamped === $current) {
            return false;
        }

        file_put_contents($package->path($mirror), $stamped);

        return true;
    }
}
