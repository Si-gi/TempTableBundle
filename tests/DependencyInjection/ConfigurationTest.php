<?php
declare(strict_types=1);

namespace Sigi\Tests\TempTableBundle\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Sigi\TempTableBundle\DependencyInjection\Configuration;

class ConfigurationTest extends TestCase
{
    private Configuration $configuration;
    private Processor $processor;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        $this->processor = new Processor();
    }

    public function testDefaultConfiguration()
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            []
        );

        $expectedConfig = [
            'retention_hours' => 24,
            'table_prefix' => 'temp_csv_',
            'csv_delimiter' => ','
        ];

        $this->assertSame($expectedConfig, $config);
    }

    public function testCustomConfiguration()
    {
        $inputConfig = [
            'temp_table' => [
                'retention_hours' => 48,
                'table_prefix' => 'custom_',
                'csv_delimiter' => ';'
            ]
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            $inputConfig
        );

        $expectedConfig = [
            'retention_hours' => 48,
            'table_prefix' => 'custom_',
            'csv_delimiter' => ';'
        ];

        $this->assertSame($expectedConfig, $config);
    }

    public function testPartialConfiguration()
    {
        $inputConfig = [
            'temp_table' => [
                'retention_hours' => 72
            ]
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            $inputConfig
        );

        $expectedConfig = [
            'retention_hours' => 72,
            'table_prefix' => 'temp_csv_', // default value
            'csv_delimiter' => ',' // default value
        ];

        $this->assertSame($expectedConfig, $config);
    }

    public function testRetentionHoursValidation()
    {
        $inputConfig = [
            'temp_table' => [
                'retention_hours' => 'invalid_value'
            ]
        ];

        $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidTypeException');
        
        $this->processor->processConfiguration(
            $this->configuration,
            $inputConfig
        );
    }

    public function testEmptyTablePrefix()
    {
        $inputConfig = [
            'temp_table' => [
                'table_prefix' => ''
            ]
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            $inputConfig
        );

        $this->assertSame('', $config['table_prefix']);
    }

    public function testSpecialCharactersInDelimiter()
    {
        $inputConfig = [
            'temp_table' => [
                'csv_delimiter' => '|'
            ]
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            $inputConfig
        );

        $this->assertSame('|', $config['csv_delimiter']);
    }

    public function testMultipleConfigurations()
    {
        // Simule la fusion de plusieurs configurations
        $config1 = [
                'retention_hours' => 12
        ];
        
        $config2 = [
                'table_prefix' => 'override_'
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            [$config1, $config2]
        );

        $expectedConfig = [
            'retention_hours' => 12,
            'table_prefix' => 'override_',
            'csv_delimiter' => ',' // default value
        ];

        $this->assertSame($expectedConfig, $config);
    }

    #[DataProvider('invalidRetentionHoursProvider')]
    public function testInvalidRetentionHours($invalidValue)
    {
        $inputConfig = [
            'temp_table' => [
                'retention_hours' => $invalidValue
            ]
        ];

        $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidTypeException');
        
        $this->processor->processConfiguration(
            $this->configuration,
            $inputConfig
        );
    }

    public static function invalidRetentionHoursProvider(): array
    {
        return [
            'string' => ['not_a_number'],
            'float' => [24.5],
            'array' => [[]],
            'null' => [null],
            'boolean' => [true],
        ];
    }
}