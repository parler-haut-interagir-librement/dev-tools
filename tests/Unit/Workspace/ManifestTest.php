<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Workspace;

use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Manifest;
use Phil\DevTools\Workspace\PackageStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Manifest::class)]
#[CoversClass(InvalidManifest::class)]
#[CoversClass(PackageStatus::class)]
final class ManifestTest extends TestCase
{
    use TemporaryDirectory;

    public function testAMissingFileIsAnEmptyManifest(): void
    {
        $manifest = Manifest::load($this->temporaryDirectory().'/phil-dev.php');

        self::assertSame([], $manifest->data);
        self::assertSame(['src', 'tests'], $manifest->strings('paths', null, ['src', 'tests']));
    }

    public function testItReadsTheSections(): void
    {
        $file = $this->temporaryDirectory().'/phil-dev.php';
        $this->put($file, "<?php return ['paths' => ['lib', 'src'], 'phpstan' => ['includes' => ['a.neon']], 'docs' => ['public' => true], 'deptrac' => ['ruleset' => ['A' => ['B'], 'C' => []]]];");

        $manifest = Manifest::load($file);

        self::assertSame(['lib', 'src'], $manifest->strings('paths'));
        self::assertSame(['a.neon'], $manifest->strings('phpstan', 'includes'));
        self::assertSame([], $manifest->strings('phpstan', 'excludePaths'));
        self::assertSame(['A' => ['B'], 'C' => []], $manifest->map('deptrac', 'ruleset'));
        self::assertSame([], $manifest->map('rector', 'skip'));
        self::assertTrue($manifest->flag('docs', 'public'));
        self::assertFalse($manifest->flag('docs', 'other'));
        self::assertTrue($manifest->flag('docs', 'other', true));
        self::assertNull($manifest->get('cs'));
        self::assertNull($manifest->get('paths', 'key'));
    }

    public function testAPackageIsActiveUnlessItsStatusSaysPlanned(): void
    {
        self::assertSame(PackageStatus::Active, new Manifest()->status());
        self::assertSame(PackageStatus::Active, new Manifest(['status' => 'active'])->status());
        self::assertSame(PackageStatus::Planned, new Manifest(['status' => 'planned'])->status());
    }

    public function testAnUnknownStatusIsRefused(): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('"status" is "active" or "planned", not "someday".');

        new Manifest(['status' => 'someday'])->status();
    }

    public function testAStatusThatIsNoStringIsRefused(): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('"status" is "active" or "planned".');

        new Manifest(['status' => ['planned']])->status();
    }

    public function testAFileThatReturnsNoArrayIsRefused(): void
    {
        $file = $this->temporaryDirectory().'/phil-dev.php';
        $this->put($file, '<?php return 1;');

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('must return an array.');

        Manifest::load($file);
    }

    public function testAListIsRefused(): void
    {
        $file = $this->temporaryDirectory().'/phil-dev.php';
        $this->put($file, "<?php return ['src'];");

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('keyed by section');

        Manifest::load($file);
    }

    public function testAnUnknownSectionIsRefused(): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('Unknown section "pathz"');

        new Manifest(['pathz' => []]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, callable(Manifest): mixed, string}>
     */
    public static function wrongValues(): iterable
    {
        yield 'paths not a list' => [['paths' => 'src'], static fn (Manifest $manifest): array => $manifest->strings('paths'), '"paths" must be a list of strings.'];
        yield 'paths holding a number' => [['paths' => [1]], static fn (Manifest $manifest): array => $manifest->strings('paths'), '"paths" must be a list of strings.'];
        yield 'includes not a list' => [['phpstan' => ['includes' => 'a']], static fn (Manifest $manifest): array => $manifest->strings('phpstan', 'includes'), '"phpstan.includes" must be a list of strings.'];
        yield 'includes holding a number' => [['phpstan' => ['includes' => [1]]], static fn (Manifest $manifest): array => $manifest->strings('phpstan', 'includes'), '"phpstan.includes" must be a list of strings.'];
        yield 'map not an array' => [['rector' => 'x'], static fn (Manifest $manifest): array => $manifest->map('rector'), '"rector" must be an array.'];
        yield 'nested map not an array' => [['rector' => ['skip' => 'x']], static fn (Manifest $manifest): array => $manifest->map('rector', 'skip'), '"rector.skip" must be an array.'];
        yield 'flag not a boolean' => [['docs' => ['public' => 'yes']], static fn (Manifest $manifest): bool => $manifest->flag('docs', 'public'), '"docs.public" must be a boolean.'];
    }

    /**
     * @param array<string, mixed>      $data
     * @param callable(Manifest): mixed $read
     */
    #[DataProvider('wrongValues')]
    public function testAWrongValueIsRefused(array $data, callable $read, string $message): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains($message);

        $read(new Manifest($data));
    }
}
