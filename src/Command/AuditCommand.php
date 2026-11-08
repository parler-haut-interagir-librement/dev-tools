<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'audit', description: 'Checks the installed packages against the security advisories', help: <<<'TXT'
    Runs composer audit on the root: every installed package, development ones included, against the
    advisories of Packagist; an abandoned package is reported without failing. A gate, turned off by the
    root's gates.audit: false.
    TXT)]
final class AuditCommand extends WorkspaceCommand
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->gateIsOn('audit', $output)) {
            return self::SUCCESS;
        }

        return $this->runner->run([...$this->composer(), 'audit', '--no-interaction', '--abandoned=report'], $this->workspace()->root->dir)->exitCode;
    }
}
