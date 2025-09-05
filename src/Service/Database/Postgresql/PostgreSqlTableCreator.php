<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database\Postgresql;

use Psr\Log\LoggerInterface;
use Sigi\TempTableBundle\Service\Database\DatabaseConnectionInterface;
use Sigi\TempTableBundle\Service\Database\TableCreatorInterface;
use Sigi\TempTableBundle\Service\Structures\Table;
use Sigi\TempTableBundle\Service\Structures\TableFactory;
use Sigi\TempTableBundle\Service\Structures\TempTableConfig;

class PostgreSqlTableCreator implements TableCreatorInterface
{
    public function __construct(
        private DatabaseConnectionInterface $connection,
        private TableFactory $tableFactory,
        private TempTableConfig $config,
        private LoggerInterface $logger
    ) {
    }

    public function createFromCsv(string $csvFilePath, string $tableName, ?string $delimiter = ','): Table
    {
        $fullTableName = $this->config->tablePrefix.$tableName;
        $table = $this->tableFactory->analyzeStructure($csvFilePath, $delimiter, $fullTableName);

        $this->dropTableIfExists($table->getFullName());
        $query = $this->buildCreateTableQuery($table);

        $this->connection->executeStatement($query);
        $this->logger->info('Table created: '.$table->getName());

        return $table;
    }

    private function buildCreateTableQuery(Table $table): string
    {
        $columns = [];
        foreach ($table->getColumns() as $column) {
            $columns[] = \sprintf('"%s" %s DEFAULT NULL', $column->getName(), $column->getType());
        }

        return \sprintf(
            'CREATE TABLE IF NOT EXISTS "%s" (%s)',
            $table->getFullName(),
            implode(",\n                ", $columns)
        );
    }

    private function dropTableIfExists(string $tableName): void
    {
        $this->connection->executeStatement(\sprintf('DROP TABLE IF EXISTS "%s"', $tableName));
    }
}
