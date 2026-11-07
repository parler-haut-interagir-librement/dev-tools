<?php

declare(strict_types=1);

namespace {{namespace}}\Tests\Unit;

use {{namespace}}\{{bundle_class}};
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

#[CoversClass({{bundle_class}}::class)]
final class {{bundle_class}}Test extends TestCase
{
    public function testTheConfigurationIsEnabledByDefault(): void
    {
        self::assertTrue($this->load([])->getParameter('{{alias}}.enabled'));
    }

    public function testTheConfigurationCanBeDisabled(): void
    {
        self::assertFalse($this->load([['enabled' => false]])->getParameter('{{alias}}.enabled'));
    }

    public function testTheAlias(): void
    {
        self::assertSame('{{alias}}', $this->extension()->getAlias());
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
        $extension = new {{bundle_class}}()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
