<?php

declare(strict_types=1);

namespace Phil\ExampleBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class Tree
{
    public function build(): TreeBuilder
    {
        $tree = new TreeBuilder('phil_sample');
        $tree->getRootNode()->children()->stringNode('locale')->defaultValue('en')->end();

        return $tree;
    }
}
