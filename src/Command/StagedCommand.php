<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'staged', description: 'Runs a fast gate on the staged files, for the pre-commit hook', help: <<<'TXT'
    Runs PHP-CS-Fixer (php-cs-fixer), PHPStan (phpstan) or Twig-CS-Fixer (twig-cs-fixer), installed under
    vendor-bin/, on the files added, copied, modified or renamed in the index: the PHP files for the first two, the
    Twig templates for the third, those under one of the --in-dir directories when any is given. Nothing staged of
    that type passes.

    The names are read from git separated by NUL bytes and handed to the tool as arguments of their own, after --,
    never through a shell: a staged file named with spaces, quotes, a semicolon or a leading dash stays one path.

    PHPStan given only files its configuration excludes — excludePaths, a manifest phil-dev.php — finds nothing to
    analyse and passes.

        phil-dev staged php-cs-fixer
        phil-dev staged phpstan --in-dir=packages/
    TXT)]
final class StagedCommand extends WorkspaceCommand
{
    /**
     * Each tool: its directory under `vendor-bin/`, its binary, its arguments before the files, the extension of the
     * files it reads.
     *
     * @var array<string, array{string, string, list<string>, string}>
     */
    private const array TOOLS = [
        'php-cs-fixer' => ['php-cs-fixer', 'php-cs-fixer', ['check', '--diff', '--path-mode=intersection'], 'php'],
        'phpstan' => ['phpstan', 'phpstan', ['analyse', '--no-progress'], 'php'],
        'twig-cs-fixer' => ['twig', 'twig-cs-fixer', ['lint', '--no-cache'], 'twig'],
    ];

    /**
     * The configurations PHP-CS-Fixer looks for in the root, in its order.
     *
     * @var list<string>
     */
    private const array PHP_CS_FIXER_CONFIGURATIONS = ['.php-cs-fixer.php', '.php-cs-fixer.dist.php'];

    /**
     * What PHPStan writes, and fails on, when each file it was given is excluded from the analysis.
     */
    private const string NOTHING_TO_ANALYSE = 'No files found to analyse.';

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('tool', InputArgument::REQUIRED, 'php-cs-fixer, phpstan or twig-cs-fixer')
            ->addOption('in-dir', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only the staged files under this directory, relative to the root; repeatable');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('tool');

        if (!\is_string($name) || !\array_key_exists($name, self::TOOLS)) {
            $output->writeln(\sprintf('<error>Unknown tool %s: php-cs-fixer, phpstan or twig-cs-fixer.</error>', \is_string($name) ? $name : ''));

            return self::INVALID;
        }

        [$directory, $binary, $arguments, $extension] = self::TOOLS[$name];
        /** @var list<string> $directories */
        $directories = $input->getOption('in-dir');
        $files = $this->stagedFiles($extension, $directories);

        if ([] === $files) {
            $output->writeln(\sprintf('No staged .%s file: nothing to check.', $extension));

            return self::SUCCESS;
        }

        $root = $this->workspace()->root->dir;
        $command = [$this->tool($directory, $binary), ...$arguments, ...$this->options($name, $root, $output)];

        if ('phpstan' !== $name) {
            return $this->runner->run([...$command, '--', ...$files], $root)->exitCode;
        }

        // PHPStan's error output is read, to tell an empty analysis from a failed one.
        $result = $this->runner->capture([...$command, '--', ...$files], $root, withErrors: true);

        if (!$result->successful() && str_contains($result->output, self::NOTHING_TO_ANALYSE)) {
            $output->writeln('PHPStan excludes every staged PHP file: nothing to analyse.');

            return self::SUCCESS;
        }

        $output->write($result->output, false, OutputInterface::OUTPUT_RAW);

        return $result->exitCode;
    }

    /**
     * The options of the tool that depend on the project: PHP-CS-Fixer requires its configuration when it is given
     * several paths; PHPStan, whose output is captured, keeps the colours of a terminal.
     *
     * @return list<string>
     */
    private function options(string $name, string $root, OutputInterface $output): array
    {
        if ('phpstan' === $name) {
            return [$output->isDecorated() ? '--ansi' : '--no-ansi'];
        }

        if ('php-cs-fixer' === $name) {
            foreach (self::PHP_CS_FIXER_CONFIGURATIONS as $file) {
                if (is_file($root.'/'.$file)) {
                    return ['--config='.$root.'/'.$file];
                }
            }
        }

        return [];
    }

    /**
     * The staged files — added, copied, modified or renamed — with the extension, under one of the directories when
     * any is given, as git names them relative to the root, in its order.
     *
     * @param list<string> $directories
     *
     * @return list<string>
     */
    private function stagedFiles(string $extension, array $directories): array
    {
        $listed = $this->runner->capture(['git', 'diff', '--cached', '--name-only', '-z', '--diff-filter=ACMR'], $this->workspace()->root->dir);
        $prefixes = array_map(static fn (string $directory): string => rtrim($directory, '/').'/', $directories);
        $files = [];

        foreach (explode("\0", $listed->output) as $file) {
            if (!str_ends_with($file, '.'.$extension)) {
                continue;
            }

            if ([] === $prefixes || array_any($prefixes, static fn (string $prefix): bool => str_starts_with($file, $prefix))) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
