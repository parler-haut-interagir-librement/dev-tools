<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Exception\InvalidManifest;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bc', description: 'Checks the public API against the latest tag for backward-compatibility breaks', help: <<<'TXT'
    Runs roave/backward-compatibility-check, installed in vendor-bin/bc, from the highest version tagged and
    reachable from HEAD to the working tree: a removed class or method, a narrowed parameter, a widened return type. The tags
    matched are those of the root's bc.tags glob, every tag by default. A repository without a tag passes: a
    package has no API to keep until its first release.

    The checker reads the repository at its root, so it runs on a package in its own repository — a split
    repository, or a package that is one —, not on the members of a monorepo. It checks out and installs two
    revisions, so it runs as its own script, before each release.
    TXT)]
final class BcCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this->addArgument('arguments', InputArgument::IS_ARRAY, 'More arguments for the checker, after --');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();
        $root = $workspace->root;

        if ($workspace->isMonorepo()) {
            $output->writeln('A monorepo: the members are checked in their split repositories, where their tags are.');

            return self::SUCCESS;
        }

        if (!is_dir($root->path('.git'))) {
            $output->writeln('Not the root of a git repository: nothing to compare.');

            return self::SUCCESS;
        }

        $pattern = $root->manifest->get('bc', 'tags') ?? '*';

        if (!\is_string($pattern)) {
            throw new InvalidManifest('bc.tags must be a glob of tags, such as v*.');
        }

        // The tags reachable from HEAD, the highest version first.
        $tags = $this->runner->capture(['git', 'tag', '--list', $pattern, '--merged', 'HEAD', '--sort=-v:refname'], $root->dir)->output;
        $tag = trim(explode("\n", $tags)[0]);

        if ('' === $tag) {
            $output->writeln('No tag yet: no released API to compare with.');

            return self::SUCCESS;
        }

        $output->writeln('Comparing with '.$tag.'.');

        /** @var list<string> $arguments */
        $arguments = $input->getArgument('arguments');

        return $this->runner->run([$this->tool('bc', 'roave-backward-compatibility-check'), '--from='.$tag, ...$arguments], $root->dir)->exitCode;
    }
}
