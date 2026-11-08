<?php

declare(strict_types=1);

namespace Phil\DevTools\Bench;

use Closure;

/**
 * Measures a page served by FrankenPHP in worker mode: requests sent one after the other, after a warm-up that
 * fills the worker's caches, each timed from the request to the end of the response.
 *
 * Sequential requests measure what one visitor waits, which is what a change to a package moves; the throughput of
 * many visitors at once depends on the machine more than on the code.
 */
final readonly class WorkerBench
{
    /**
     * @var Closure(string): bool
     */
    private Closure $fetch;

    /**
     * @param (Closure(string): bool)|null $fetch fetches a URL and says whether it answered 200; an HTTP GET by default
     */
    public function __construct(?Closure $fetch = null)
    {
        $this->fetch = $fetch ?? $this->get(...);
    }

    public function run(string $url, int $requests, int $warmup): WorkerBenchResult
    {
        for ($i = 0; $i < $warmup; ++$i) {
            ($this->fetch)($url);
        }

        $durations = [];
        $failures = 0;

        for ($i = 0; $i < $requests; ++$i) {
            $start = hrtime(true);

            if (!($this->fetch)($url)) {
                ++$failures;
            }

            $durations[] = (hrtime(true) - $start) / 1_000_000;
        }

        return new WorkerBenchResult($durations, $failures);
    }

    /**
     * Whether the URL answered 200: the status line of the response, read whatever the body, an error page included.
     */
    private function get(string $url): bool
    {
        // The headers of the request before stay readable until the next one answers.
        http_clear_last_response_headers();
        @file_get_contents($url);
        $status = http_get_last_response_headers()[0] ?? '';

        return 2 === sscanf($status, 'HTTP/%s %d', $version, $code) && 200 === $code;
    }
}
