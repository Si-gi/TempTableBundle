<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Strategy;

use Psr\Log\LoggerInterface;
use Sigi\TempTableBundle\Service\Structures\TempTableConfig;
use Sigi\TempTableBundle\Service\Structures\Table;
use Sigi\TempTableBundle\Service\Database\DatabaseConnectionInterface;
use Sigi\TempTableBundle\Service\Database\TypeConverters\TypeConverterInterface;


class BatchInsertStrategy implements CsvImportStrategyInterface
{
    public function __construct(
        private DatabaseConnectionInterface $connection,
        private TypeConverterInterface $typeConverter,
        private TempTableConfig $config,
        private LoggerInterface $logger
    ) {}

    public function canHandle(DatabaseConnectionInterface $connection): bool
    {
        return true; // Always avalaible as fallback
    }

    public function getPriority(): int
    {
        return 10; // low priority because it's a fallback
    }

    public function import(string $csvFilePath, Table $table, string $delimiter): void
    {
        $handle = fopen($csvFilePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Cannot open CSV file: {$csvFilePath}");
        }

        try {
            fgetcsv($handle, 0, $delimiter); // Skip header
            $this->processCsvInBatches($handle, $table, $delimiter);
            $this->logger->info('Batch insert completed successfully');
        } finally {
            fclose($handle);
        }
    }

    private function processCsvInBatches($handle, Table $table, string $delimiter): void
    {
        $batch = [];
        $columns = array_map(fn($col) => '"' . $col->getName() . '"', $table->getColumns()->toArray());

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $typedRow = $this->convertRowTypes($row, $table);
            $batch[] = $typedRow;

            if (count($batch) >= $this->config->batchSize) {
                $this->insertBatch($table, $columns, $batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->insertBatch($table, $columns, $batch);
        }
    }

    private function convertRowTypes(array $row, Table $table): array
    {
        // Adjust row length to match table columns
        while (count($row) > count($table->getColumns())) {
            array_pop($row);
        }
        while (count($row) < count($table->getColumns())) {
            $row[] = null;
        }

        $typedRow = [];
        foreach ($row as $index => $value) {
            $columnType = $table->getColumnByIndex($index)->getType();
            $typedRow[] = $this->typeConverter->convert($value, $columnType);
        }

        return $typedRow;
    }

    private function insertBatch(Table $table, array $columns, array $batch): void
    {
        if (empty($batch)) {
            return;
        }

        $columnList = implode(', ', $columns);
        $placeholderRows = [];
        $values = [];
        $paramIndex = 1;

        foreach ($batch as $row) {
            $placeholders = [];
            foreach ($row as $colIndex => $value) {
                $placeholder = ':param' . $paramIndex++;
                $placeholders[] = $placeholder;
                $values[$placeholder] = [
                    'value' => $value,
                    'type' => $this->typeConverter->getPdoType($table->getColumnByIndex($colIndex)->getType()),
                ];
            }
            $placeholderRows[] = '(' . implode(', ', $placeholders) . ')';
        }

        $sql = sprintf(
            'INSERT INTO "%s" (%s) VALUES %s',
            $table->getFullName(),
            $columnList,
            implode(', ', $placeholderRows)
        );

        $stmt = $this->connection->prepare($sql);
        foreach ($values as $placeholder => $data) {
            $stmt->bindValue($placeholder, $data['value'], $data['type']);
        }

        $stmt->executeStatement();
    }
}