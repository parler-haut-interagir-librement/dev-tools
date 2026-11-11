<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Config;

use Phil\DevTools\Config\PhpUnitConfig;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Workspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpUnitConfig::class)]
final class PhpUnitConfigTest extends TestCase
{
    use TemporaryDirectory;

    private const string CONFIGURATION = <<<'XML_WRAP'
        <?xml version="1.0" encoding="UTF-8"?>
        <phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                 xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
                 bootstrap="tests/bootstrap.php"
                 cacheDirectory="var/phpunit"
                 colors="true">
            <php>
                <env name="COLUMNS" value="240"/>
            </php>
            <testsuites>
                <testsuite name="Unit">
                    <directory>tests</directory>
                    <directory>packages/*/tests/Unit</directory>
                    <file>/elsewhere/OneTest.php</file>
                </testsuite>
                <testsuite name="Integration">
                    <directory>packages/*/tests/Integration</directory>
                </testsuite>
            </testsuites>
            <source>
                <include>
                    <directory> packages/*/src </directory>
                </include>
            </source>
            <logging>
                <junit outputFile="var/junit.xml"/>
            </logging>
        </phpunit>
        XML_WRAP;

    public function testNothingWhenNoPackageIsSkipped(): void
    {
        $root = $this->monorepo();

        self::assertNull(new PhpUnitConfig(Workspace::load($root, null, false))->withoutSkipped());
        self::assertNull(new PhpUnitConfig(Workspace::load($root, ['active'], true))->withoutSkipped());
    }

    public function testNothingWithoutAConfiguration(): void
    {
        $root = $this->monorepo();
        unlink($root.'/phpunit.dist.xml');

        self::assertNull(new PhpUnitConfig(Workspace::load($root, null, true))->withoutSkipped());
    }

    public function testTheSkippedPackagesAreLeftOutAndEveryPathIsAbsolute(): void
    {
        $root = $this->monorepo();

        $xml = new PhpUnitConfig(Workspace::load($root, null, true))->withoutSkipped();

        self::assertSame(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="{$root}/vendor/phpunit/phpunit/phpunit.xsd" bootstrap="{$root}/tests/bootstrap.php" cacheDirectory="{$root}/var/phpunit" colors="true">
                <php>
                    <env name="COLUMNS" value="240"/>
                </php>
                <testsuites>
                    <testsuite name="Unit">
                        <directory>{$root}/tests</directory>
                        <directory>{$root}/packages/*/tests/Unit</directory>
                        <file>/elsewhere/OneTest.php</file>
                    <exclude>{$root}/packages/later</exclude><exclude>{$root}/packages/someday</exclude></testsuite>
                    <testsuite name="Integration">
                        <directory>{$root}/packages/*/tests/Integration</directory>
                    <exclude>{$root}/packages/later</exclude><exclude>{$root}/packages/someday</exclude></testsuite>
                </testsuites>
                <source>
                    <include>
                        <directory>{$root}/packages/*/src</directory>
                    </include>
                <exclude><directory>{$root}/packages/later</directory><directory>{$root}/packages/someday</directory></exclude></source>
                <logging>
                    <junit outputFile="{$root}/var/junit.xml"/>
                </logging>
            </phpunit>

            XML, $xml);
    }

    public function testAnExistingExclusionOfTheSourceIsKept(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/phpunit.xml', '<phpunit><source><include><directory>src</directory></include><exclude><file>src/Kernel.php</file></exclude></source></phpunit>');

        $xml = new PhpUnitConfig(Workspace::load($root, null, true))->withoutSkipped();

        self::assertSame(<<<XML
            <?xml version="1.0"?>
            <phpunit><source><include><directory>{$root}/src</directory></include><exclude><file>{$root}/src/Kernel.php</file><directory>{$root}/packages/later</directory><directory>{$root}/packages/someday</directory></exclude></source></phpunit>

            XML, $xml);
    }

    public function testAConfigurationThatIsNoXmlIsRefused(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/phpunit.xml', '<phpunit');

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains($root.'/phpunit.xml is not valid XML.');

        new PhpUnitConfig(Workspace::load($root, null, true))->withoutSkipped();
    }

    public function testTheDistributedFileAfterTheOthers(): void
    {
        $root = $this->monorepo();
        unlink($root.'/phpunit.dist.xml');
        $this->put($root.'/phpunit.xml.dist', '<phpunit bootstrap="/absolute/bootstrap.php"/>');

        self::assertSame("<?xml version=\"1.0\"?>\n<phpunit bootstrap=\"/absolute/bootstrap.php\"/>\n", new PhpUnitConfig(Workspace::load($root, null, true))->withoutSkipped());
        self::assertSame(['phpunit.xml', 'phpunit.dist.xml', 'phpunit.xml.dist'], PhpUnitConfig::FILES);
    }

    /**
     * A monorepo with one active member and two planned ones, and the PHPUnit configuration above.
     */
    private function monorepo(): string
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/phpunit.dist.xml', self::CONFIGURATION);

        foreach (['active', 'later', 'someday'] as $name) {
            $this->put($root.'/packages/'.$name.'/composer.json', '{"name": "phil/'.$name.'"}');
        }

        $this->put($root.'/packages/later/phil-dev.php', "<?php return ['status' => 'planned'];");
        $this->put($root.'/packages/someday/phil-dev.php', "<?php return ['status' => 'planned'];");

        return $root;
    }
}
