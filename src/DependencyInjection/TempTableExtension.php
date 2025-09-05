<?php declare(strict_types=1);

namespace Sigi\TempTableBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class TempTableExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('temp_table.retention_hours', $config['retention_hours']);
        $container->setParameter('temp_table.table_prefix', $config['table_prefix']);
        $container->setParameter('temp_table.csv_delimiter', $config['csv_delimiter']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'temp_table';
    }
}
