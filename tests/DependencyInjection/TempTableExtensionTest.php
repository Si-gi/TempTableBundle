<?php
declare(strict_types=1);

namespace Sigi\Tests\TempTableBundle\DependencyInjection;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Sigi\TempTableBundle\DependencyInjection\TempTableExtension;

class TempTableExtensionTest extends TestCase
{
    public function testLoad()
    {
        $config = [
                'retention_hours' => 48,
                'table_prefix' => 'custom_',
                'csv_delimiter' => ';'
        ];

        $containerBuilder = new ContainerBuilder();
        $tempTableExtension = new TempTableExtension();
        
        $tempTableExtension->load([$config], $containerBuilder);

        $this->assertSame(48, $containerBuilder->getParameter('temp_table.retention_hours'));
        $this->assertSame('custom_', $containerBuilder->getParameter('temp_table.table_prefix'));
        $this->assertSame(';', $containerBuilder->getParameter('temp_table.csv_delimiter'));
    }
    public function testLoadWithDefaults()
    {
        $config = [];

        $containerBuilder = new ContainerBuilder();
        $tempTableExtension = new TempTableExtension();
        
        $tempTableExtension->load([$config], $containerBuilder);

        $this->assertSame(24, $containerBuilder->getParameter('temp_table.retention_hours'));
        $this->assertSame('temp_csv_', $containerBuilder->getParameter('temp_table.table_prefix'));
        $this->assertSame(',', $containerBuilder->getParameter('temp_table.csv_delimiter'));
    }

    public function testGetAlias()
    {
        $tempTableExtension = new TempTableExtension();
        $this->assertSame('temp_table', $tempTableExtension->getAlias());
    }
}