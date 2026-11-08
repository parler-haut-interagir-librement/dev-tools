<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Files;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\Workspace;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'sbom', description: 'Writes the CycloneDX software bill of materials of each package', help: <<<'TXT'
    Writes var/phil-dev/sbom/<vendor>-<name>.cdx.json for each package: the CycloneDX 1.6 bill of materials of
    what installing it brings, without its development dependencies, made by cyclonedx/cyclonedx-php-composer,
    installed in vendor-bin/sbom, and reproducible from one run to the next.

    A package with a composer.lock is read from it. A member of a monorepo has none: its composer.json is
    copied under var/phil-dev/sbom/work/, with a path repository on each glob of the root's packages, and its
    dependencies are resolved without being installed. That needs the network: it runs as its own script,
    before each release.
    TXT)]
final class SbomCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this->addArgument('packages', InputArgument::IS_ARRAY, 'The packages, by directory or Composer name; all by default');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();
        $root = $workspace->root;
        /** @var list<string> $names */
        $names = $input->getArgument('packages');
        $directory = $root->path('var/phil-dev/sbom');
        $status = self::SUCCESS;

        if (!is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        foreach ($workspace->targets($names) as $package) {
            $slug = str_replace('/', '-', $package->name);
            $composer = $package->path('composer.json');

            if (!is_file($package->path('composer.lock'))) {
                $composer = $this->resolve($workspace, $package, $directory.'/work/'.$slug);

                if (null === $composer) {
                    $output->writeln(\sprintf('%s: its dependencies do not resolve.', $package->name));
                    $status = self::FAILURE;

                    continue;
                }
            }

            $file = $directory.'/'.$slug.'.cdx.json';
            $result = $this->runner->run([
                ...$this->composer(),
                '--working-dir='.$root->path('vendor-bin/sbom'),
                'CycloneDX:make-sbom',
                '--output-format=JSON',
                '--spec-version=1.6',
                '--output-reproducible',
                '--omit=dev',
                '--output-file='.$file,
                $composer,
            ], $root->dir);

            if ($result->successful()) {
                $output->writeln($file);
            } else {
                $status = self::FAILURE;
            }
        }

        return $status;
    }

    /**
     * Copies the package's `composer.json` to `$work`, with a path repository on each glob of a monorepo, and resolves
     * its lock file there without installing anything; returns the copy, or null when it does not resolve.
     */
    private function resolve(Workspace $workspace, Package $package, string $work): ?string
    {
        $composer = json_decode(Files::read($package->path('composer.json')), true, flags: \JSON_THROW_ON_ERROR);
        $composer = \is_array($composer) ? $composer : [];

        $repositories = \is_array($composer['repositories'] ?? null) ? $composer['repositories'] : [];

        foreach ($workspace->isMonorepo() ? $workspace->packageGlobs() : [] as $glob) {
            $repositories[] = ['type' => 'path', 'url' => $workspace->root->path($glob), 'options' => ['symlink' => false]];
        }

        $composer['repositories'] = $repositories;

        if (!is_dir($work)) {
            mkdir($work, recursive: true);
        }

        // A lock left by an earlier run would pin what the composer.json may have changed since.
        if (is_file($work.'/composer.lock')) {
            unlink($work.'/composer.lock');
        }

        file_put_contents($work.'/composer.json', json_encode($composer, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));

        $update = $this->runner->run([...$this->composer(), 'update', '--no-install', '--no-interaction', '--no-progress', '--no-plugins', '--no-scripts', '--quiet'], $work);

        return $update->successful() ? $work.'/composer.json' : null;
    }
}
