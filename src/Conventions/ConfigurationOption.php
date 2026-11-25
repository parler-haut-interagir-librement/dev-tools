<?php

declare(strict_types=1);

namespace Phil\DevTools\Conventions;

/**
 * An option of a bundle's configuration tree, as its builder declares it.
 */
final readonly class ConfigurationOption
{
    /**
     * @param string      $path       the dotted path from the root key, such as `network.ipv4_prefix`
     * @param bool        $hasDefault whether the builder gives the option a default
     * @param string|null $default    the default, when it is a literal, normalised as a recipe would write it
     * @param bool        $required   whether the builder declares the option required
     */
    public function __construct(
        public string $path,
        public bool $hasDefault,
        public ?string $default,
        public bool $required,
    ) {
    }

    /**
     * The option's own name, the last part of its path.
     */
    public function name(): string
    {
        $parts = explode('.', $this->path);

        return end($parts);
    }
}
