<?php

declare(strict_types=1);

namespace Phil\DevTools\Bench;

/**
 * The timings of a worker-mode benchmark, in milliseconds.
 */
final readonly class WorkerBenchResult
{
    /** @var list<float> */
    public array $durations;

    /**
     * @param list<float> $durations each request, in milliseconds
     */
    public function __construct(array $durations, public int $failures)
    {
        sort($durations);
        $this->durations = $durations;
    }

    public function requests(): int
    {
        return \count($this->durations);
    }

    /**
     * The duration under which `$percent` % of the requests answered, by the nearest rank; `$percent` goes from 0 to 100.
     */
    public function percentile(int $percent): float
    {
        if ([] === $this->durations) {
            return 0.0;
        }

        // The nearest rank, ceil(percent × count / 100), in whole numbers; the first request at the least.
        $rank = max(1, intdiv($percent * \count($this->durations) + 99, 100));

        return $this->durations[$rank - 1];
    }

    public function perSecond(): float
    {
        $total = array_sum($this->durations);

        return $total > 0 ? \count($this->durations) / ($total / 1000) : 0.0;
    }
}
