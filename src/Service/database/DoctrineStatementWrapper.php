<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database;

use \Doctrine\DBAL\Statement;

class DoctrineStatementWrapper implements StatementInterface
{
    public function __construct(private Statement $statement) {}

    public function bindValue(string $param, mixed $value, int $type): void
    {
        $this->statement->bindValue($param, $value, $type);
    }

    public function executeStatement(): int
    {
        return $this->statement->executeStatement();
    }
}