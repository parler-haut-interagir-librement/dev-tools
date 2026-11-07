<?php

declare(strict_types=1);

namespace Phil\DevTools\Docs;

use Stringable;

/**
 * One finding of the documentation checks: its code, the file relative to the package, the line (null for the whole
 * file) and what was found.
 */
final readonly class Finding implements Stringable
{
    public function __construct(
        public string $code,
        public string $file,
        public ?int $line,
        public string $detail,
    ) {
    }

    public function __toString(): string
    {
        return \sprintf('[%s] %s%s: %s', $this->code, $this->file, null === $this->line ? '' : ':'.$this->line, $this->detail);
    }
}
