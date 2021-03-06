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

        $container->addCompilerPass(new MemoizeProxyCreatorCompilerPass());
    }
}
