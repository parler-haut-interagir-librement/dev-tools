<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Conventions;

use Phil\DevTools\Conventions\ConfigurationOption;
use Phil\DevTools\Conventions\ConfigurationTreeReader;
use Phil\DevTools\Conventions\RecipeReader;
use Phil\DevTools\Conventions\TreeFrame;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigurationTreeReader::class)]
#[CoversClass(ConfigurationOption::class)]
#[CoversClass(TreeFrame::class)]
#[CoversClass(RecipeReader::class)]
final class ConfigurationTreeReaderTest extends TestCase
{
    public function testTheOptionsOfATreeAreRead(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            $root->children()
                ->scalarNode('a')->defaultValue('it''s')->end()
                ->scalarNode("b")->defaultValue("x	y")->end()
                ->integerNode('c')->defaultValue(-1)->end()
                ->booleanNode('d')->defaultFalse()->end()
                ->scalarNode('e')->defaultValue(NULL)->end()
                ->scalarNode('f')->defaultValue(true)->end()
                ->arrayNode('g')->children()->scalarNode('h')->defaultValue('i')->end()->end()->end()
                ->variableNode('v')->end()
                ->scalarNode('k')
            ;
            $other?->scalarNode('n')->defaultValue(PHP_EOL)->end();
            $tree->node('m', 'scalar')->children()->scalarNode('o')->end()->end();
            PHP_WRAP;

        $options = new ConfigurationTreeReader()->options($code);

        self::assertEquals([
            new ConfigurationOption('a', true, null, false),
            new ConfigurationOption('b', true, "x\ty", false),
            new ConfigurationOption('c', true, null, false),
            new ConfigurationOption('d', true, 'false', false),
            new ConfigurationOption('e', true, 'null', false),
            new ConfigurationOption('f', true, 'true', false),
            new ConfigurationOption('g.h', true, 'i', false),
            new ConfigurationOption('k', false, null, false),
            new ConfigurationOption('n', true, null, false),
            new ConfigurationOption('m.o', false, null, false),
        ], $options);
        self::assertSame('h', $options[6]->name());
        self::assertSame('o', $options[9]->name());
    }
}
