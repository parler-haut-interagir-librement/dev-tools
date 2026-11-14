<?php

declare(strict_types=1);

/*
 * The reader of test kernels, on files nobody wrote: whatever it reads, it returns null or the name of a class the
 * input declares, and throws nothing.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\DevTools\Workspace\TestKernels;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $class = TestKernels::declared($input);

    if (null === $class) {
        return;
    }

    $short = substr($class, (int) strrpos('\\'.$class, '\\'));

    if (1 !== preg_match('/^[\w\\\\\x80-\xff]+$/', $class) || !str_contains($input, $short)) {
        throw new LogicException(sprintf('"%s" is not a class the input declares.', $class));
    }
});
$config->setMaxLen(2048);
