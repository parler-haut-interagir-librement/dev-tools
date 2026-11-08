<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\SbomCommand;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SbomCommand::class)]
final class SbomCommandTest extends CommandTestCase
{
    private const string OPTIONS = ' CycloneDX:make-sbom --output-format=JSON --spec-version=1.6 --output-reproducible --omit=dev --output-file=';

    public function testAPackageWithItsLockFile(): void
    {
        $root = $this->package();
        $this->put($root.'/composer.lock', '{}');
        $tester = $this->tester($root, 'sbom');

        self::assertSame(0, $this->withEnvironment('COMPOSER_BINARY', '', static fn (): int => $tester->execute([])));
        self::assertSame(['composer --working-dir='.$root.'/vendor-bin/sbom'.self::OPTIONS.$root.'/var/phil-dev/sbom/phil-example.cdx.json '.$root.'/composer.json'], $this->runner->commands());
        self::assertSame($root, $this->runner->runs[0]['directory']);
        self::assertSame($root."/var/phil-dev/sbom/phil-example.cdx.json\n", $tester->getDisplay());
        self::assertDirectoryExists($root.'/var/phil-dev/sbom');
    }

    public function testAMemberIsResolvedInACopy(): void
    {
        $root = $this->fixture('monorepo');
        $work = $root.'/var/phil-dev/sbom/work/phil-slug';
        $this->put($work.'/composer.lock', '{"stale": true}');
        $tester = $this->tester($root, 'sbom', [new ProcessResult(0), new ProcessResult(4)]);

        self::assertSame(1, $this->withEnvironment('COMPOSER_BINARY', '/opt/composer.phar', static fn (): int => $tester->execute(['packages' => ['slug']])));
        self::assertSame([
            \PHP_BINARY.' /opt/composer.phar update --no-install --no-interaction --no-progress --no-plugins --no-scripts --quiet',
            \PHP_BINARY.' /opt/composer.phar --working-dir='.$root.'/vendor-bin/sbom'.self::OPTIONS.$root.'/var/phil-dev/sbom/phil-slug.cdx.json '.$work.'/composer.json',
        ], $this->runner->commands());
        self::assertSame([$work, $root], array_column($this->runner->runs, 'directory'));
        self::assertFileDoesNotExist($work.'/composer.lock');
        self::assertSame('', $tester->getDisplay());

        $raw = (string) file_get_contents($work.'/composer.json');
        self::assertStringStartsWith("{\n    \"name\": \"phil/slug\",", $raw);
        self::assertStringContainsString('"url": "'.$root.'/packages/*",', $raw);
        $copy = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($copy);
        self::assertSame('phil/slug', $copy['name']);
        self::assertSame([['type' => 'path', 'url' => $root.'/packages/*', 'options' => ['symlink' => false]]], $copy['repositories']);
    }

    public function testAMemberThatDoesNotResolve(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/slug/composer.json', '{"name": "phil/slug", "repositories": [{"type": "vcs", "url": "https://example.org/x.git"}]}');
        $tester = $this->tester($root, 'sbom', [new ProcessResult(1), new ProcessResult(0), new ProcessResult(0)]);

        self::assertSame(1, $tester->execute(['packages' => ['slug', 'tilttek-dice']]));
        self::assertStringStartsWith("phil/slug: its dependencies do not resolve.\n", $tester->getDisplay());
        self::assertCount(3, $this->runner->runs);

        $copy = json_decode((string) file_get_contents($root.'/var/phil-dev/sbom/work/phil-slug/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($copy);
        self::assertSame([['type' => 'vcs', 'url' => 'https://example.org/x.git'], ['type' => 'path', 'url' => $root.'/packages/*', 'options' => ['symlink' => false]]], $copy['repositories']);
    }
}
