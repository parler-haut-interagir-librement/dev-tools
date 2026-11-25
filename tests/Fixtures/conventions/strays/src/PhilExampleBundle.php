<?php

declare(strict_types=1);

namespace Phil\ExampleBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class PhilExampleBundle extends AbstractBundle
{
    protected string $extensionAlias = 'phil_sample';

    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();
        $children
            ->scalarNode('title')->info('The title.')->defaultValue('Status')->end()
            ->integerNode('limit')->defaultValue(10)->end()
            ->booleanNode('enabled')->defaultTrue()->end()
            ->scalarNode('connection')->defaultNull()->end()
            ->scalarNode('host')->info('No default.')->end()
            ->scalarNode('token')->isRequired()->end()
            ->scalarNode('template')->defaultValue(self::class)->end()
            ->arrayNode('mail')
                ->canBeEnabled()
                ->validate()->ifTrue(static function (array $value): bool {
                    $ok = [] === $value;

                    return !$ok;
                })->thenInvalid('Invalid.')->end()
                ->children()
                    ->scalarNode('sender')->defaultValue("no-reply@example.com")->end()
                    ->floatNode('ratio')->defaultValue(0.5)->end()
                ->end()
            ->end()
            ->arrayNode('limiters')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('policy')->end()
                    ->end()
                ->end()
            ->end()
            ->scalarNode($name)->end()
            ->enumNode('mode')->values(['a', 'b'])->defaultValue('a')->end();
    }
}
