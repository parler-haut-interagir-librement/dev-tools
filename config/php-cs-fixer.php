<?php

declare(strict_types=1);

use Phil\DevTools\Config\Tooling;
use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

require_once __DIR__.'/bootstrap.php';

/*
 * The shared code style: `@Symfony` and `@Symfony:risky`, with the PHP migration sets read from the `php` constraint
 * of `composer.json`, so that no version number is written here.
 *
 * A project's `.php-cs-fixer.dist.php` calls the closure with its own directory; the paths come from the
 * `phil-dev.php` of the root and of each package, and the root's `cs.rules` adds to or changes the rules.
 *
 * @return Closure(string, array<string, mixed>=): Config
 */
return static function (string $root, array $rules = []): Config {
    $tooling = Tooling::fromEnvironment($root);

    $finder = new Finder()
        ->in($tooling->directories())
        ->append([...$tooling->files(), ...$tooling->configurationFiles()])
        ->exclude(['Fixtures', 'vendor', 'var']);

    foreach ($tooling->codeStyleExclusions() as $exclusion) {
        $finder->notPath($exclusion);
    }

    return new Config()
        ->setRiskyAllowed(true)
        ->setParallelConfig(ParallelConfigFactory::detect())
        ->setCacheFile($root.'/var/php-cs-fixer.cache')
        ->setRules([
            '@Symfony' => true,
            '@Symfony:risky' => true,
            '@autoPHPMigration' => true,
            '@autoPHPMigration:risky' => true,

            // `declare(strict_types=1)` everywhere: without it, the string "12abc" passes for an `int` at the boundary
            // of a value object, which is exactly where validation was meant to bite.
            'declare_strict_types' => true,

            // `0 == 'abc'` and `in_array($id, $ids)` without its third argument are two ways a loose comparison answers
            // "yes" to a question of identity. Dates are never compared with `==` nor `===`: this rule turns the first
            // into the second, and `===` between two date objects compares their identity, not their instant.
            'strict_comparison' => true,
            'strict_param' => true,

            'concat_space' => ['spacing' => 'none'],
            'global_namespace_import' => [
                'import_classes' => true,
                'import_constants' => false,
                'import_functions' => false,
            ],

            // A docblock that explains *why* is not a comment to demote.
            'phpdoc_to_comment' => false,
            ...$tooling->codeStyleRules(),
            ...$rules,
        ])
        ->setFinder($finder);
};
