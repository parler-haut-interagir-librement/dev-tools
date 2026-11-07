<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\DevTools;
use Phil\DevTools\Matrix\Matrix;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'php', description: 'Runs a command on a PHP version of the matrix, in its Docker image', help: <<<'TXT'
    Runs the command — PHPUnit by default — with the project mounted at /app, in the image of the PHP version the matrix
    (config/matrix.json) names: a version such as 8.6, or "floor" or "next". The image is built from resources/php the
    first time, or again with --build.

        phil-dev php next
        phil-dev php next -- php vendor/bin/phil-dev check-packages
        phil-dev php next -- php vendor/bin/phil-dev infection
    TXT)]
final class PhpCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('version', InputArgument::REQUIRED, 'A PHP version of the matrix, "floor" or "next"')
            ->addArgument('arguments', InputArgument::IS_ARRAY, 'The command, after --; php vendor/bin/phpunit by default')
            ->addOption('build', null, InputOption::VALUE_NONE, 'Build the image again first');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('version');
        $version = Matrix::load()->find('php', \is_string($name) ? $name : '');
        $tag = 'phil-dev/php:'.$version->version;
        $root = $this->workspace()->root->dir;

        if (true === $input->getOption('build') || !$this->runner->capture(['docker', 'image', 'inspect', $tag], $root)->successful()) {
            $build = $this->runner->run(['docker', 'build', '--build-arg', 'PHP_IMAGE='.$version->image, '--tag', $tag, DevTools::resource('php')], $root);

            if (!$build->successful()) {
                return $build->exitCode;
            }
        }

        /** @var list<string> $arguments */
        $arguments = $input->getArgument('arguments');

        return $this->runner->run(
            ['docker', 'run', '--rm', '--volume', $root.':/app', '--workdir', '/app', $tag, ...([] === $arguments ? ['php', 'vendor/bin/phpunit'] : $arguments)],
            $root,
        )->exitCode;
    }
}
