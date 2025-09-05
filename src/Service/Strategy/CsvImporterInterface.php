<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Strategy;

use Sigi\TempTableBundle\Service\Structures\Table;

interface CsvImporterInterface
{
    public function import(string $csvFilePath, Table $table, string $delimiter): void;
}