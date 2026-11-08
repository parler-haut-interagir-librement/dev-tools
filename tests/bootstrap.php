<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

// The tests never write in the checkout. A command that loses the root it was given (an Infection mutant of
// WorkspaceCommand::root() or Tooling::load(), for one) falls back to the working directory: here, a throwaway
// directory, removed at the end of the run, rather than the root of dev-tools.
(static function (): void {
    $sandbox = sys_get_temp_dir().'/phil-dev-cwd-'.getmypid();

    if (!is_dir($sandbox)) {
        mkdir($sandbox, 0o777, true);
    }

    chdir($sandbox);

    register_shutdown_function(static function () use ($sandbox): void {
        if (!is_dir($sandbox)) {
            return;
        }

        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sandbox, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($items as $item) {
            /** @var SplFileInfo $item */
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($sandbox);
    });
})();
