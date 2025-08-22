<?php declare(strict_types=1);

namespace Sigi\TempTableBundle;

use Sigi\TempTableBundle\DependencyInjection\TempTableExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

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
