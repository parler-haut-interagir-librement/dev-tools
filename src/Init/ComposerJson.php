<?php

declare(strict_types=1);

namespace Phil\DevTools\Init;

use JsonException;
use Phil\DevTools\Exception\WorkspaceNotFound;
use stdClass;

/**
 * A `composer.json` that `init` adds to: values are added where they are missing, and one already there with another
 * value is left as it is unless the change is forced. Keys keep their order.
 */
final class ComposerJson
{
    /** @var list<string> */
    private const array OBJECTS = ['require', 'require-dev', 'autoload', 'autoload-dev', 'config', 'extra', 'scripts', 'scripts-descriptions', 'suggest', 'conflict', 'replace', 'provide'];

    /** @var list<string> */
    private array $changes = [];

    /** @var list<string> */
    private array $kept = [];

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(private readonly string $file, private array $data, private readonly string $original)
    {
    }

    public static function read(string $file): self
    {
        $json = is_file($file) ? file_get_contents($file) : false;

        if (false === $json) {
            throw new WorkspaceNotFound(\sprintf('%s does not exist.', $file));
        }

        try {
            $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new WorkspaceNotFound(\sprintf('%s is not valid JSON: %s', $file, $jsonException->getMessage()), $jsonException->getCode(), previous: $jsonException);
        }

        if (!\is_array($data) || array_is_list($data) && [] !== $data) {
            throw new WorkspaceNotFound(\sprintf('%s is not a JSON object.', $file));
        }

        /** @var array<string, mixed> $data */
        return new self($file, $data, $json);
    }

    /**
     * Sets `$path` — keys separated by dots, such as `extra.bamarni-bin` — to `$value` when it is missing; replaces it
     * when it differs only if `$force` is set.
     */
    public function set(string $path, mixed $value, bool $force = false): void
    {
        $keys = explode('.', $path);
        $last = array_pop($keys);
        $node = &$this->data;

        foreach ($keys as $key) {
            if (!isset($node[$key]) || !\is_array($node[$key])) {
                $node[$key] = [];
            }

            $node = &$node[$key];
        }

        $this->apply($node, $last, $path, $value, $force);
    }

    /**
     * Sets a key whose name holds dots, such as a script named `cs:fix` under `scripts`.
     */
    public function setIn(string $section, string $key, mixed $value, bool $force = false): void
    {
        if (!isset($this->data[$section]) || !\is_array($this->data[$section])) {
            $this->data[$section] = [];
        }

        /** @var array<string, mixed> $node */
        $node = $this->data[$section];
        $this->apply($node, $key, $section.'.'.$key, $value, $force);

        $this->data[$section] = $node;
    }

    /**
     * @param array<array-key, mixed> $node
     */
    private function apply(array &$node, string $key, string $path, mixed $value, bool $force): void
    {
        if (!\array_key_exists($key, $node)) {
            $node[$key] = $value;
            $this->changes[] = $path;
        } elseif ($node[$key] !== $value && $force) {
            $node[$key] = $value;
            $this->changes[] = $path.' (replaced)';
        } elseif ($node[$key] !== $value) {
            $this->kept[] = $path;
        }
    }

    /**
     * @return list<string> the paths added or replaced
     */
    public function changes(): array
    {
        return $this->changes;
    }

    /**
     * @return list<string> the paths left as they were because their value differs
     */
    public function kept(): array
    {
        return $this->kept;
    }

    public function encode(): string
    {
        $data = $this->data;

        // An empty object decodes to an empty array, which would encode back as a list.
        foreach (self::OBJECTS as $key) {
            if ([] === ($data[$key] ?? null)) {
                $data[$key] = new stdClass();
            }
        }

        $json = json_encode([] === $data ? new stdClass() : $data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        return $json."\n";
    }

    /**
     * Writes the file when something changed.
     */
    public function save(): bool
    {
        $json = $this->encode();

        if ($json === $this->original) {
            return false;
        }

        file_put_contents($this->file, $json);

        return true;
    }
}
