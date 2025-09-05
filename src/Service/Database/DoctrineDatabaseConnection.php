<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database;

use Doctrine\DBAL\Connection;

class DoctrineDatabaseConnection implements DatabaseConnectionInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function executeStatement(string $sql, array $params = []): int
    {
        return $this->connection->executeStatement($sql, $params);
    }

    public function prepare(string $sql): StatementInterface
    {
        return new DoctrineStatementWrapper($this->connection->prepare($sql));
    }

    public function fetchFirstColumn(string $sql, array $params = []): array
    {
        return $this->connection->fetchFirstColumn($sql, $params);
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->connection->rollBack();
    }

    public function supportsCopyFrom(): bool
    {
        try {
            $pdo = $this->connection->getNativeConnection();

            return ($pdo instanceof \PDO) && ('pgsql' === $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        } catch (\Exception) {
            return false;
        }
    }

    public function getConnectionParams(): array
    {
        return $this->connection->getParams();
    }

    public function getNativeConnection(): mixed
    {
        return $this->connection->getNativeConnection();
    }
}
