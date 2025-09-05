<?php


declare(strict_types=1);

namespace Sigi\Tests\TempTableBundle\Service;

use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\MockObject\MockObject;
use Sigi\TempTableBundle\Service\TempTableManager;
use Sigi\TempTableBundle\Service\Strategy\CsvImporterInterface;
use Sigi\TempTableBundle\Service\Database\TableCleanerInterface;
use Sigi\TempTableBundle\Service\Database\TableCreatorInterface;
use Sigi\TempTableBundle\Service\Database\TableRegistryInterface;
use Sigi\TempTableBundle\Service\Structures\Table;
use Symfony\Component\DependencyInjection\Loader\Configurator\Traits\PropertyTrait;

class TempTableManagerTest extends TestCase{
    use PropertyTrait;
    private MockObject|TableCreatorInterface $tableCreator;
    private MockObject|CsvImporterInterface $csvImporter;
    private MockObject|TableRegistryInterface $tableRegistry;
    private MockObject|TableCleanerInterface $tableCleaner;
    private MockObject|LoggerInterface $logger;
    private TempTableManager $tempTableManager;
    
    private vfsStreamDirectory $rootDir;
    protected function setUp(): void
    {
        $this->tableCreator = $this->createMock(TableCreatorInterface::class);
        $this->csvImporter = $this->createMock(CsvImporterInterface::class);
        $this->tableRegistry = $this->createMock(TableRegistryInterface::class);
        $this->tableCleaner = $this->createMock(TableCleanerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->tempTableManager = new TempTableManager($this->tableCreator, $this->csvImporter, $this->tableRegistry, $this->tableCleaner, $this->logger);
    }
    protected function tearDown(): void{

    }

    public function testDropTable(): void
    {
        $this->tableCleaner->expects($this->once())->method('dropTable');
        $this->tempTableManager->dropTable("tableName");
    }

    public function testCleanupExpiredTables() : void
    {

        $this->tableCleaner->expects($this->once())->method("cleanupExpiredTables");
        $this->tempTableManager->cleanupExpiredTables();
    }

    public function testCreateTableFromCsv(): void
    {
        $table = new Table("table", "tmp_");
        $this->tableCreator->expects($this->once())->method('createFromCsv')->willReturn($table);
        $this->tableRegistry->expects($this->once())->method("initialize");
        $this->tableRegistry->expects($this->once())->method("register");
        $this->logger->expects($this->once())->method('info');

        $this->csvImporter->expects($this->once())->method('import');
        $tableName = $this->tempTableManager->createTableFromCsv('file.csv', 'tableName');

        $this->assertSame($table->getFullName(), $tableName);
    }

    public function testCreateTableFromCsvThrowException(): void
    {
        $this->expectException(\Exception::class);
        $this->tableCreator->expects($this->once())->method('createFromCsv')->willThrowException(new \Exception());
        $this->logger->expects($this->once())->method('error');
        $this->tempTableManager->createTableFromCsv('file.csv', 'tableName');

    }
    
}