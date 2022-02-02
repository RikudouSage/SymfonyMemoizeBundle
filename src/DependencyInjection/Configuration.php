<?php

namespace Rikudou\MemoizeBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('rikudou_memoize');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('enabled')
                    ->info('Whether memoization is enabled or not.')
                    ->defaultValue(true)
                ->end()
                ->integerNode('default_memoize_seconds')
                    ->info('The default memoization period if none is specified in attribute. -1 means until end of request.')
                    ->defaultValue(-1)
                ->end()
                ->scalarNode('cache_service')
                    ->info('The default cache service to use. If default_memoize_seconds is set to -1 this setting is ignored and internal service is used.')
                    ->defaultValue('cache.app')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
