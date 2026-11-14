<?php

declare(strict_types=1);

namespace Phil\DevTools\Workspace;

use Phil\DevTools\Files;
use PhpToken;

/**
 * The kernels of a package's functional test applications: the concrete classes under `tests/Functional/` whose
 * parent's name ends with `Kernel`, found by reading the files, never by loading them — the workspace's autoloader
 * is not this command's.
 *
 * The parent is read as it is written, so that a kernel extending Symfony's `Kernel`, an alias of it or another test
 * kernel of the package is found alike; the process that boots it checks that it is a kernel.
 */
final readonly class TestKernels
{
    public const string DIRECTORY = 'tests/Functional';

    /**
     * The kernels of the package, by class name, each with its file, sorted by file.
     *
     * @return array<string, string>
     */
    public static function of(Package $package): array
    {
        $kernels = [];

        foreach ($package->filesUnder(self::DIRECTORY) as $file) {
            if (!str_ends_with($file, '.php')) {
                continue;
            }

            $class = self::declared(Files::read($package->path($file)));

            if (null !== $class) {
                $kernels[$class] = $package->path($file);
            }
        }

        return $kernels;
    }

    /**
     * The fully qualified name of the first concrete class of the code whose parent's name ends with `Kernel`, or null.
     */
    public static function declared(string $code): ?string
    {
        $tokens = array_values(array_filter(PhpToken::tokenize($code), static fn (PhpToken $token): bool => !$token->isIgnorable()));
        $namespace = '';
        $count = \count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $next = $tokens[$i + 1] ?? null;

            if ($tokens[$i]->is(\T_NAMESPACE) && true === $next?->is([\T_STRING, \T_NAME_QUALIFIED])) {
                $namespace = $next->text.'\\';

                continue;
            }

            if (!$tokens[$i]->is(\T_CLASS) || !self::concrete($tokens, $i)) {
                continue;
            }

            $name = $tokens[$i + 1] ?? null;
            $extends = $tokens[$i + 2] ?? null;
            $parent = $tokens[$i + 3] ?? null;

            if (null !== $name && $name->is(\T_STRING) && true === $extends?->is(\T_EXTENDS) && null !== $parent && str_ends_with($parent->text, 'Kernel')) {
                return $namespace.$name->text;
            }
        }

        return null;
    }

    /**
     * Whether the `class` keyword at `$index` declares a concrete named class: neither `Foo::class`, nor an anonymous
     * class, nor one whose modifiers before it hold `abstract`.
     *
     * @param list<PhpToken> $tokens
     */
    private static function concrete(array $tokens, int $index): bool
    {
        $before = $tokens[$index - 1] ?? null;

        if (null === $before || $before->is([\T_DOUBLE_COLON, \T_NEW])) {
            return false;
        }

        for ($i = $index - 1; $i >= 0 && $tokens[$i]->is([\T_FINAL, \T_READONLY, \T_ABSTRACT]); --$i) {
            if ($tokens[$i]->is(\T_ABSTRACT)) {
                return false;
            }
        }

        return true;
    }
}
