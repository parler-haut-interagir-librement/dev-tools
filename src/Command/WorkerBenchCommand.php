<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Bench\WorkerBench;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Services\TestServices;
use Phil\DevTools\Workspace\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bench:worker', description: 'Measures a page of a package served by FrankenPHP in worker mode', help: <<<'TXT'
    Starts the frankenphp service of resources/compose.yaml with the project mounted at /app and the worker
    script a package declares under bench.worker.script, sends bench.worker.requests requests (1000 by
    default) to it after bench.worker.warmup (50), one after the other, prints the requests per second and the
    50th, 95th and 99th percentiles, then stops the service. A failed request fails the run.

    The worker script is a front controller that loops on frankenphp_handle_request(); it is served from its
    own directory, at /<file name>. The port is 8090, moved by PHIL_DEV_FRANKENPHP_PORT.
    TXT)]
final class WorkerBenchCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this->addArgument('package', InputArgument::OPTIONAL, 'The package whose worker to measure, when several declare one');
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
        $compose = ['docker', 'compose', '--file', TestServices::composeFile(), '--project-name', TestServices::PROJECT, '--profile', 'bench'];
        $up = $this->runner->run([...$compose, 'up', '--detach', '--wait', '--force-recreate', 'frankenphp'], $root->dir, $environment);

        if (!$up->successful()) {
            return $up->exitCode;
        }

        try {
            $port = getenv('PHIL_DEV_FRANKENPHP_PORT');
            $url = \sprintf('http://127.0.0.1:%s/%s', \is_string($port) && '' !== $port ? $port : '8090', basename($relative));
            $result = new WorkerBench()->run($url, $requests, $warmup);
        } finally {
            $this->runner->run([...$compose, 'stop', 'frankenphp'], $root->dir, $environment);
        }

        $output->writeln(\sprintf('%s: %d requests, %.1f per second; p50 %.2f ms, p95 %.2f ms, p99 %.2f ms; %d failed.', $url, $result->requests(), $result->perSecond(), $result->percentile(50), $result->percentile(95), $result->percentile(99), $result->failures));

        return 0 === $result->failures ? self::SUCCESS : self::FAILURE;
    }
}
