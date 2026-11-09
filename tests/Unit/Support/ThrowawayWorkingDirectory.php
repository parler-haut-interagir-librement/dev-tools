<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Support;

use FilesystemIterator;
use Override;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Runs each test from a throwaway directory, removed at the end of the process.
 *
 * The tests never write in the checkout: a command that loses the root it was given falls back to the working
 * directory, which is this one while a test runs. The working directory comes back between two tests because the
 * workers of ParaTest load each test file by a path relative to it.
 */
final readonly class ThrowawayWorkingDirectory implements Extension
{
    #[Override]
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $checkout = (string) getcwd();
        $sandbox = sys_get_temp_dir().'/phil-dev-cwd-'.getmypid();

        if (!is_dir($sandbox)) {
            mkdir($sandbox, 0o777, true);
        }

        $facade->registerSubscribers(
            new readonly class($sandbox) implements PreparationStartedSubscriber {
                public function __construct(private string $directory)
                {
                }

                #[Override]
                public function notify(PreparationStarted $event): void
                {
                    chdir($this->directory);
                }
            },
            new readonly class($checkout) implements FinishedSubscriber {
                public function __construct(private string $directory)
                {
                }

                #[Override]
                public function notify(Finished $event): void
                {
                    chdir($this->directory);
                }
            },
        );

        register_shutdown_function($this->remove(...), $sandbox);
    }

    private function remove(string $sandbox): void
    {
        if (!is_dir($sandbox)) {
            return;
        }

        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sandbox, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($items as $item) {
            /** @var SplFileInfo $item */
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($sandbox);
    }
}
