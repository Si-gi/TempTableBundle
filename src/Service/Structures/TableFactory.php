<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Structures;

use Sigi\TempTableBundle\Exception\CsvStructureException;
use Sigi\TempTableBundle\Service\TypeGuesser;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;

class TableFactory
{
    public function __construct(private int $rowToAnalyse, private TypeGuesser $typeGuesser)
    {
    }

    /**
     * Analyzes the structure of the CSV to determine the columns
     */
    public function analyzeStructure(string $csvFilePath, string $delimiter = ',', string $tableName = 'temp'): Table
    {
        // if (!file_exists($csvFilePath)){
        //     throw new FileNotFoundException("Csv File not found", 404);
        // }

        // if (!is_readable($csvFilePath)){
        //     throw new AccessDeniedException("Wrong Permission to read file");
        // }

        $handle = fopen($csvFilePath, 'r');
        if (!$handle) {
            throw new CsvStructureException(CsvStructureException::FOPEN_ERROR);
        }
        // Read the first line for column name
        // fgetCsv escape parameter default value deprecied in 8.4
        $headers = fgetcsv($handle, null, $delimiter);
        if (!$headers) {
            throw new CsvStructureException(CsvStructureException::FGETCSV_ERROR);
        }
        $tempTable = new Table($tableName, 'tmp');
        foreach ($headers as $header) {
            $tempTable->addColumn(new Column($header));
        }

        // Analyser quelques lignes pour déterminer les types
        $sampleData = [];
        $sampleCount = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) && $sampleCount < $this->rowToAnalyse) {
            foreach ($row as $index => $value) {
                $tempTable->getColumnByIndex($index)?->addValue($value);
            }
            /** @var array<array<string>> */
            $sampleData[] = $row;
            ++$sampleCount;
        }

        fclose($handle);

        // Columns doesn't have type yet
        foreach ($tempTable->getColumns() as $column) {
            $columnType = $this->typeGuesser->guessTypes($column->getValues());
            $column->setType($columnType);
        }

        return $tempTable;
    }
}
