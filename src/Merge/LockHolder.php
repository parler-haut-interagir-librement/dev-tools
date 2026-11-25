<?php

declare(strict_types=1);

namespace Phil\DevTools\Merge;

use JsonException;

/**
 * What the merge lock says of the merge that holds it: its process, when it started, its lot and its branch. A lock
 * whose content cannot be read has no process and an unknown lot.
 */
final readonly class LockHolder
{
    public function __construct(
        public ?int $pid,
        public string $lot,
        public string $branch,
        public string $started,
    ) {
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }

        $data = \is_array($data) ? $data : [];
        $pid = $data['pid'] ?? null;

        return new self(
            \is_int($pid) && $pid > 0 ? $pid : null,
            self::text($data['lot'] ?? null),
            self::text($data['branch'] ?? null),
            self::text($data['started'] ?? null),
        );
    }

    public function toJson(): string
    {
        return json_encode(['pid' => $this->pid, 'started' => $this->started, 'lot' => $this->lot, 'branch' => $this->branch], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES)."\n";
    }

    public function describe(): string
    {
        return \sprintf('lot %s (branch %s, PID %s, started %s)', $this->lot, $this->branch, $this->pid ?? '?', $this->started);
    }

    private static function text(mixed $value): string
    {
        return \is_string($value) && '' !== $value ? $value : '?';
    }
}
