# Benchmarks

PHPBench measures the subjects of the `*Bench.php` files, with the shared configuration of `config/phpbench.json`:
five iterations of a hundred revolutions, after a warm-up, with the `phil` report.

| Script | What it runs |
|---|---|
| `composer bench` | The benchmarks. |
| `composer bench:baseline` | The benchmarks, stored as the baseline of this machine. |
| `composer bench:compare` | The benchmarks against the baseline, failing above the threshold. |
| `composer bench:worker` | A page served by FrankenPHP in worker mode. |

None of them is a gate: a timing depends on the machine and on what else runs on it.

## Regressions against a baseline

`phil-dev bench --baseline` stores the run under the tag `phil_baseline` in `var/phpbench/`. A timing belongs to the
machine that measured it, so the baseline stays on it, made on the commit the comparison starts from, such as the
base of a branch.

`phil-dev bench --compare` runs again against that baseline and fails when the mode of a subject's time is slower
than the baseline's by more than the threshold: `--threshold`, else the root's `bench.threshold`, else 10 %.

```php
'bench' => ['threshold' => 15],
```

## Worker mode

A package that runs under FrankenPHP in worker mode — a bundle whose services must reset between requests, a
library that keeps a cache in memory — measures a page served by a worker that lives across requests. It declares a
worker script, a front controller that loops on `frankenphp_handle_request()`:

```php
'bench' => [
    'worker' => ['script' => 'tests/Bench/worker.php', 'requests' => 1000, 'warmup' => 50],
],
```

```php
<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

$kernel = new Phil\Example\Tests\Bench\BenchKernel();

while (frankenphp_handle_request(static function () use ($kernel): void {
    echo $kernel->handle();
})) {
    gc_collect_cycles();
}
```

`phil-dev bench:worker [package]` starts the `frankenphp` service of `resources/compose.yaml` with the project
mounted at `/app`, serves the script from its own directory at `/<file name>`, sends `requests` requests after
`warmup` requests, one after the other, prints the requests per second and the 50th, 95th and 99th percentiles, then
stops the service. A request that does not answer 200 fails the run. The port is 8090, moved by
`PHIL_DEV_FRANKENPHP_PORT`; the service has the `bench` profile, so `phil-dev services up` leaves it out.

Sequential requests measure what one visitor waits, which is what a change to a package moves; the throughput of many
visitors at once depends on the machine more than on the code.
