<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database;

interface TableCleanerInterface
{
    public function cleanupExpiredTables(): int;
    public function dropTable(string $tableName): void;
}
;
