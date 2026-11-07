<?php

declare(strict_types=1);

namespace Tilttek\ExampleBundle;

use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * An example bundle.
 *
 * ```yaml
 * tilttek_example:
 *     enabled: true
 * ```
 */
final class TilttekExampleBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_example';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('enabled')
                    ->info("Registers the bundle's services.")
                    ->defaultTrue()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('tilttek_example.enabled', true === $config['enabled']);
    }
}
