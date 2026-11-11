<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use DateTimeImmutable;
use Override;
use Phil\DevTools\Skeleton\PackageName;
use Phil\DevTools\Skeleton\SkeletonWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'new', description: 'Writes the skeleton of a new package, a library or a bundle', help: <<<'TXT'
    Writes the file set every package has — README, CHANGELOG, CODE_OF_CONDUCT, CONTRIBUTING, LICENSE,
    SECURITY, composer.json, composer-dependency-analyser.php, phil-dev.php, doc/, src/ and tests/ — with
    the wording of its vendor, and the French mirror of its documentation, README.fr.md and doc/fr/. In a monorepo it goes to packages/<name> (packages/tilttek-<name> for
    Tilttek), where the shared configurations find it without any change at the root.

    --planned writes a package being written: 'status' => 'planned' in its phil-dev.php, a README and
    doc/reference.md that say so and describe what it will do (--description, its first sentence as the
    summary of composer.json; --description-fr for the French mirror), and a smoke test. --requires adds
    packages to its require, at ^0.1@dev unless a constraint follows a colon (symfony/uid:^8.2).
    TXT)]
final class NewCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('package', InputArgument::REQUIRED, 'The name, such as phil/example or tilttek/example')
            ->addOption('bundle', null, InputOption::VALUE_NONE, 'A Symfony bundle; its name ends with -bundle')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'What the package does: the description of composer.json and the README', 'To be described.')
            ->addOption('description-fr', null, InputOption::VALUE_REQUIRED, 'The French description, for README.fr.md; the English one by default')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'The title of the README; the package name by default')
            ->addOption('planned', null, InputOption::VALUE_NONE, 'A package being written: its status is planned, and its README describes what it will do')
            ->addOption('requires', null, InputOption::VALUE_REQUIRED, 'The packages it requires, comma-separated, such as phil/geo,tilttek/dice')
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'The directory to write it in, relative to the root')
            ->addOption('year', null, InputOption::VALUE_REQUIRED, 'The year of the copyright; this year by default');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $package = $input->getArgument('package');
        $description = $input->getOption('description');
        $name = PackageName::parse(\is_string($package) ? $package : '', true === $input->getOption('bundle'));
        $root = $this->root();
        $dir = $input->getOption('dir');
        $relative = \is_string($dir) && '' !== $dir ? $dir : (is_dir($root.'/packages') ? 'packages/'.$name->directory() : $name->directory());
        $year = $input->getOption('year');
        $libraryExists = is_dir($root.'/packages/'.PackageName::parse($name->library())->directory());

        $writer = new SkeletonWriter(\is_string($year) && '' !== $year ? $year : new DateTimeImmutable()->format('Y'));
        $descriptionFr = $input->getOption('description-fr');
        $title = $input->getOption('title');
        $requiresOption = $input->getOption('requires');
        $requires = [];

        foreach (\is_string($requiresOption) ? explode(',', $requiresOption) : [] as $require) {
            if ('' !== trim($require)) {
                $requires[] = trim($require);
            }
        }

        $files = $writer->write(
            $name,
            $root.'/'.$relative,
            \is_string($description) ? $description : '',
            $libraryExists,
            true === $input->getOption('planned'),
            $requires,
            \is_string($descriptionFr) ? $descriptionFr : null,
            \is_string($title) ? $title : null,
        );

        $output->writeln(\sprintf('%s written in %s:', $name->package(), $relative));

        foreach ($files as $file) {
            $output->writeln('  '.$file);
        }

        return self::SUCCESS;
    }
}
