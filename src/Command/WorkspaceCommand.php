<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Phil\DevTools\DevTools;
use Phil\DevTools\Process\LocalProcessRunner;
use Phil\DevTools\Process\ProcessRunner;
use Phil\DevTools\Workspace\Workspace;
use Symfony\Component\Console\Command\Command;

/**
 * A command that works on the workspace of a root directory: `PHIL_DEV_ROOT`, or the directory it runs from.
 */
abstract class WorkspaceCommand extends Command
{
    public function __construct(
        private readonly ?string $root = null,
        protected readonly ProcessRunner $runner = new LocalProcessRunner(),
    ) {
        parent::__construct();
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
     * Writes a generated file under `var/phil-dev/` of the root, and returns its path.
     */
    protected function generate(string $name, string $content): string
    {
        $directory = $this->workspace()->root->path('var/phil-dev');

        if (!is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        file_put_contents($directory.'/'.$name, $content);

        return $directory.'/'.$name;
    }

    /**
     * The binary of a tool installed under `vendor-bin/`.
     */
    protected function tool(string $tool, string $binary): string
    {
        return $this->workspace()->root->path('vendor-bin/'.$tool.'/vendor/bin/'.$binary);
    }
}
