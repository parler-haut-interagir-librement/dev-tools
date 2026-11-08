<?php

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/*
 * The Twig environment TwigStan compiles the templates with, when no package names its own under `twig.loader`: the
 * template directories of every package analysed, which `phil-dev twigstan` passes in PHIL_DEV_TWIG_PATHS.
 *
 * It runs inside TwigStan, with the Twig of `vendor-bin/twig/`.
 */
$paths = json_decode((string) getenv('PHIL_DEV_TWIG_PATHS'), true);

return new Environment(new FilesystemLoader(is_array($paths) ? array_filter($paths, is_string(...)) : []));
