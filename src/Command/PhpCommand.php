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
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'php', description: 'Runs a command on a PHP version of the matrix, in its Docker image', help: <<<'TXT'
    Runs the command — PHPUnit by default — with the project mounted at /app, in the image of the PHP version the matrix
    (config/matrix.json) names: a version such as 8.6, or "floor" or "next". The image is built from resources/php the
    first time, again when resources/php/Dockerfile changed since it was built — the image carries the hash of the
    Dockerfile it was built from in its label phil-dev.recipe —, or again with --build.

    The directories outside the project that its path repositories name — a neighbouring monorepo whose packages it
    installs, phil/dev-tools beside it — are mounted read-only where the same relative path reaches them from /app,
    so that Composer and the autoloader find them in the container as on the host.

    When the project is a git worktree, its .git file names a directory under .git/worktrees/ of its repository: that
    repository is mounted read-only at the path the .git file names, and git is told that /app is safe, so that git
    reaches the repository in the container — infection --changed lists the changed files from a worktree as from the
    main checkout. A .git file that cannot be read, or that names anything else, is reported and nothing is mounted.

    The temporary directories — PHPStan's, the tmpDir of the root's phpstan.neon (var/phpstan by default) and
    var/phpstan-witnesses, the TMPDIR of the tests (var/tmp) and Infection's (var/infection/tmp) — are replaced in the
    container by directories of its own under var/phil-dev/php/<version>/: PHPStan keys part of its cache by the PHP
    version it analyses for, not by where its phar lies, and the test kernels compile their containers with the
    paths of the checkout, so a cache the host wrote would send the container to the host's paths.

        phil-dev php next
        phil-dev php next -- php vendor/bin/phil-dev check-packages
        phil-dev php next -- php vendor/bin/phil-dev infection
    TXT)]
final class PhpCommand extends WorkspaceCommand
{
    /**
     * The label of an image that holds the hash of the Dockerfile it was built from.
     */
    public const string RECIPE_LABEL = 'phil-dev.recipe';

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
        $recipe = hash_file('sha256', DevTools::resource('php/Dockerfile'));

        if (true === $input->getOption('build') || $this->builtFrom($tag, $root) !== $recipe) {
            $build = $this->runner->run(['docker', 'build', '--build-arg', 'PHP_IMAGE='.$version->image, '--label', self::RECIPE_LABEL.'='.$recipe, '--tag', $tag, DevTools::resource('php')], $root);

            if (!$build->successful()) {
                return $build->exitCode;
            }
        }

        /** @var list<string> $arguments */
        $arguments = $input->getArgument('arguments');
        $repository = $this->repository($root);

        if (\is_string($repository)) {
            ($output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output)->writeln(\sprintf('<comment>%s: the repository is not mounted, and git cannot reach it in the container.</comment>', $repository));
            $repository = [];
        }

        return $this->runner->run(
            ['docker', 'run', '--rm', '--volume', $root.':/app', ...$this->caches($root, $version->version), ...$this->neighbours($root), ...$repository, '--workdir', '/app', $tag, ...([] === $arguments ? ['php', 'vendor/bin/phpunit'] : $arguments)],
            $root,
        )->exitCode;
    }

    /**
     * The hash of the Dockerfile the image was built from, its label `phil-dev.recipe`; null when the image is missing:
     * an image built from an older Dockerfile lacks what the newer one adds, an extension or a program of the tests.
     */
    private function builtFrom(string $tag, string $root): ?string
    {
        $inspect = $this->runner->capture(['docker', 'image', 'inspect', '--format', '{{ index .Config.Labels "'.self::RECIPE_LABEL.'" }}', $tag], $root);

        return $inspect->successful() ? trim($inspect->output) : null;
    }

    /**
     * The `--volume` arguments that give the container temporary directories of its own, created on the host under
     * var/phil-dev/php/<version>/: PHPStan's reflection cache stores the absolute path of the stubs inside the phar
     * under a key the host and the container share, and the container then fails on a stub "is not a file"; the test
     * kernels under the TMPDIR of the tests and Infection's copies under its tmpDir hold the host's paths too.
     *
     * @return list<string>
     */
    private function caches(string $root, string $version): array
    {
        $volumes = [];

        foreach ([$this->phpstanTmpDir($root), 'var/phpstan-witnesses', 'var/tmp', 'var/infection/tmp'] as $directory) {
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
                $segments = array_filter(explode('/', $match[1]), static fn (string $segment): bool => '' !== $segment && '.' !== $segment);

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
     * The arguments that let git reach, in the container, the repository of a worktree mounted at /app: its directory
     * mounted read-only at the path the `.git` file of the worktree names — or, for a relative one, where that path
     * reaches from /app — and /app declared safe through the environment of the container, which leaves the host's
     * git configuration alone. Nothing for an ordinary checkout, whose `.git` is a directory /app already shows. The
     * reason, as a string, when the `.git` file cannot be read or names anything but a directory under the
     * `worktrees/` of a repository, which is then not mounted.
     *
     * The mount is a `--mount`, not a `--volume`: some engines misread a `--volume` whose source and target are the same
     * path.
     *
     * @return list<string>|string
     */
    private function repository(string $root): array|string
    {
        $file = $root.'/.git';

        if (!is_file($file)) {
            return [];
        }

        $content = is_readable($file) ? file_get_contents($file) : false;

        if (false === $content || 1 !== preg_match('~\Agitdir: (\V+?)\s*\z~', $content, $match)) {
            return $file.' cannot be read as the gitdir of a worktree';
        }

        $written = $match[1];
        $absolute = str_starts_with($written, '/');
        $gitdir = realpath($absolute ? $written : $root.'/'.$written);
        $commondir = false !== $gitdir && is_file($gitdir.'/commondir') ? trim(Files::read($gitdir.'/commondir')) : '';
        $common = '' === $commondir ? false : realpath(str_starts_with($commondir, '/') ? $commondir : $gitdir.'/'.$commondir);

        if (false === $common || \dirname($gitdir) !== $common.'/worktrees') {
            return \sprintf('%s names %s, which is not the directory of a worktree under the worktrees/ of a repository', $file, $written);
        }

        $container = str_starts_with($commondir, '/') ? $this->normalise($commondir) : $this->normalise(($absolute ? $written : '/app/'.$written).'/'.$commondir);
        $safe = ['--env', 'GIT_CONFIG_COUNT=1', '--env', 'GIT_CONFIG_KEY_0=safe.directory', '--env', 'GIT_CONFIG_VALUE_0=/app'];

        // What /app already shows is not mounted again.
        if ('/' === $container || str_starts_with($container.'/', '/app/')) {
            return $safe;
        }

        return ['--mount', 'type=bind,'.$this->field('source='.$common).','.$this->field('target='.$container).',readonly', ...$safe];
    }

    /**
     * A field of a `--mount`, which Docker reads as CSV: quoted, its quotes doubled, when it holds a comma or a quote.
     */
    private function field(string $field): string
    {
        return false === strpbrk($field, ',"') ? $field : '"'.str_replace('"', '""', $field).'"';
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
