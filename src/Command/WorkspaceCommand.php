<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\DevTools;
use Phil\DevTools\Exception\WorkspaceNotFound;
use Phil\DevTools\Process\LocalProcessRunner;
use Phil\DevTools\Process\ProcessRunner;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\Workspace;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command that works on the workspace of a root directory: `PHIL_DEV_ROOT`, or the directory it runs from.
 *
 * Every command takes `--skip-planned`, which leaves the planned packages out, as `PHIL_DEV_SKIP_PLANNED=1` does: the
 * option sets the variable, so that the tools the command starts leave them out too. While they are left out, the
 * command first tells, on the error output, how many it skipped.
 */
abstract class WorkspaceCommand extends Command
{
    public function __construct(
        private readonly ?string $root = null,
        protected readonly ProcessRunner $runner = new LocalProcessRunner(),
    ) {
        parent::__construct();
        $this->addOption('skip-planned', null, InputOption::VALUE_NONE, 'Leave out the planned packages, as PHIL_DEV_SKIP_PLANNED=1 does');
    }

    #[Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        if (true === $input->getOption('skip-planned')) {
            // The tools the command starts — PHPStan, Rector, PHPUnit — read the variable through their configurations.
            putenv(Workspace::SKIP_PLANNED_VARIABLE.'=1'); // @phpstan-ignore disallowed.function
        }

        if (!Workspace::skipPlannedFromEnvironment()) {
            return;
        }

        try {
            $skipped = $this->workspace()->skipped();
        } catch (WorkspaceNotFound) {
            return;
        }

        $names = array_map(static fn (Package $package): string => $package->name, $skipped);
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $errors->writeln(\sprintf('Skipped %d planned package%s%s.', \count($names), 1 === \count($names) ? '' : 's', [] === $names ? '' : ': '.implode(', ', $names)));
    }

    protected function root(): string
    {
        return $this->root ?? DevTools::root();
    }

    protected function workspace(): Workspace
    {
        return Workspace::load($this->root());
    }

    /**
     * Writes a generated file under `var/phil-dev/` of the root, in a subdirectory when its name has one, and returns its path.
     */
    protected function generate(string $name, string $content): string
    {
        $file = $this->workspace()->root->path('var/phil-dev/'.$name);

        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), recursive: true);
        }

        file_put_contents($file, $content);

        return $file;
    }

    /**
     * The variables a process that runs the tests gets: `TMPDIR` under `var/tmp/` of the root, so that the test kernels
     * of two checkouts of one repository — a clone and its worktrees — never share a cache directory under the system's
     * temporary directory. A `TMPDIR` that already points inside the root is kept.
     *
     * @return array<string, string>
     */
    protected function testEnvironment(): array
    {
        $root = (string) realpath($this->root());
        $current = getenv('TMPDIR');

        // An empty TMPDIR is the system's temporary directory, whatever directory realpath() would make of it.
        if (\is_string($current) && '' !== $current && str_starts_with(realpath($current).'/', $root.'/')) {
            return [];
        }

        $directory = $root.'/var/tmp';

        if (!is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        return ['TMPDIR' => $directory];
    }

    /**
     * The binary of a tool installed under `vendor-bin/`.
     */
    protected function tool(string $tool, string $binary): string
    {
        return $this->workspace()->root->path('vendor-bin/'.$tool.'/vendor/bin/'.$binary);
    }

    /**
     * Composer: the one running the script when `phil-dev` runs from a Composer script, else the one on the `PATH`.
     *
     * @return non-empty-list<string>
     */
    protected function composer(): array
    {
        $binary = getenv('COMPOSER_BINARY');

        return \is_string($binary) && '' !== $binary ? [\PHP_BINARY, $binary] : ['composer'];
    }

    /**
     * A whole number given as an option, a string of digits, or in a manifest, an integer; null for anything else.
     */
    protected static function wholeNumber(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value;
        }

        return \is_string($value) && 1 === preg_match('/^\d+$/', $value) ? (int) $value : null;
    }

    /**
     * Whether the gate is on: every gate is, unless the root's `phil-dev.php` turns it off under `gates`.
     */
    protected function gateIsOn(string $gate, OutputInterface $output): bool
    {
        if ($this->workspace()->root->manifest->flag('gates', $gate, true)) {
            return true;
        }

        $output->writeln(\sprintf('The %s gate is turned off in phil-dev.php.', $gate));

        return false;
    }
}
