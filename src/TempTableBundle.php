<?php

namespace Sigi\TempTableBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Sigi\TempTableBundle\DependencyInjection\TempTableExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class TempTableBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new TempTableExtension();
    }
}