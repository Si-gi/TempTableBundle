<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database;

interface StatementInterface
{
    public function bindValue(string $param, mixed $value, int $type): void;
    public function executeStatement(): int;
}