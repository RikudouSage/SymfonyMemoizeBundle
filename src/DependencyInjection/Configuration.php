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
                ->arrayNode('memoize_services')
                    ->info('List additional services that you want to be memoized.')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('service_id')
                                ->info('The service ID.')
                            ->end()
                            ->scalarNode('memoize_seconds')
                                ->info('The seconds to memoize the service for. Defaults to null which means the value of default_memoize_seconds.')
                                ->defaultNull()
                            ->end()
                            ->arrayNode('methods')
                                ->info('List of methods to memoize. Leave empty to memoize all of them.')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('name')
                                            ->info('The method name.')
                                        ->end()
                                        ->scalarNode('memoize_seconds')
                                            ->info('The seconds to memoize the method for. Defaults to null which means the value of memoize_seconds from the parent service.')
                                            ->defaultNull()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
