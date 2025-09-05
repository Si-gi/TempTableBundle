<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database\Postgresql;

use Psr\Log\LoggerInterface;
use Sigi\TempTableBundle\Service\Database\TableRegistryInterface;
use Sigi\TempTableBundle\Service\Database\DatabaseConnectionInterface;

class DatabaseTableRegistry implements TableRegistryInterface
{

    private const REGISTRY_TABLE = 'temp_table_registry';

    public function __construct(
        private DatabaseConnectionInterface $connection,
        private LoggerInterface $logger
    ) {}

    public function initialize(): void
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS "%s" (
                table_name VARCHAR(255) PRIMARY KEY,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )',
            self::REGISTRY_TABLE
        );

        try {
            $this->connection->executeStatement($sql);
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize registry: ' . $e->getMessage());
            throw new \RuntimeException('Registry initialization failed', 0, $e);
        }
    }

     public function register(string $tableName): void
    {
        $sql = sprintf(
            'INSERT INTO "%s" (table_name, created_at) VALUES (:table_name, CURRENT_TIMESTAMP)
             ON CONFLICT (table_name) DO UPDATE SET created_at = CURRENT_TIMESTAMP',
            self::REGISTRY_TABLE
        );

        try {
            $this->connection->executeStatement($sql, ['table_name' => $tableName]);
            $this->logger->info("Table registered: {$tableName}");
        } catch (\Exception $e) {
            $this->logger->error("Failed to register table {$tableName}: " . $e->getMessage());
            throw new \RuntimeException('Table registration failed', 0, $e);
        }
    }

    public function getExpiredTables(int $retentionHours): array
    {
        $sql = sprintf(
            'SELECT table_name FROM "%s" 
             WHERE created_at < (CURRENT_TIMESTAMP - INTERVAL \'%d hours\')',
            self::REGISTRY_TABLE,
            $retentionHours
        );

        return $this->connection->fetchFirstColumn($sql);
    }

    public function unregister(string $tableName): void
    {
        $sql = sprintf(
            'DELETE FROM "%s" WHERE table_name = :table_name',
            self::REGISTRY_TABLE
        );

        $this->connection->executeStatement($sql, ['table_name' => $tableName]);
        $this->logger->info("Table unregistered: {$tableName}");
    }
}