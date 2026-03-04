<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ibexa_theme_translations');

        $treeBuilder->getRootNode()
            ->children()
            ->scalarNode('cache_dir')
            ->defaultValue('%kernel.cache_dir%/theme_translations')
            ->end()
            ->scalarNode('default_language')
            ->defaultValue('eng-GB')
            ->end()
            ->arrayNode('redis')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')
            ->defaultTrue()
            ->end()
            ->scalarNode('prefix')
            ->defaultValue('theme_trans')
            ->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
