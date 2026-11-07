<?php

declare(strict_types=1);

namespace Tilttek\ExampleBundle\Tests\Unit;

use Tilttek\ExampleBundle\TilttekExampleBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

#[CoversClass(TilttekExampleBundle::class)]
final class TilttekExampleBundleTest extends TestCase
{
    public function testTheConfigurationIsEnabledByDefault(): void
    {
        self::assertTrue($this->load([])->getParameter('tilttek_example.enabled'));
    }

    public function testTheConfigurationCanBeDisabled(): void
    {
        self::assertFalse($this->load([['enabled' => false]])->getParameter('tilttek_example.enabled'));
    }

    public function testTheAlias(): void
    {
        self::assertSame('tilttek_example', $this->extension()->getAlias());
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new TilttekExampleBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
