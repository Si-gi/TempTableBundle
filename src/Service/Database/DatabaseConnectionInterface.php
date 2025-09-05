<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database;

interface DatabaseConnectionInterface
{
    public function executeStatement(string $sql, array $params = []): int;

    public function prepare(string $sql): StatementInterface;

    public function fetchFirstColumn(string $sql, array $params = []): array;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function supportsCopyFrom(): bool;

    public function getConnectionParams(): array;

    public function getNativeConnection(): mixed;
}
