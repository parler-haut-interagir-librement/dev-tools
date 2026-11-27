<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Conventions;

use Phil\DevTools\Conventions\DoctrineMapping;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\PackageRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineMapping::class)]
final class DoctrineMappingTest extends TestCase
{
    use TemporaryDirectory;

    public function testTheMappingFilesOfAPackage(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', '{"name": "phil/x"}');

        foreach ([
            'config/doctrine/Slug.orm.xml',
            'config/doctrine/Slug.orm.yml',
            'src/Resources/config/doctrine/Money.orm.yaml',
            'src/Resources/config/doctrine/Old.dcm.xml',
            'config/services.yaml',
            'config/doctrine.xml',
            'vendor/acme/x/config/Y.orm.xml',
            'var/cache/Z.orm.xml',
            '.github/A.orm.xml',
        ] as $file) {
            $this->put($dir.'/'.$file, '');
        }

        self::assertSame([
            'config/doctrine/Slug.orm.xml',
            'config/doctrine/Slug.orm.yml',
            'src/Resources/config/doctrine/Money.orm.yaml',
            'src/Resources/config/doctrine/Old.dcm.xml',
        ], new DoctrineMapping()->files(Package::fromDirectory($dir, '', PackageRole::Root)));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function names(): iterable
    {
        yield 'an XML mapping' => ['a/Entity.orm.xml', true];
        yield 'a YAML mapping' => ['Entity.orm.yml', true];
        yield 'a YAML mapping, long ending' => ['Entity.orm.yaml', true];
        yield 'an older XML mapping' => ['Entity.dcm.xml', true];
        yield 'whatever the case' => ['Entity.ORM.XML', true];
        yield 'the ending alone' => ['.orm.xml', false];
        yield 'in the directory name' => ['x.orm.xml/Entity.php', false];
        yield 'an ODM mapping' => ['Entity.mongodb.xml', false];
        yield 'a plain XML file' => ['orm.xml', false];
    }

    #[DataProvider('names')]
    public function testWhatIsAMappingFile(string $file, bool $expected): void
    {
        self::assertSame($expected, DoctrineMapping::isMapping($file));
    }
}
