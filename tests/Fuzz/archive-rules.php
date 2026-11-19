<?php

declare(strict_types=1);

/*
 * The rules of a Composer archive, on patterns nobody wrote: each line of the input is a pattern of archive.exclude,
 * and the paths the input names are checked against them. Whatever it reads, it answers, without a warning of
 * preg_match nor an exception.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\DevTools\Split\ArchiveRules;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    error_clear_last();
    $lines = explode("\n", $input);
    $rules = ArchiveRules::of(sys_get_temp_dir().'/phil-dev-fuzz-none', ['archive' => ['exclude' => $lines]]);

    foreach (['tests', 'src/A.php', 'composer.json', ...$lines] as $path) {
        $rules->excludes($path, true);
        $rules->excludes($path, false);

        if (null !== error_get_last()) {
            throw new LogicException(sprintf('A pattern of "%s" broke the regular expression: %s', $input, error_get_last()['message']));
        }
    }
});
$config->setMaxLen(512);
