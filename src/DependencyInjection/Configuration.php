<?php declare(strict_types=1);

namespace Sigi\TempTableBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('temp_table');
        $rootNode = $treeBuilder->getRootNode();
        
        $rootNode
            ->info('Temp table bundle symfony bundle configuration')
            ->children()
                ->integerNode('retention_hours')
                    ->defaultValue(24)
                    ->info('Numbers of hours to keep temp tables')
                ->end()
                ->scalarNode('table_prefix')
                    ->defaultValue('temp_csv_')
                    ->info('Prefix for temp tables')
                ->end()
                ->scalarNode('csv_delimiter')
                    ->defaultValue(',')
                    ->info('default delimiter')
                ->end()
            ->end()
        ;
        
        return $treeBuilder;
    }
}
