<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Strategy;

use Psr\Log\LoggerInterface;
use Sigi\TempTableBundle\Service\Database\DatabaseConnectionInterface;
use Sigi\TempTableBundle\Service\Structures\Table;

class CopyFromImportStrategy implements CsvImportStrategyInterface
{
    public function __construct(
        private DatabaseConnectionInterface $connection,
        private LoggerInterface $logger
    ) {
    }

    public function canHandle(DatabaseConnectionInterface $connection): bool
    {
        return $connection->supportsCopyFrom();
    }

    public function getPriority(): int
    {
        return 1; // max priority value
    }

    public function import(string $csvFilePath, Table $table, string $delimiter): void
    {
        try {
            $this->importViaCopyFromDirect($csvFilePath, $table, $delimiter);
            $this->logger->info('Import COPY FROM success');
        } catch (\Exception $e) {
            $this->logger->warning('COPY FROM direct failed, trying pg_connect: '.$e->getMessage());
            $this->importViaPgConnect($csvFilePath, $table, $delimiter);
        }
    }

    private function importViaCopyFromDirect(string $csvFilePath, Table $table, string $delimiter): void
    {
        $columns = array_map(static fn ($col) => '"'.$col->getName().'"', $table->getColumns()->toArray());
        $columnList = implode(', ', $columns);

        $copyCommand = \sprintf(
            "COPY \"%s\" (%s) FROM '%s' WITH (FORMAT CSV, DELIMITER '%s', NULL '', QUOTE '\"', HEADER true)",
            $table->getFullName(),
            $columnList,
            $csvFilePath,
            $delimiter
        );

        $this->connection->executeStatement($copyCommand);
    }

    private function importViaPgConnect(string $csvFilePath, Table $table, string $delimiter): void
    {
        if (!\function_exists('pg_connect')) {
            throw new \RuntimeException('pg_connect function not available');
        }

        $params = $this->connection->getConnectionParams();
        $connString = \sprintf(
            'host=%s port=%s dbname=%s user=%s password=%s connect_timeout=20',
            $params['host'] ?? 'localhost',
            $params['port'] ?? 5432,
            $params['dbname'],
            $params['user'],
            $params['password'] ?? ''
        );

        $pgConn = pg_connect($connString);
        if (!$pgConn) {
            throw new \RuntimeException('Failed to connect to PostgreSQL for COPY FROM');
        }

        try {
            $this->executePgCopyFrom($pgConn, $csvFilePath, $table, $delimiter);
        } finally {
            pg_close($pgConn);
        }
    }

    private function executePgCopyFrom($pgConn, string $csvFilePath, Table $table, string $delimiter): void
    {
        $columns = array_map(static fn ($col) => '"'.$col->getName().'"', $table->getColumns()->toArray());
        $columnList = implode(', ', $columns);

        $copyCommand = \sprintf(
            "COPY \"%s\" (%s) FROM '%s' WITH (FORMAT CSV, DELIMITER '%s', NULL '', HEADER true)",
            $table->getFullName(),
            $columnList,
            $csvFilePath,
            $delimiter
        );

        $result = pg_query($pgConn, $copyCommand);
        if (!$result) {
            throw new \RuntimeException('COPY FROM error: '.pg_last_error($pgConn));
        }

        $affectedRows = pg_affected_rows($result);
        $this->logger->info("Imported {$affectedRows} rows via pg_connect");
    }
}
