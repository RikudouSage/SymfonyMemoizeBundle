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

        $container->setParameter('rikudou.memoize.cache_service', $configs['cache_service']);
        $container->setParameter('rikudou.memoize.default_memoize_seconds', $configs['default_memoize_seconds']);
        $container->setParameter('rikudou.memoize.enabled', $configs['enabled']);
        $container->setParameter('rikudou.memoize.key_specifier_service', $configs['key_specifier_service']);
    }
}
