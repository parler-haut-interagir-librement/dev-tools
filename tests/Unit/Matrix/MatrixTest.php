<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Matrix;

use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Matrix\Matrix;
use Phil\DevTools\Matrix\Version;
use Phil\DevTools\Matrix\VersionStatus;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Matrix::class)]
#[CoversClass(Version::class)]
#[CoversClass(VersionStatus::class)]
final class MatrixTest extends TestCase
{
    use TemporaryDirectory;

    public function testTheMatrixOfThisPackage(): void
    {
        $matrix = Matrix::load();

        self::assertSame(VersionStatus::Floor, $matrix->floor('php')->status);
        self::assertSame(VersionStatus::Floor, $matrix->floor('symfony')->status);
        self::assertNull($matrix->floor('symfony')->image);
    }

    public function testAMatrix(): void
    {
        $matrix = Matrix::load($this->matrix([
            'php' => [['version' => '8.6', 'status' => 'next', 'image' => 'php:8.6-rc-cli'], ['version' => '8.5', 'status' => 'floor', 'image' => 'php:8.5-cli']],
            'symfony' => [['version' => '8.2', 'status' => 'floor'], ['version' => '8.3', 'status' => 'next']],
        ]));

        self::assertSame(['8.6', '8.5'], array_map(static fn (Version $version): string => $version->version, $matrix->versions('php')));
        self::assertSame([], $matrix->versions('node'));
        self::assertSame('8.5', $matrix->floor('php')->version);
        self::assertSame('php:8.6-rc-cli', $matrix->find('php', 'next')->image);
        self::assertSame('php:8.5-cli', $matrix->find('php', '8.5')->image);
        self::assertSame('8.3', $matrix->find('symfony', 'next')->version);
        self::assertSame(['min' => 80500, 'max' => 80699], $matrix->phpVersionRange());
        self::assertSame(80600, $matrix->find('php', 'next')->id());
        self::assertSame(100_200, new Version('10.2', VersionStatus::Floor)->id());
    }

    public function testTheFloorAlone(): void
    {
        $matrix = Matrix::load($this->matrix([
            'php' => [['version' => '8.5', 'status' => 'floor', 'image' => 'php:8.5-cli']],
            'symfony' => [['version' => '8.2', 'status' => 'floor']],
        ]));

        self::assertSame(['min' => 80500, 'max' => 80599], $matrix->phpVersionRange());
    }

    public function testAnUnknownVersion(): void
    {
        $matrix = Matrix::load();

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('The matrix has no symfony version "7.4".');

        $matrix->find('symfony', '7.4');
    }

    public function testInvalidJson(): void
    {
        $file = $this->temporaryDirectory().'/matrix.json';
        $this->put($file, '{');

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('is not valid JSON');

        Matrix::load($file);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function wrongMatrices(): iterable
    {
        $php = [['version' => '8.5', 'status' => 'floor', 'image' => 'php:8.5-cli']];
        $symfony = [['version' => '8.2', 'status' => 'floor']];

        yield 'a list' => [[1], 'lists no php versions.'];
        yield 'no symfony' => [['php' => $php], 'lists no symfony versions.'];
        yield 'no floor' => [['php' => [['version' => '8.6', 'status' => 'next', 'image' => 'x']], 'symfony' => $symfony], 'must list one floor php version, and at most one next.'];
        yield 'two floors' => [['php' => [...$php, ...$php], 'symfony' => $symfony], 'must list one floor php version'];
        yield 'two next' => [['php' => [...$php, ['version' => '8.6', 'status' => 'next', 'image' => 'x'], ['version' => '8.7', 'status' => 'next', 'image' => 'y']], 'symfony' => $symfony], 'and at most one next.'];
        yield 'a PHP without image' => [['php' => [['version' => '8.5', 'status' => 'floor']], 'symfony' => $symfony], 'and an "image"'];
        yield 'a Symfony with an image' => [['php' => $php, 'symfony' => [['version' => '8.2', 'status' => 'floor', 'image' => 'x']]], 'each symfony version has a "version" such as 8.5 and a "status", floor or next.'];
        yield 'a version of three numbers' => [['php' => [['version' => '8.5.1', 'status' => 'floor', 'image' => 'x']], 'symfony' => $symfony], 'each php version'];
        yield 'a version with text' => [['php' => [['version' => 'x8.5', 'status' => 'floor', 'image' => 'x']], 'symfony' => $symfony], 'each php version'];
        yield 'no status' => [['php' => [['version' => '8.5', 'image' => 'x']], 'symfony' => $symfony], 'each php version'];
        yield 'an unknown status' => [['php' => [['version' => '8.5', 'status' => 'old', 'image' => 'x']], 'symfony' => $symfony], 'each php version'];
        yield 'an entry that is no object' => [['php' => ['8.5'], 'symfony' => $symfony], 'each php version'];
        yield 'a version that is no string' => [['php' => [['version' => 8.5, 'status' => 'floor', 'image' => 'x']], 'symfony' => $symfony], 'each php version'];
        yield 'an image that is no string' => [['php' => [['version' => '8.5', 'status' => 'floor', 'image' => 3]], 'symfony' => $symfony], 'each php version'];
    }

    #[DataProvider('wrongMatrices')]
    public function testAWrongMatrixIsRefused(mixed $data, string $message): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains($message);

        Matrix::load($this->matrix($data));
    }

    private function matrix(mixed $data): string
    {
        $file = $this->temporaryDirectory().'/matrix.json';
        $this->put($file, json_encode($data, \JSON_THROW_ON_ERROR));

        return $file;
    }
}
