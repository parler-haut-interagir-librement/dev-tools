<?php

declare(strict_types=1);

namespace Tilttek\SampleBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class TilttekSampleBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('title')->defaultValue('Status')->end()
            ->end();
    }
}
