<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database;

use Sigi\TempTableBundle\Service\Structures\Table;

interface TableCreatorInterface
{
    public function createFromCsv(string $csvFilePath, string $tableName, ?string $delimiter = ','): Table;
}
