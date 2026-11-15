<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Bench\WorkerBench;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Services\TestServices;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\Workspace;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bench:worker', description: 'Measures a page of a package served by FrankenPHP in worker mode', help: <<<'TXT'
    Starts the frankenphp service of resources/compose.yaml with the project mounted at /app and the worker
    script a package declares under bench.worker.script, sends bench.worker.requests requests (1000 by
    default) to it after bench.worker.warmup (50), one after the other, prints the requests per second and the
    50th, 95th and 99th percentiles, then stops the service. A failed request fails the run.

    The worker script is a front controller that loops on frankenphp_handle_request(); it is served from its
    own directory, at /<file name>. The port is 8090, moved by PHIL_DEV_FRANKENPHP_PORT.

    The root's other path repositories — a neighbouring monorepo whose packages vendor/ links to — are mounted
    read-only where those links lead inside the container: a directory beside the root at /<its name>, so that
    ../neighbour/packages/* on the host is /neighbour/packages/* there.

    With --ext, the frankenphp-ext service serves it instead, on the bench-ext image of resources/bench-ext:
    Debian, the thread-safe PHP 8.5, intl, GD, production settings, and the PIE extensions that
    PHIL_DEV_BENCH_EXT_PIE names, separated by spaces. The image is built the first time, or again with --build
    after PHIL_DEV_BENCH_EXT_PIE changes; build it on a machine at rest, never in the CI of each commit.

        PHIL_DEV_BENCH_EXT_PIE="vendor/extension" phil-dev bench:worker --ext --build
    TXT)]
final class WorkerBenchCommand extends WorkspaceCommand
{
    /**
     * The directories of the FrankenPHP image a mount must not hide.
     *
     * @var list<string>
     */
    private const array IMAGE_DIRECTORIES = ['', 'app', 'bin', 'boot', 'config', 'data', 'dev', 'etc', 'home', 'lib', 'lib64', 'opt', 'proc', 'root', 'run', 'sbin', 'srv', 'sys', 'tmp', 'usr', 'var'];

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('package', InputArgument::OPTIONAL, 'The package whose worker to measure, when several declare one')
            ->addOption('ext', null, InputOption::VALUE_NONE, 'Serve it on the bench-ext image, with intl, GD and the PIE extensions')
            ->addOption('build', null, InputOption::VALUE_NONE, 'Build the bench-ext image again first; with --ext');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();
        $root = $workspace->root;
        $name = $input->getArgument('package');
        $declaring = array_values(array_filter(
            \is_string($name) ? $workspace->targets([$name]) : $workspace->analysed(),
            static fn (Package $package): bool => null !== $package->manifest->get('bench', 'worker'),
        ));

        if ([] === $declaring) {
            $output->writeln('No package declares bench.worker: nothing to measure.');

            return self::SUCCESS;
        }

        if (\count($declaring) > 1) {
            $output->writeln('Several packages declare bench.worker; name one.');

            return self::INVALID;
        }

        $package = $declaring[0];
        $worker = $package->manifest->map('bench', 'worker');
        $script = $worker['script'] ?? null;
        $requests = $worker['requests'] ?? 1000;
        $warmup = $worker['warmup'] ?? 50;

        if (!\is_string($script) || !is_file($package->path($script)) || !\is_int($requests) || $requests < 1 || !\is_int($warmup) || $warmup < 0) {
            throw new InvalidManifest(\sprintf('bench.worker of %s needs a script that exists, and whole numbers of requests and warmup.', $package->name));
        }

        $relative = ltrim(substr($package->path($script), \strlen($root->dir)), '/');
        $environment = [
            'PHIL_DEV_WORKER_ROOT' => $root->dir,
            'PHIL_DEV_WORKER_SCRIPT' => '/app/'.$relative,
            'PHIL_DEV_WORKER_DIRECTORY' => '/app/'.\dirname($relative),
        ];
        $extension = true === $input->getOption('ext');
        $service = $extension ? 'frankenphp-ext' : 'frankenphp';
        $build = $extension && true === $input->getOption('build') ? ['--build'] : [];
        $files = ['--file', TestServices::composeFile()];
        $mounts = $this->mounts($workspace, $output);

        if ([] !== $mounts) {
            $files = [...$files, '--file', $this->override($root, $service, $mounts)];
        }

        $compose = ['docker', 'compose', ...$files, '--project-name', TestServices::PROJECT, '--profile', $extension ? 'bench-ext' : 'bench'];
        $up = $this->runner->run([...$compose, 'up', '--detach', '--wait', '--force-recreate', ...$build, $service], $root->dir, $environment);

        if (!$up->successful()) {
            return $up->exitCode;
        }

        try {
            $port = getenv('PHIL_DEV_FRANKENPHP_PORT');
            $url = \sprintf('http://127.0.0.1:%s/%s', \is_string($port) && '' !== $port ? $port : '8090', basename($relative));
            $result = new WorkerBench()->run($url, $requests, $warmup);
        } finally {
            $this->runner->run([...$compose, 'stop', $service], $root->dir, $environment);
        }

        $output->writeln(\sprintf('%s: %d requests, %.1f per second; p50 %.2f ms, p95 %.2f ms, p99 %.2f ms; %d failed.', $url, $result->requests(), $result->perSecond(), $result->percentile(50), $result->percentile(95), $result->percentile(99), $result->failures));

        return 0 === $result->failures ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Where each of the root's other path repositories is mounted in the container: the directory a relative link of
     * `vendor/` reaches on the host, at the place the same link reaches from `/app`. A repository inside the root is
     * mounted with it already, one inside another is mounted with it, and one whose place would hide a directory of the
     * image is left out with a note.
     *
     * @return array<string, string> the directories on the host, to their places in the container
     */
    private function mounts(Workspace $workspace, OutputInterface $output): array
    {
        $root = $workspace->root->dir;
        $mounts = [];

        foreach ($workspace->neighbourRepositories() as $url) {
            $directory = realpath($this->fixedPart($url));

            if (false === $directory || !is_dir($directory) || $directory === $root || str_starts_with($directory, $root.'/')) {
                continue;
            }

            if (array_any(array_keys($mounts), static fn (string $mounted): bool => $directory === $mounted || str_starts_with($directory, $mounted.'/'))) {
                continue;
            }

            $place = $this->place($root, $directory);
            $top = explode('/', ltrim($place, '/'))[0];

            if ('/' === $place || \in_array($top, self::IMAGE_DIRECTORIES, true)) {
                $output->writeln(\sprintf("%s is not mounted: its place in the container, %s, would hide the image's own.", $directory, $place));

                continue;
            }

            $mounts = array_filter($mounts, static fn (string $mounted): bool => !str_starts_with($mounted, $directory.'/'), \ARRAY_FILTER_USE_KEY);
            $mounts[$directory] = $place;
        }

        return $mounts;
    }

    /**
     * The part of a path repository's URL before its first glob: `/code/public/packages/*` is `/code/public/packages`.
     */
    private function fixedPart(string $url): string
    {
        $parts = [];

        foreach (explode('/', $url) as $part) {
            if (false !== strpbrk($part, '*?[{')) {
                break;
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    /**
     * The place a directory outside the root has in the container: its path below the nearest directory it shares
     * with the root, from `/`, since a link that climbs out of `/app` stops at `/`.
     */
    private function place(string $root, string $directory): string
    {
        $from = explode('/', trim($root, '/'));
        $to = explode('/', trim($directory, '/'));
        $shared = 0;

        while (isset($from[$shared], $to[$shared]) && $from[$shared] === $to[$shared]) {
            ++$shared;
        }

        return '/'.implode('/', \array_slice($to, $shared));
    }

    /**
     * Writes the Compose file that adds the mounts to the service, and returns its path.
     *
     * @param array<string, string> $mounts
     */
    private function override(Package $root, string $service, array $mounts): string
    {
        $volumes = [];

        foreach ($mounts as $source => $target) {
            $volumes[] = ['type' => 'bind', 'source' => $source, 'target' => $target, 'read_only' => true];
        }

        $file = $root->path('var/phil-dev/bench/compose.neighbours.yaml');

        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), recursive: true);
        }

        file_put_contents($file, json_encode(['services' => [$service => ['volumes' => $volumes]]], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n");

        return $file;
    }
}
