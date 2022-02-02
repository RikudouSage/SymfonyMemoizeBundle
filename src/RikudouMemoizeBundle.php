<?php

namespace Rikudou\MemoizeBundle;

use Rikudou\MemoizeBundle\DependencyInjection\Compiler\MemoizeProxyCreatorCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class RikudouMemoizeBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->setParameter(
            'rikudou.memoize.target_dir',
            $container->getParameter('kernel.project_dir') . '/memoized',
        );

        spl_autoload_register(function (string $className) use ($container) {
            if (!str_starts_with($className, 'App\\Memoized')) {
                return;
            }

            $path = substr($className, strlen('App\\Memoized\\'));
            $path = $container->getParameter('rikudou.memoize.target_dir') . '/' . $path . '.php';
            if (file_exists($path)) {
                require $path;
            }
        });

        $container->addCompilerPass(new MemoizeProxyCreatorCompilerPass());
    }
}