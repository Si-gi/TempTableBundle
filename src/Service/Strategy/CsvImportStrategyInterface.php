<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Strategy;

use Sigi\TempTableBundle\Service\Database\DatabaseConnectionInterface;
use Sigi\TempTableBundle\Service\Structures\Table;

interface CsvImportStrategyInterface
{
    public function canHandle(DatabaseConnectionInterface $connection): bool;

    public function import(string $csvFilePath, Table $table, string $delimiter): void;

    public function getPriority(): int; // Plus le nombre est bas, plus la priorité est haute
}
