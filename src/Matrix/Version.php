<?php

declare(strict_types=1);

namespace Phil\DevTools\Matrix;

/**
 * One version of the matrix: PHP or Symfony, the floor the packages enforce or the next one they already support, and
 * for PHP the Docker image it runs in.
 */
final readonly class Version
{
    public function __construct(
        public string $version,
        public VersionStatus $status,
        public ?string $image = null,
    ) {
    }

    /**
     * The `PHP_VERSION_ID` of the version's first release, which PHPStan reads: 80600 for 8.6.
     */
    public function id(): int
    {
        [$major, $minor] = explode('.', $this->version);

        return (int) $major * 10_000 + (int) $minor * 100;
    }
}
