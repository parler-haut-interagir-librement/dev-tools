<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\DevTools;
use Phil\DevTools\Files;
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

    The directories outside the project that its path repositories name — a neighbouring monorepo whose packages it
    installs, phil/dev-tools beside it — are mounted read-only where the same relative path reaches them from /app,
    so that Composer and the autoloader find them in the container as on the host.

    PHPStan's temporary directories — the tmpDir of the root's phpstan.neon, var/phpstan by default, and
    var/phpstan-witnesses — are replaced in the container by directories of its own under var/phil-dev/php/<version>/:
    PHPStan keys part of its cache by the PHP version it analyses for, not by where its phar lies, so a cache the
    host wrote would send the container to the host's paths.

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
            ['docker', 'run', '--rm', '--volume', $root.':/app', ...$this->caches($root, $version->version), ...$this->neighbours($root), '--workdir', '/app', $tag, ...([] === $arguments ? ['php', 'vendor/bin/phpunit'] : $arguments)],
            $root,
        )->exitCode;
    }

    /**
     * The `--volume` arguments that give the container PHPStan temporary directories of its own, created on the host
     * under var/phil-dev/php/<version>/: the reflection cache stores the absolute path of the stubs inside the phar
     * under a key the host and the container share, and the container then fails on a stub "is not a file".
     *
     * @return list<string>
     */
    private function caches(string $root, string $version): array
    {
        $volumes = [];

        foreach ([$this->phpstanTmpDir($root), 'var/phpstan-witnesses'] as $directory) {
            $host = $root.'/var/phil-dev/php/'.$version.'/'.str_replace('/', '-', $directory);

            if (!is_dir($host)) {
                mkdir($host, recursive: true);
            }

            $volumes[$directory] = ['--volume', $host.':/app/'.$directory];
        }

        return array_merge(...array_values($volumes));
    }

    /**
     * The tmpDir the root's phpstan.neon, or else its phpstan.dist.neon, sets when it is a path inside the project;
     * var/phpstan otherwise, the directory `phil-dev init` writes.
     */
    private function phpstanTmpDir(string $root): string
    {
        foreach (['phpstan.neon', 'phpstan.dist.neon'] as $file) {
            if (!is_file($root.'/'.$file)) {
                continue;
            }

            if (1 === preg_match('~^\s+tmpDir:\s*[\'"]?([\w./-]+?)[\'"]?\s*$~m', Files::read($root.'/'.$file), $match) && !str_starts_with($match[1], '/')) {
                $segments = array_values(array_filter(explode('/', $match[1]), static fn (string $segment): bool => '' !== $segment && '.' !== $segment));

                if ([] !== $segments && !\in_array('..', $segments, true)) {
                    return implode('/', $segments);
                }
            }

            break;
        }

        return 'var/phpstan';
    }

    /**
     * The `--volume` arguments of the directories outside the root that its path repositories name, each mounted
     * read-only at the path its relative URL reaches from /app — `../../Phil/phil-prerelease/packages/*` at
     * /Phil/phil-prerelease/packages, since `..` stops at the container's root — or at its own absolute path.
     *
     * @return list<string>
     */
    private function neighbours(string $root): array
    {
        $composer = json_decode(Files::read($root.'/composer.json'), true);
        $repositories = \is_array($composer) && \is_array($composer['repositories'] ?? null) ? $composer['repositories'] : [];
        $volumes = [];

        foreach ($repositories as $repository) {
            if (!\is_array($repository) || 'path' !== ($repository['type'] ?? null) || !\is_string($repository['url'] ?? null)) {
                continue;
            }

            $url = $repository['url'];
            $wildcard = strcspn($url, '*?{[');
            // The directory before the first wildcard: `packages` of `packages/*` and of `packages/a*`.
            $directory = $wildcard < \strlen($url) ? \dirname(substr($url, 0, $wildcard).'-') : $url;
            $absolute = str_starts_with($directory, '/');
            $host = realpath($absolute ? $directory : $root.'/'.$directory);
            $container = $this->normalise($absolute ? $directory : '/app/'.$directory);

            // What /app already shows, through the project or a link inside it, is not mounted again.
            if (false === $host || '/' === $container || str_starts_with($container.'/', '/app/')) {
                continue;
            }

            $volumes[$container] = ['--volume', $host.':'.$container.':ro'];
        }

        return array_merge(...array_values($volumes));
    }

    /**
     * An absolute path without `.` or `..`, a `..` at the root staying there as it does in the container.
     */
    private function normalise(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ('..' === $segment) {
                array_pop($segments);
            } elseif ('' !== $segment && '.' !== $segment) {
                $segments[] = $segment;
            }
        }

        return '/'.implode('/', $segments);
    }
}
