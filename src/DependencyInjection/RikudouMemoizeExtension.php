<?php

namespace Rikudou\MemoizeBundle\DependencyInjection;

use Exception;
use Rikudou\MemoizeBundle\Attribute\Memoizable;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class RikudouMemoizeExtension extends Extension
{
    /**
     * @param array<array<string, mixed>> $configs
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            Memoizable::class,
            static function (ChildDefinition $definition): void {
                $definition->addTag('rikudou.memoize.memoizable_service');
            }
        );

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = $this->getConfiguration([], $container);
        assert($configuration !== null);
        $configs = $this->processConfiguration($configuration, $configs);

        $cacheService = $configs['cache_service'];
        $defaultMemoizeSeconds = $configs['default_memoize_seconds'];
        if ($defaultMemoizeSeconds < 0) {
            $cacheService = 'rikudou.memoize.internal_cache';
        }

        $container->setParameter('rikudou.memoize.cache_service', $cacheService);
        $container->setParameter('rikudou.memoize.default_memoize_seconds', $defaultMemoizeSeconds);
        $container->setParameter('rikudou.memoize.enabled', $configs['enabled']);
    }
}
