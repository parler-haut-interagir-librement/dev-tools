<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Matrix\Matrix;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'matrix', description: 'Lists the versions of PHP and Symfony the packages are tested on', help: <<<'TXT'
    The matrix of config/matrix.json: for PHP and for Symfony, the floor the packages enforce and the next version they
    already support, from its first release candidate until it becomes the floor at its x.y.3 release.
    TXT)]
final class MatrixCommand extends WorkspaceCommand
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $matrix = Matrix::load();

        foreach (Matrix::PRODUCTS as $product) {
            foreach ($matrix->versions($product) as $version) {
                $output->writeln(rtrim(\sprintf('%-8s %-5s %-6s %s', $product, $version->version, $version->status->value, $version->image ?? '')));
            }
        }

        return self::SUCCESS;
    }
}
