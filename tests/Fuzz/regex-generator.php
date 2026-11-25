<?php

declare(strict_types=1);

/*
 * The regex generator, on patterns nobody wrote: each input is the body of a pattern between slashes, with the u
 * modifier when the input is valid UTF-8. Whatever it reads, the generator draws strings the pattern accepts or refuses
 * the pattern with UnsupportedPattern or RejectedCaseAccepted; never another exception, a warning or a hang.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\DevTools\Exception\RejectedCaseAccepted;
use Phil\DevTools\Exception\UnsupportedPattern;
use Phil\DevTools\Fuzz\RegexGenerator;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    error_clear_last();
    $pattern = '/'.str_replace('/', '\/', $input).'/'.(mb_check_encoding($input, 'UTF-8') ? 'u' : '');

    try {
        new RegexGenerator(1, 4)->generate($pattern, ["\x00phil-dev-fuzz\x01"], 4);
    } catch (UnsupportedPattern|RejectedCaseAccepted) {
        // A refusal is an answer.
    }

    if (null !== error_get_last()) {
        throw new LogicException(sprintf('The pattern %s raised a warning: %s', $pattern, error_get_last()['message']));
    }
});
$config->setMaxLen(256);
