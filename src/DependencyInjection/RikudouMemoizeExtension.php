<?php

namespace Rikudou\MemoizeBundle\DependencyInjection;

use Rikudou\MemoizeBundle\Attribute\Memoizable;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class RikudouMemoizeExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $container->registerAttributeForAutoconfiguration(
            Memoizable::class,
            static function (ChildDefinition $definition): void {
                $definition->addTag('rikudou.memoize.memoizable_service');
            }
        );

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configs = $this->processConfiguration($this->getConfiguration([], $container), $configs);

        $cacheService = $configs['cache_service'];
        $defaultMemoizeSeconds = $configs['default_memoize_seconds'];
        if ($defaultMemoizeSeconds < 0) {
            $cacheService = 'rikudou.memoize.internal_cache';
        }

        $container->setParameter('rikudou.memoize.cache_service', $cacheService);
        $container->setParameter('rikudou.memoize.default_memoize_seconds', $defaultMemoizeSeconds);
    }
}