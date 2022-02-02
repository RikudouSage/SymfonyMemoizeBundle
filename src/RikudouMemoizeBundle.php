<?php

namespace Rikudou\MemoizeBundle;

use Rikudou\MemoizeBundle\DependencyInjection\Compiler\MemoizeProxyCreatorCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class RikudouMemoizeBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $projectDir = $container->getParameter('kernel.project_dir');
        assert(is_string($projectDir));

        $container->setParameter(
            'rikudou.memoize.target_dir',
            "{$projectDir}/memoized",
        );

        $targetDir = $container->getParameter('rikudou.memoize.target_dir');
        assert(is_string($targetDir));

        spl_autoload_register(function (string $className) use ($targetDir) {
            if (!str_starts_with($className, 'App\\Memoized')) {
                return;
            }

            $path = substr($className, strlen('App\\Memoized\\'));
            $path = "{$targetDir}/{$path}.php";
            if (file_exists($path)) {
                require $path;
            }
        });

        $container->addCompilerPass(new MemoizeProxyCreatorCompilerPass());
    }
}
