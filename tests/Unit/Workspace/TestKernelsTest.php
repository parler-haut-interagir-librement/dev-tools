<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Workspace;

use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\PackageRole;
use Phil\DevTools\Workspace\TestKernels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TestKernels::class)]
final class TestKernelsTest extends TestCase
{
    use TemporaryDirectory;

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function sources(): iterable
    {
        yield 'a kernel of Symfony' => ["<?php\nnamespace Vendor\\Bundle\\Tests\\Functional\\App;\nuse Symfony\\Component\\HttpKernel\\Kernel;\nfinal class TestKernel extends Kernel {}", 'Vendor\\Bundle\\Tests\\Functional\\App\\TestKernel'];
        yield 'an alias' => ["<?php\nnamespace App;\nuse Symfony\\Component\\HttpKernel\\Kernel as BaseKernel;\nclass AppKernel extends BaseKernel {}", 'App\\AppKernel'];
        yield 'a qualified parent' => ["<?php\nnamespace App;\nreadonly final class Other extends \\Symfony\\Component\\HttpKernel\\Kernel {}", 'App\\Other'];
        yield 'another test kernel' => ["<?php\nnamespace App;\nfinal class AssetsKernel extends TestKernel {}", 'App\\AssetsKernel'];
        yield 'no namespace' => ["<?php\nfinal class TestKernel extends Kernel {}", 'TestKernel'];
        yield 'after a class constant' => ["<?php\nnamespace App;\n\$a = Foo::class;\nfinal class TestKernel extends Kernel {}", 'App\\TestKernel'];
        yield 'an abstract kernel' => ["<?php\nnamespace App;\nabstract class BaseKernel extends Kernel {}", null];
        yield 'an abstract readonly kernel' => ["<?php\nnamespace App;\nabstract readonly class BaseKernel extends Kernel {}", null];
        yield 'an anonymous kernel' => ["<?php\nnamespace App;\n\$kernel = new class('test', true) extends Kernel {};", null];
        yield 'a parent that is not a kernel' => ["<?php\nnamespace App;\nfinal class KernelTest extends TestCase {}", null];
        yield 'no parent' => ["<?php\nnamespace App;\nfinal class TestKernel {}", null];
        yield 'an interface' => ["<?php\nnamespace App;\ninterface TestKernel extends Kernel {}", null];
        yield 'a namespace keyword alone' => ['<?php namespace', null];
        yield 'a class keyword alone' => ['<?php final class', null];
        yield 'a class without its parent' => ['<?php final class TestKernel extends', null];
    }

    #[DataProvider('sources')]
    public function testTheDeclaredKernel(string $code, ?string $expected): void
    {
        self::assertSame($expected, TestKernels::declared($code));
    }

    public function testTheFirstKernelOfAFile(): void
    {
        self::assertSame('App\\First', TestKernels::declared("<?php\nnamespace App;\nfinal class First extends Kernel {}\nfinal class Second extends Kernel {}"));
    }

    public function testTheKernelsUnderTestsFunctional(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', '{"name": "phil/example-bundle", "type": "symfony-bundle"}');
        $this->put($dir.'/tests/Functional/App/TestKernel.php', "<?php\nnamespace A;\nfinal class TestKernel extends Kernel {}");
        $this->put($dir.'/tests/Functional/Admin/AdminKernel.php', "<?php\nnamespace B;\nfinal class AdminKernel extends Kernel {}");
        $this->put($dir.'/tests/Functional/App/config/kernel.php.txt', "<?php\nfinal class Text extends Kernel {}");
        $this->put($dir.'/tests/Functional/PageTest.php', "<?php\nnamespace A;\nfinal class PageTest extends WebTestCase {}");
        $this->put($dir.'/tests/Unit/UnitKernel.php', "<?php\nnamespace C;\nfinal class UnitKernel extends Kernel {}");

        self::assertSame(
            ['B\\AdminKernel' => $dir.'/tests/Functional/Admin/AdminKernel.php', 'A\\TestKernel' => $dir.'/tests/Functional/App/TestKernel.php'],
            TestKernels::of(Package::fromDirectory($dir, '', PackageRole::Root)),
        );
    }

    public function testAPackageWithoutFunctionalTests(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/composer.json', '{"name": "phil/example"}');

        self::assertSame([], TestKernels::of(Package::fromDirectory($dir, '', PackageRole::Root)));
    }
}
