<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Docs;

use Phil\DevTools\DevTools;
use Phil\DevTools\Docs\FrenchDictionary;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FrenchDictionary::class)]
final class FrenchDictionaryTest extends TestCase
{
    use TemporaryDirectory;

    public function testThisPackagePinsAnExactVersion(): void
    {
        $manifest = json_decode((string) file_get_contents(DevTools::directory().'/package.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertIsArray($manifest);
        self::assertIsArray($manifest['devDependencies']);
        self::assertSame($manifest['devDependencies']['@cspell/dict-fr-fr'], new FrenchDictionary()->version());
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', new FrenchDictionary()->version());
    }

    public function testTheVersionOfAnotherManifest(): void
    {
        $manifest = $this->temporaryDirectory().'/package.json';
        $this->put($manifest, '{"devDependencies": {"@cspell/dict-fr-fr": "12.30.4"}}');

        self::assertSame('12.30.4', new FrenchDictionary($manifest)->version());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidManifests(): iterable
    {
        yield 'not JSON' => ['{'];
        yield 'no devDependencies' => ['{"dependencies": {"@cspell/dict-fr-fr": "2.3.2"}}'];
        yield 'devDependencies that are not a map' => ['{"devDependencies": "@cspell/dict-fr-fr"}'];
        yield 'no dictionary' => ['{"devDependencies": {"cspell": "10.0.0"}}'];
        yield 'a range' => ['{"devDependencies": {"@cspell/dict-fr-fr": "^2.3.2"}}'];
        yield 'a version with a suffix' => ['{"devDependencies": {"@cspell/dict-fr-fr": "2.3.2-beta"}}'];
        yield 'a version with a prefix' => ['{"devDependencies": {"@cspell/dict-fr-fr": "v2.3.2"}}'];
        yield 'a number' => ['{"devDependencies": {"@cspell/dict-fr-fr": 2}}'];
    }

    #[DataProvider('invalidManifests')]
    public function testAnInvalidManifest(string $content): void
    {
        $manifest = $this->temporaryDirectory().'/package.json';
        $this->put($manifest, $content);

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains($manifest.' pins @cspell/dict-fr-fr at an exact version in its devDependencies.');

        new FrenchDictionary($manifest)->version();
    }

    public function testTheFirstDirectoryThatHoldsIt(): void
    {
        $first = $this->temporaryDirectory();
        $second = $this->temporaryDirectory();
        $third = $this->temporaryDirectory();
        $this->put($second.'/node_modules/@cspell/dict-fr-fr/cspell-ext.json', '{}');
        $this->put($third.'/node_modules/@cspell/dict-fr-fr/cspell-ext.json', '{}');
        mkdir($first.'/node_modules/@cspell/dict-fr-fr/cspell-ext.json', recursive: true);

        self::assertSame($second.'/node_modules/@cspell/dict-fr-fr/cspell-ext.json', new FrenchDictionary()->find([$first, $second.'/', $third]));
        self::assertNull(new FrenchDictionary()->find([$first]));
        self::assertNull(new FrenchDictionary()->find([]));
    }

    public function testTheInstallCommand(): void
    {
        $manifest = $this->temporaryDirectory().'/package.json';
        $this->put($manifest, '{"devDependencies": {"@cspell/dict-fr-fr": "2.3.2"}}');

        self::assertSame(
            ['/bin/npm', 'install', '--prefix', '/root/var/phil-dev/node', '--no-save', '--no-package-lock', '--ignore-scripts', '--no-audit', '--no-fund', '@cspell/dict-fr-fr@2.3.2'],
            new FrenchDictionary($manifest)->installCommand('/bin/npm', '/root/var/phil-dev/node'),
        );
    }
}
