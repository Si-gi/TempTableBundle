<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service;

use Psr\Log\LoggerInterface;
use Sigi\TempTableBundle\Service\Database\TableCleanerInterface;
use Sigi\TempTableBundle\Service\Database\TableCreatorInterface;
use Sigi\TempTableBundle\Service\Database\TableRegistryInterface;
use Sigi\TempTableBundle\Service\Strategy\CsvImporterInterface;

class TempTableManager
{
    public function __construct(
        private TableCreatorInterface $tableCreator,
        private CsvImporterInterface $csvImporter,
        private TableRegistryInterface $tableRegistry,
        private TableCleanerInterface $tableCleaner,
        private LoggerInterface $logger
    ) {
    }

    public function createTableFromCsv(string $csvFilePath, string $tableName, ?string $delimiter = ','): string
    {
        $this->tableRegistry->initialize();

        try {
            $table = $this->tableCreator->createFromCsv($csvFilePath, $tableName, $delimiter);
            $this->csvImporter->import($csvFilePath, $table, $delimiter);
            $this->tableRegistry->register($table->getFullName());

            $this->logger->info("Temp table created successfully: {$table->getFullName()}");

            return $table->getFullName();
        } catch (\Exception $e) {
            $this->logger->error('Error creating table from CSV: '.$e->getMessage());

            throw $e;
        }
    }

    public function cleanupExpiredTables(): int
    {
        return $this->tableCleaner->cleanupExpiredTables();
    }

    public function dropTable(string $tableName): void
    {
        $this->tableCleaner->dropTable($tableName);
    }
}
// 37Hp/z8Dk$2;
// youdontneedit
