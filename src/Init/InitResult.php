<?php

declare(strict_types=1);

namespace Phil\DevTools\Init;

/**
 * What `init` did with one file, and for `composer.json` the values it left as they were.
 */
final readonly class InitResult
{
    /**
     * @param list<string> $kept
     */
    public function __construct(public string $file, public InitStatus $status, public array $kept = [])
    {
    }
}
