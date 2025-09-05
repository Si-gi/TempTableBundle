<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database;

interface TableRegistryInterface
{
    public function initialize(): void;

    public function register(string $tableName): void;

    public function getExpiredTables(int $retentionHours): array;

    public function unregister(string $tableName): void;
}
