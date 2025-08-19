<?php

namespace Sigi\TempTableBundle\Service;

use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Connection;
use Sigi\TempTableBundle\Exception\CsvStructureException;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;

class TempTableManager
{
    private Connection $connection;
    private LoggerInterface $logger;
    private string $tablePrefix;
    private int $retentionHours;

    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        string $tablePrefix,
        int $retentionHours
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->tablePrefix = $tablePrefix;
        $this->retentionHours = $retentionHours;
    }

    /**
     * Create a temp table from a csvFile
     */
    public function createTableFromCsv(string $csvFilePath, string $tableName, ?string $delimiter = null): string
    {
        $fullTableName = $this->tablePrefix . $tableName;
        
        try {
            $structure = $this->analyzeCsvStructure($csvFilePath, $delimiter);
            
            $this->createTable($fullTableName, $structure);
            
            // Import Data with COPY (faster than INSERT)
            $this->importCsvData($csvFilePath, $fullTableName, $structure, $delimiter);
            
            $this->registerTable($fullTableName);
            
            $this->logger->info("Ttemp table created: {$fullTableName}");
            
            return $fullTableName;
            
        } catch (\Exception $e) {
            $this->logger->error("Erreor while creating table {$fullTableName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Analyzes the structure of the CSV to determine the columns
     */
    public function analyzeCsvStructure(string $csvFilePath, ?string $delimiter = null): array
    {
        $delimiter = $delimiter ?? ',';
        
        if (!file_exists($csvFilePath)){
            throw new FileNotFoundException("Csv File not found", 404);
        }

        if (!is_readable($csvFilePath)){
            throw new AccessDeniedException("Wrong Permission to read file");
        }
        
        $handle = fopen($csvFilePath, 'r');
        if (!$handle){
            throw new CsvStructureException(CsvStructureException::FOPEN_ERROR);
        }
        // Read the first line for column name
        //fgetCsv escape parameter default value deprecied in 8.4
        $headers = fgetcsv($handle, 0, $delimiter, escape: '');
        if (!$headers)
        {
            throw new CsvStructureException(CsvStructureException::FGETCSV_ERROR);
        }
        // Analyser quelques lignes pour déterminer les types
        $sampleData = [];
        $sampleCount = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) && $sampleCount < 100) {
            $sampleData[] = $row;
            $sampleCount++;
        }
        
        fclose($handle);

        $structure = [];
        foreach ($headers as $index => $header) {
            $columnName = $this->sanitizeColumnName($header);
            $columnType = $this->guessColumnType($sampleData, $index);
            
            $structure[] = [
                'name' => $columnName,
                'type' => $columnType,
                'original_name' => $header
            ];
        }

        return $structure;
    }

    /**
     * sanitize column name for PostgreSQL
     */
    private function sanitizeColumnName(string $name): string
    {
        // Replace special characters with underscores
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        // start by a letter if necessary
        if (preg_match('/^[0-9]/', $name)) {
            $name = 'col_' . $name;
        }
        return strtolower($name);
    }

    /**
     * Guess the column type from analyzeCsvStructure()
     */
    private function guessColumnType(array $sampleData, int $columnIndex): string
    {
        $values = array_column($sampleData, $columnIndex);
        $values = array_filter($values, fn($v) => $v !== null && $v !== '');

        if (empty($values)) {
            return 'TEXT';
        }

        $isInteger = true;
        $isNumeric = true;
        $isDate = true;
        $maxLength = 0;

        foreach ($values as $value) {
            $maxLength = max($maxLength, strlen($value));
            
            if (!is_numeric($value)) {
                $isInteger = false;
                $isNumeric = false;
            } elseif (!filter_var($value, FILTER_VALIDATE_INT)) {
                $isInteger = false;
            }
            
            if ($isDate && !strtotime($value)) {
                $isDate = false;
            }
        }

        if ($isInteger) {
            return 'INTEGER';
        } elseif ($isNumeric) {
            return 'DECIMAL';
        } elseif ($isDate && $maxLength <= 19) {
            return 'TIMESTAMP';
        } elseif ($maxLength <= 255) {
            return 'VARCHAR(255)';
        } else {
            return 'TEXT';
        }
    }

    /**
     * Create PostgreSQL table
     */
    private function createTable(string $tableName, array $structure): void
    {
        $columns = [];
        foreach ($structure as $column) {
            $columns[] = sprintf('"%s" %s', $column['name'], $column['type']);
        }

        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS "%s" (
                id SERIAL PRIMARY KEY,
                %s,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )',
            $tableName,
            implode(",\n                ", $columns)
        );

        $this->connection->executeStatement($sql);
    }

    /**
     * Import data in the temp table
     */
    private function importCsvData(string $csvFilePath, string $tableName, array $structure, ?string $delimiter = null): void
    {
        $delimiter = $delimiter ?? ',';
        
        // Utiliser COPY FROM pour une import rapide
        $columns = array_map(fn($col) => '"' . $col['name'] . '"', $structure);
        $columnList = implode(', ', $columns);

        $sql = sprintf(
            "COPY \"%s\" (%s) FROM STDIN WITH (FORMAT CSV, HEADER true, DELIMITER '%s')",
            $tableName,
            $columnList,
            $delimiter
        );

        // PostgreSQL COPY nécessite une approche spécifique
        $handle = fopen($csvFilePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier CSV: {$csvFilePath}");
        }

        $this->connection->beginTransaction();
        
        try {
            // Pour une version simplifiée, on utilise des INSERTs par batch
            $this->importCsvViaBatchInsert($csvFilePath, $tableName, $structure, $delimiter);
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Import par batch INSERT (plus compatible que COPY FROM)
     */
    private function importCsvViaBatchInsert(string $csvFilePath, string $tableName, array $structure, string $delimiter): void
    {
        $handle = fopen($csvFilePath, 'r');
        
        // Skip header
        fgetcsv($handle, 0, $delimiter);
        
        $batchSize = 1000;
        $batch = [];
        $columns = array_map(fn($col) => '"' . $col['name'] . '"', $structure);
        
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $batch[] = $row;
            
            if (count($batch) >= $batchSize) {
                $this->insertBatch($tableName, $columns, $batch);
                $batch = [];
            }
        }
        
        // Insert remaining rows
        if (!empty($batch)) {
            $this->insertBatch($tableName, $columns, $batch);
        }
        
        fclose($handle);
    }

    private function insertBatch(string $tableName, array $columns, array $batch): void
    {
        $placeholders = [];
        $values = [];
        
        foreach ($batch as $rowIndex => $row) {
            $rowPlaceholders = [];
            foreach ($row as $colIndex => $value) {
                $placeholder = ':val_' . $rowIndex . '_' . $colIndex;
                $rowPlaceholders[] = $placeholder;
                $values[$placeholder] = $value;
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $sql = sprintf(
            'INSERT INTO "%s" (%s) VALUES %s',
            $tableName,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->connection->executeStatement($sql, $values);
    }

    /**
     * Saves the table for automatic cleaning
     */
    private function registerTable(string $tableName): void
    {
        $sql = "INSERT INTO temp_table_registry (table_name, created_at) VALUES (:table_name, CURRENT_TIMESTAMP)
                ON CONFLICT (table_name) DO UPDATE SET created_at = CURRENT_TIMESTAMP";
        
        $this->connection->executeStatement($sql, ['table_name' => $tableName]);
    }

    /**
     * Clean outdated table
     */
    public function cleanupExpiredTables(): int
    {
        $sql = "SELECT table_name FROM temp_table_registry 
                WHERE created_at < (CURRENT_TIMESTAMP - INTERVAL '{$this->retentionHours} hours')";
        
        $expiredTables = $this->connection->fetchFirstColumn($sql);
        $cleanedCount = 0;

        foreach ($expiredTables as $tableName) {
            try {
                $this->dropTable($tableName);
                $cleanedCount++;
            } catch (\Exception $e) {
                $this->logger->error("Erreur lors de la suppression de la table {$tableName}: " . $e->getMessage());
            }
        }

        return $cleanedCount;
    }

    /**
     * Delete a temp table
     */
    public function dropTable(string $tableName): void
    {
        $this->connection->executeStatement(sprintf('DROP TABLE IF EXISTS "%s"', $tableName));
        $this->connection->executeStatement(
            'DELETE FROM temp_table_registry WHERE table_name = :table_name',
            ['table_name' => $tableName]
        );
        
        $this->logger->info("Table temporaire supprimée: {$tableName}");
    }

    /**
     * Initialize table registry
     */
    public function initializeRegistry(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS temp_table_registry (
            table_name VARCHAR(255) PRIMARY KEY,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->connection->executeStatement($sql);
    }
}