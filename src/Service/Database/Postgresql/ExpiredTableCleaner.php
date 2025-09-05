<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database\Postgresql;

use Psr\Log\LoggerInterface;
use Sigi\TempTableBundle\Service\Database\DatabaseConnectionInterface;
use Sigi\TempTableBundle\Service\Database\TableCleanerInterface;
use Sigi\TempTableBundle\Service\Database\TableRegistryInterface;
use Sigi\TempTableBundle\Service\Structures\TempTableConfig;

class ExpiredTableCleaner implements TableCleanerInterface
{
    public function __construct(
        private DatabaseConnectionInterface $connection,
        private TableRegistryInterface $tableRegistry,
        private TempTableConfig $config,
        private LoggerInterface $logger
    ) {
    }

    public function cleanupExpiredTables(): int
    {
        $expiredTables = $this->tableRegistry->getExpiredTables($this->config->retentionHours);
        $cleanedCount = 0;

        foreach ($expiredTables as $tableName) {
            try {
                $this->dropTable($tableName);
                ++$cleanedCount;
            } catch (\Exception $e) {
                $this->logger->error("Error dropping table {$tableName}: ".$e->getMessage());
            }
        }

        $this->logger->info("Cleaned {$cleanedCount} expired tables");

        return $cleanedCount;
    }

    public function dropTable(string $tableName): void
    {
        $this->connection->executeStatement(\sprintf('DROP TABLE IF EXISTS "%s"', $tableName));
        $this->tableRegistry->unregister($tableName);
        $this->logger->info("Table dropped: {$tableName}");
    }
}
