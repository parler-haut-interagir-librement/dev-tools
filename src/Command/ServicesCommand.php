<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Services\TestServices;
use Phil\DevTools\Testing\HttpRecording;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'services', description: 'Starts, stops and points the tests at MySQL 8, PostgreSQL 17 and Gotenberg 9', help: <<<'TXT'
    up    starts the servers of resources/compose.yaml and waits until they are healthy;
    down  stops them, leaving nothing behind;
    env   prints the variables the integration tests read, as shell exports;
    test  runs the Integration suite with those variables;
    record runs it with SYMFONY_HTTP_RECORDER=record as well, rewriting the HAR files of the tests marked
          #[UseRecord] from the real services.

    PHIL_TEST_MYSQL_URL, PHIL_TEST_POSTGRESQL_URL and PHIL_GOTENBERG_URL point at the servers on their
    ports — 13306, 15432 and 3011, moved by PHIL_DEV_MYSQL_PORT, PHIL_DEV_POSTGRESQL_PORT and
    PHIL_DEV_GOTENBERG_PORT —, unless they are already set.
    TXT)]
final class ServicesCommand extends WorkspaceCommand
{
    /** @var list<string> */
    private const array ACTIONS = ['up', 'down', 'env', 'test', 'record'];

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'up, down, env, test or record')
            ->addArgument('arguments', InputArgument::IS_ARRAY, 'For test and record: more arguments for PHPUnit, after --');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');
        $root = $this->root();

        if (!\is_string($action) || !\in_array($action, self::ACTIONS, true)) {
            $output->writeln(\sprintf('The action is one of %s.', implode(', ', self::ACTIONS)));

            return self::INVALID;
        }

        $compose = ['docker', 'compose', '--file', TestServices::composeFile(), '--project-name', TestServices::PROJECT];
        /** @var list<string> $arguments */
        $arguments = $input->getArgument('arguments');

        return match ($action) {
            'up' => $this->runner->run([...$compose, 'up', '--detach', '--wait'], $root)->exitCode,
            'down' => $this->runner->run([...$compose, 'down', '--volumes'], $root)->exitCode,
            'env' => $this->printEnvironment($output),
            'record' => $this->runner->run([$root.'/vendor/bin/phpunit', '--testsuite=Integration', ...$arguments], $root, [...TestServices::environment(), HttpRecording::MODE_VARIABLE => 'record'])->exitCode,
            default => $this->runner->run([$root.'/vendor/bin/phpunit', '--testsuite=Integration', ...$arguments], $root, TestServices::environment())->exitCode,
        };
    }

    private function printEnvironment(OutputInterface $output): int
    {
        foreach (TestServices::environment() as $name => $value) {
            $output->writeln(\sprintf("export %s='%s'", $name, str_replace("'", "'\\''", $value)));
        }

        return self::SUCCESS;
    }
}
