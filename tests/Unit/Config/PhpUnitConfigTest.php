<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Config;

use Phil\DevTools\Config\PhpUnitConfig;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Workspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testNothingWhenNoPackageIsSkippedAndNoPathIsAGlob(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/phpunit.xml', '<phpunit><testsuites><testsuite name="Unit"><directory>tests</directory><directory>packages/**/tests</directory></testsuite></testsuites></phpunit>');

        self::assertNull(new PhpUnitConfig(Workspace::load($root, null, false))->generated());
        self::assertNull(new PhpUnitConfig(Workspace::load($root, ['active'], true))->generated());
    }

    public function testTheGlobsAreExpandedWhenNoPackageIsSkipped(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/phpunit.xml', '<phpunit><testsuites><testsuite name="Unit"><directory suffix="Test.php">packages/*/tests/Unit</directory><file>packages/*/tests/Smoke?Test.php</file><directory>packages/[a]*/tests/Missing</directory><exclude>packages/l*/tests/Unit</exclude></testsuite></testsuites></phpunit>');
        $this->put($root.'/packages/active/tests/SmokeOTest.php', '<?php');
        $this->put($root.'/packages/later/tests/Unit/SmokeTest.php', '<?php');

        self::assertSame(<<<XML
            <?xml version="1.0"?>
            <phpunit><testsuites><testsuite name="Unit"><directory suffix="Test.php">{$root}/packages/active/tests/Unit</directory><directory suffix="Test.php">{$root}/packages/later/tests/Unit</directory><directory suffix="Test.php">{$root}/packages/someday/tests/Unit</directory><file>{$root}/packages/active/tests/SmokeOTest.php</file><exclude>{$root}/packages/later/tests/Unit</exclude></testsuite></testsuites></phpunit>

            XML, new PhpUnitConfig(Workspace::load($root, null, false))->generated());
    }

    public function testAnExclusionOfTheSourceIsNotAddedWhenNoPackageIsSkipped(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/phpunit.xml', '<phpunit><testsuites><testsuite name="Unit"><directory>packages/*/tests/Unit</directory></testsuite></testsuites><source><include><directory>src</directory></include></source></phpunit>');

        self::assertSame(<<<XML
            <?xml version="1.0"?>
            <phpunit><testsuites><testsuite name="Unit"><directory>{$root}/packages/active/tests/Unit</directory><directory>{$root}/packages/later/tests/Unit</directory><directory>{$root}/packages/someday/tests/Unit</directory></testsuite></testsuites><source><include><directory>{$root}/src</directory></include></source></phpunit>

            XML, new PhpUnitConfig(Workspace::load($root, null, false))->generated());
    }

    public function testOnlyThePathElementsAreExpandedAndTrimmed(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/phpunit.xml', '<phpunit><note>packages/*/tests</note><testsuites><testsuite name="Unit"><directory> tests </directory></testsuite></testsuites></phpunit>');

        self::assertSame(<<<XML
            <?xml version="1.0"?>
            <phpunit><note>packages/*/tests</note><testsuites><testsuite name="Unit"><directory>{$root}/tests</directory><exclude>{$root}/packages/later</exclude><exclude>{$root}/packages/someday</exclude></testsuite></testsuites></phpunit>

            XML, new PhpUnitConfig(Workspace::load($root, null, true))->generated());
    }

    public function testAMatchIsSkippedOnlyInsideTheDirectoryOfASkippedPackage(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/packages/laterally/composer.json', '{"name": "phil/laterally"}');
        $this->put($root.'/phpunit.xml', '<phpunit><testsuites><testsuite name="Unit"><directory>packages/*</directory></testsuite></testsuites></phpunit>');

        $xml = (string) new PhpUnitConfig(Workspace::load($root, null, true))->generated();

        self::assertStringContainsString('<directory>'.$root.'/packages/active</directory><directory>'.$root.'/packages/laterally</directory><exclude>', $xml);
        self::assertStringNotContainsString('<directory>'.$root.'/packages/later</directory>', $xml);
        self::assertStringNotContainsString('<directory>'.$root.'/packages/someday</directory>', $xml);
    }

    public function testNothingWithoutAConfiguration(): void
    {
        $root = $this->monorepo();
        unlink($root.'/phpunit.dist.xml');

        self::assertNull(new PhpUnitConfig(Workspace::load($root, null, true))->generated());
    }

    public function testTheSkippedPackagesAreLeftOutAndEveryPathIsAbsolute(): void
    {
        $root = $this->monorepo();

        $xml = new PhpUnitConfig(Workspace::load($root, null, true))->generated();

        self::assertSame(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="../../../vendor/phpunit/phpunit/phpunit.xsd" bootstrap="{$root}/tests/bootstrap.php" cacheDirectory="{$root}/var/phpunit" colors="true">
                <php>
                    <env name="COLUMNS" value="240"/>
                </php>
                <testsuites>
                    <testsuite name="Unit">
                        <directory>{$root}/tests</directory>
                        <directory>{$root}/packages/active/tests/Unit</directory>
                        <file>/elsewhere/OneTest.php</file>
                    <exclude>{$root}/packages/later</exclude><exclude>{$root}/packages/someday</exclude></testsuite>
                    <testsuite name="Integration">
                        <directory>{$root}/packages/active/tests/Integration</directory>
                    <exclude>{$root}/packages/later</exclude><exclude>{$root}/packages/someday</exclude></testsuite>
                </testsuites>
                <source>
                    <include>
                        <directory>{$root}/packages/active/src</directory>
                    </include>
                <exclude><directory>{$root}/packages/later</directory><directory>{$root}/packages/someday</directory></exclude></source>
                <logging>
                    <junit outputFile="{$root}/var/junit.xml"/>
                </logging>
            </phpunit>

            XML, $xml);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function schemas(): iterable
    {
        yield 'relative to the root' => ['vendor/phpunit/phpunit/phpunit.xsd', '../../../vendor/phpunit/phpunit/phpunit.xsd'];
        yield 'dotted' => ['./vendor/../vendor/phpunit/phpunit.xsd', '../../../vendor/phpunit/phpunit.xsd'];
        yield 'inside var' => ['var/phil-dev/schema.xsd', '../schema.xsd'];
        yield 'beside the generated file' => ['var/phil-dev/phpunit/phpunit.xsd', 'phpunit.xsd'];
        yield 'a parent of the generated file' => ['var', '../../'];
        yield 'outside the root' => ['../phpunit.xsd', '../../../../phpunit.xsd'];
        yield 'a URL' => ['https://schema.phpunit.de/13.0/phpunit.xsd', 'https://schema.phpunit.de/13.0/phpunit.xsd'];
    }

    /**
     * Infection resolves a schema path that is not a URL against the directory of the configuration, even an
     * absolute one ("Invalid schema path found"): the path stays relative to the generated file.
     */
    #[DataProvider('schemas')]
    public function testTheSchemaIsReachedFromTheGeneratedFile(string $location, string $expected): void
    {
        $root = $this->monorepo();
        $this->put($root.'/phpunit.dist.xml', '<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="'.$location.'"><source><include><directory>packages/*/src</directory></include></source></phpunit>');

        $xml = (string) new PhpUnitConfig(Workspace::load($root, null, false))->generated();

        self::assertStringContainsString('xsi:noNamespaceSchemaLocation="'.$expected.'"', $xml);
    }

    public function testTheSchemaOfTheGeneratedFileExistsFromItsDirectory(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/vendor/phpunit/phpunit/phpunit.xsd', '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"/>');
        $this->put($root.'/phpunit.dist.xml', self::CONFIGURATION);
        $xml = (string) new PhpUnitConfig(Workspace::load($root, null, false))->generated();
        $this->put($root.'/'.PhpUnitConfig::GENERATED, $xml);

        self::assertSame(1, preg_match('~noNamespaceSchemaLocation="([^"]+)"~', $xml, $match));
        self::assertFileExists(\dirname($root.'/'.PhpUnitConfig::GENERATED).'/'.$match[1]);
    }

    public function testAnExistingExclusionOfTheSourceIsKept(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/phpunit.xml', '<phpunit><source><include><directory>src</directory></include><exclude><file>src/Kernel.php</file></exclude></source></phpunit>');

        $xml = new PhpUnitConfig(Workspace::load($root, null, true))->generated();

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

        new PhpUnitConfig(Workspace::load($root, null, true))->generated();
    }

    public function testTheDistributedFileAfterTheOthers(): void
    {
        $root = $this->monorepo();
        unlink($root.'/phpunit.dist.xml');
        $this->put($root.'/phpunit.xml.dist', '<phpunit bootstrap="/absolute/bootstrap.php"/>');

        self::assertSame("<?xml version=\"1.0\"?>\n<phpunit bootstrap=\"/absolute/bootstrap.php\"/>\n", new PhpUnitConfig(Workspace::load($root, null, true))->generated());
        self::assertSame(['phpunit.xml', 'phpunit.dist.xml', 'phpunit.xml.dist'], PhpUnitConfig::FILES);
    }

    /**
     * A monorepo with one active member and two planned ones, and the PHPUnit configuration above.
     */
    public function testNarrowedTheMembersNotAnalysedAreLeftOut(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/phpunit.xml', '<phpunit><testsuites><testsuite name="Unit"><directory>tests</directory><directory>packages/*/tests/Unit</directory></testsuite></testsuites><source><include><directory>packages/*/src</directory></include></source></phpunit>');

        self::assertSame(<<<XML
            <?xml version="1.0"?>
            <phpunit><testsuites><testsuite name="Unit"><directory>{$root}/tests</directory><directory>{$root}/packages/later/tests/Unit</directory><exclude>{$root}/packages/active</exclude><exclude>{$root}/packages/someday</exclude></testsuite></testsuites><source><include><directory>{$root}/packages/later/src</directory></include><exclude><directory>{$root}/packages/active</directory><directory>{$root}/packages/someday</directory></exclude></source></phpunit>

            XML, new PhpUnitConfig(Workspace::load($root, ['later'], false), narrowed: true)->generated());
    }

    public function testNarrowedWithEveryMemberAnalysedOnlyTheGlobsChange(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/phpunit.xml', '<phpunit><testsuites><testsuite name="Unit"><directory>tests</directory></testsuite></testsuites></phpunit>');

        self::assertNull(new PhpUnitConfig(Workspace::load($root, null, false), narrowed: true)->generated());
    }

    public function testNotNarrowedTheMembersNotAnalysedStay(): void
    {
        $root = $this->monorepo();
        $this->put($root.'/phpunit.xml', '<phpunit><testsuites><testsuite name="Unit"><directory>tests</directory></testsuite></testsuites></phpunit>');

        self::assertNull(new PhpUnitConfig(Workspace::load($root, ['later'], false))->generated());
    }

    private function monorepo(): string
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/phpunit.dist.xml', self::CONFIGURATION);

        foreach (['active', 'later', 'someday'] as $name) {
            $this->put($root.'/packages/'.$name.'/composer.json', '{"name": "phil/'.$name.'"}');
            $this->put($root.'/packages/'.$name.'/src/.keep', '');
            $this->put($root.'/packages/'.$name.'/tests/Unit/.keep', '');
        }

        $this->put($root.'/packages/active/tests/Integration/.keep', '');
        $this->put($root.'/packages/later/phil-dev.php', "<?php return ['status' => 'planned'];");
        $this->put($root.'/packages/someday/phil-dev.php', "<?php return ['status' => 'planned'];");

        return $root;
    }
}
