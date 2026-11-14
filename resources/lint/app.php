<?php

declare(strict_types=1);

/*
 * Lints a functional test application through its own console, with the autoloader of the workspace: phil-dev
 * lint-apps runs it once per kernel, in a process of its own, so that each kernel boots alone.
 *
 *     php app.php vendor/autoload.php 'Vendor\Bundle\Tests\Functional\App\TestKernel'
 *
 * It runs lint:container, lint:expressions and, when the application's config/ directory holds YAML files,
 * lint:yaml --check-schema on it; a command the application does not have is skipped with a note. It fails when one
 * of them fails, and is skipped when the kernel is not built from an environment and a debug flag alone.
 */
if ($argc !== 3) {
    echo "Usage: php app.php <autoload.php> <kernel class>\n";

    exit(2);
}

[, $autoload, $class] = $argv;

if (!is_file($autoload)) {
    echo "Run composer install first: {$autoload} does not exist.\n";

    exit(1);
}

require $autoload;

if (!class_exists($class) || !is_subclass_of($class, Symfony\Component\HttpKernel\KernelInterface::class)) {
    echo "{$class} is not a kernel: skipped.\n";

    exit(0);
}

// The kernel is built as Symfony's: its constructor takes $environment and $debug first, and nothing else it needs.
$reflection = new ReflectionClass($class);
$parameters = $reflection->getConstructor()?->getParameters() ?? [];
$builds = $reflection->isInstantiable()
    && 'environment' === ($parameters[0] ?? null)?->getName()
    && 'debug' === ($parameters[1] ?? null)?->getName()
    && [] === array_filter(array_slice($parameters, 2), static fn (ReflectionParameter $parameter): bool => !$parameter->isOptional());

if (!$builds) {
    echo "{$class} is not built from an environment and a debug flag alone: skipped.\n";

    exit(0);
}

if (!class_exists(Symfony\Bundle\FrameworkBundle\Console\Application::class)) {
    echo "{$class}: symfony/framework-bundle is not installed: skipped.\n";

    exit(0);
}

$output = new Symfony\Component\Console\Output\ConsoleOutput();
$status = 0;

try {
    $kernel = $reflection->newInstance('test', true);
    $application = new Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
    $application->setAutoExit(false);
    $runs = [['command' => 'lint:container'], ['command' => 'lint:expressions']];
    $config = $kernel->getProjectDir().'/config';
    $yaml = is_dir($config) ? glob($config.'/{,*/,*/*/}*.{yaml,yml}', GLOB_BRACE) : [];

    if (is_array($yaml) && [] !== $yaml) {
        // An explicit --check-schema fails without the validator: the syntax alone is checked then, and said so.
        $schema = class_exists(Opis\JsonSchema\Validator::class);
        $runs[] = ['command' => 'lint:yaml', 'filename' => [$config], $schema ? '--check-schema' : '--no-check-schema' => true];

        if (!$schema) {
            $output->writeln('opis/json-schema is not installed: lint:yaml checks the syntax only.');
        }
    }

    foreach ($runs as $run) {
        // has() boots the kernel the first time: a container that does not build fails here.
        if (!$application->has($run['command'])) {
            $output->writeln($run['command'].' is not available in this application: skipped.');

            continue;
        }

        if (0 !== $application->run(new Symfony\Component\Console\Input\ArrayInput($run), $output)) {
            $status = 1;
        }
    }
} catch (Throwable $exception) {
    $output->writeln(sprintf('%s does not boot: %s', $class, $exception->getMessage()));
    $status = 1;
}

exit($status);
