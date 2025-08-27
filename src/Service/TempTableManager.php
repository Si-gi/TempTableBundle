<?php declare(strict_types=1);

namespace Sigi\TempTableBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Sigi\TempTableBundle\Service\Structures\Table;
use Sigi\TempTableBundle\Service\Structures\TableFactory;

class TempTableManager
{
    private Connection $connection;
    private LoggerInterface $logger;
    private string $tablePrefix;
    private int $retentionHours;

    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        private TableFactory $tableFactory,
        string $tablePrefix,
        int $retentionHours
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->tablePrefix = $tablePrefix;
        $this->retentionHours = $retentionHours;
    }

    public function createTableFromCsv(string $csvFilePath, string $tableName, ?string $delimiter = ',')
    {
        $this->initializeRegistry();

        try {
            $table = $this->tableFactory->analyzeStructure($csvFilePath, $delimiter, $this->tablePrefix.$tableName);

            $query = $this->createTableQuery($table);
            $this->connection->executeStatement($query);

            $this->logger->info('Table created '.$table->getName());

            // Import Data with COPY (faster than INSERT)
            $this->importCsvData($csvFilePath, $table, $delimiter);

            $this->logger->info("Ttemp table created: {$table->getName()}");

            return $table->getFullName();
        } catch (\Exception $e) {
            $this->logger->error("Erreor while creating table {$table->getName()}: ".$e->getMessage());

            throw $e;
        }
    }

    /**
     * Create PostgreSQL table
     */
    public function createTableQuery(Table $table): string
    {
        $this->dropTable($table->getFullName());
        $columns = [];
        foreach ($table->getColumns() as $column) {
            $columns[] = \sprintf('"%s" %s DEFAULT NULL', $column->getName(), $column->getType());
        }

        $sql = \sprintf(
            'CREATE TABLE IF NOT EXISTS "%s" (
                %s
            )',
            $table->getFullName(),
            implode(",\n                ", $columns)
        );
        dump($sql);

        return $sql;
    }

    /**
     * Import data with proper type detection and COPY FROM optimization
     */
    private function importCsvData(string $csvFilePath, Table $table, ?string $delimiter = null): void
    {
        $delimiter ??= ',';

        $this->connection->beginTransaction();

        // handle 3 cases
        try {
            if ($this->canUseCopyFrom()) {
                try {
                    $this->importCsvViaCopyFrom($csvFilePath, $table, $delimiter);
                 
                } catch (\Exception $e) {
                    $this->importCsvViaBatchInsertTyped($csvFilePath, $table, $delimiter);
                }
            } else {
                $this->importCsvViaBatchInsertTyped($csvFilePath, $table, $delimiter);
            }

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }

    /**
     * Verify if COPY FROM is available, check driver
     */
    private function canUseCopyFrom(): bool
    {
        try {
            $pdo = $this->connection->getNativeConnection();

            return ($pdo instanceof \PDO)
                && ('pgsql' === $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Optimized import with COPY FROM
     */
    private function importCsvViaCopyFrom(string $csvFilePath, Table $table, string $delimiter): void
    {
        try {
            $this->importCsvViaCopyFromSimple($csvFilePath, $table, $delimiter);
            $this->logger->info('Import COPY FROM success');

            return;
        }catch(\Doctrine\DBAL\Exception $e){
                throw $e;
        } catch (\Exception $e) {
            $this->logger->info('function importCsvViaCopyFromSimple failed: '.$e->getMessage());
        }

        // Fallback pg_connect if PDO fail
        if (\function_exists('pg_connect')) {
            try {
                $columns = array_map(static fn ($col) => '"'.$col->getName().'"', $table->getColumns()->toArray());
                $columnList = implode(', ', $columns);
                $copyCommand = \sprintf(
                    "COPY \"%s\" (%s) FROM %s WITH (FORMAT CSV, HEADER true, DELIMITER '%s', ENCODING 'UTF8')",
                    $table->getFullName(),
                    $columnList,
                    $csvFilePath,
                    $delimiter
                );
                $this->importViaPgCopyFrom($copyCommand, $csvFilePath, $delimiter);

                return;
            } catch (\Exception $e) {
                $this->logger->info('Échec pg_connect COPY FROM: '.$e->getMessage());
            }
        }

        // throw exception to use the fallback
        throw new \RuntimeException('COPY FROM non disponible, utilisation du fallback batch insert');
    }

    /**
     * COPY FROM with nativ postgresql functions
     */
    private function importViaPgCopyFrom(string $copyCommand, string $csvFilePath, string $delimiter): void
    {
        $params = $this->connection->getParams();
        $connString = \sprintf(
            'host=%s port=%s dbname=%s user=%s password=%s connect_timeout=20',
            $params['host'] ?? 'localhost',
            $params['port'] ?? 5432,
            $params['dbname'],
            $params['user'],
            $params['password'] ?? ''
        );

        $pgConn = pg_connect($connString);
        if (!$pgConn) {
            throw new \RuntimeException('Failed to connect to Postgresql for COPY FROM');
        }

        try {
            // Solution 1: Utiliser COPY FROM avec un fichier temporaire (plus fiable)
            $this->importViaPgCopyFromFile($pgConn, $csvFilePath, $copyCommand, $delimiter);
        } finally {
            pg_close($pgConn);
        }
    }

    /**
     * Import via COPY FROM avec fichier (plus fiable que pg_copy_from avec array)
     * @param mixed $pgConn
     */
    private function importViaPgCopyFromFile($pgConn, string $csvFilePath, string $copyCommand, string $delimiter): void
    {
        // Créer un fichier temporaire sans header
        $tempFile = tempnam(sys_get_temp_dir(), 'pg_copy_');
        $originalHandle = fopen($csvFilePath, 'r');
        $tempHandle = fopen($tempFile, 'w');

        // Skip header
        $header = fgetcsv($originalHandle, null, $delimiter);
        array_pop($header);
        array_pop($header);

        $lineCount = 0;
        while (($row = fgetcsv($originalHandle, 0, $delimiter)) !== false) {
            // Nettoyer les données pour éviter les problèmes
            while (\count($row) > \count($header)) {
                array_pop($row);
            }
            $cleanedRow = array_map(static function ($value) {
                if (null === $value) {
                    return '';
                }

                return (string) $value;
            }, $row);

            fputcsv($tempHandle, $cleanedRow, $delimiter);
            ++$lineCount;
        }

        fclose($originalHandle);
        fclose($tempHandle);

        dump("Fichier temporaire créé avec {$lineCount} lignes");

        try {
            // Utiliser COPY FROM avec le fichier temporaire
            $tableName = $this->getTableNameOnly($copyCommand);
            $columns = $this->getColumnsFromCopyCommand($copyCommand);

            $copyFromFileCommand = \sprintf(
                "COPY \"%s\" (%s) FROM '%s' WITH (FORMAT CSV, DELIMITER '%s', NULL '')",
                $tableName,
                $columns,
                $csvFilePath,
                $delimiter
            );

            $result = pg_query($pgConn, $copyFromFileCommand);

            if (!$result) {
                throw new \RuntimeException('Erreur lors de COPY FROM: '.pg_last_error($pgConn));
            }

            $affectedRows = pg_affected_rows($result);
            $this->logger->info("Imported row: {$affectedRows}");
        } finally {
            unlink($tempFile);
        }
    }

    /**
     * Extraire les colonnes d'une commande COPY
     */
    private function getColumnsFromCopyCommand(string $copyCommand): string
    {
        if (preg_match('/COPY\s+"?[^"(\s]+"?\s*\(([^)]+)\)/i', $copyCommand, $matches)) {
            return trim($matches[1]);
        }

        throw new \RuntimeException('Impossible d\'extraire les colonnes de la commande COPY');
    }

    /**
     * Fallback : Batch insert with precises types
     */
    private function importCsvViaBatchInsertTyped(string $csvFilePath, Table $table, string $delimiter): void
    {
        $handle = fopen($csvFilePath, 'r');
        fgetcsv($handle, 0, $delimiter); // Skip header

        $batchSize = 1000;
        $batch = [];
        $columns = array_map(static fn ($col) => '"'.$col->getName().'"', $table->getColumns()->toArray());

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Ajuster le nombre de colonnes
            while (\count($row) > \count($table->getColumns())) {
                array_pop($row);
            }
            while (\count($row) < \count($table->getColumns())) {
                $row[] = null;
            }

            // Convertir selon les types détectés
            $typedRow = [];
            foreach ($row as $index => $value) {
                $columnType = $table->getColumnByIndex($index)->getType();
                $typedRow[] = $this->convertValueToType($value, $columnType);
            }

            $batch[] = $typedRow;

            if (\count($batch) >= $batchSize) {
                $this->insertTypedBatch($table, $columns, $batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->insertTypedBatch($table, $columns, $batch);
        }

        fclose($handle);
    }

    /**
     * Convertir une valeur selon le type de colonne
     * @param mixed $value
     */
    private function convertValueToType($value, string $columnType): mixed
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $value = (string) $value;

        switch (strtoupper($columnType)) {
            case TypeGuesser::INTEGER:
                return false !== filter_var($value, \FILTER_VALIDATE_INT) ? (int) $value : null;

            case TypeGuesser::BIGINT:
                return is_numeric($value) ? $value : null;

            case TypeGuesser::DECIMAL:
            case TypeGuesser::NUMERIC:
                return is_numeric($value) ? $value : null;

            // case TypeGuesser::TIMESTAMP:
            // case TypeGuesser::DATE:
            //     $timestamp = strtotime($value);

            //     return false !== $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;

            default:
                return $value;
        }
    }

    /**
     * Insert avec binding typé approprié
     */
    private function insertTypedBatch(Table $table, array $columns, array $batch): void
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
                $placeholder = ':param'.$paramIndex++;
                $placeholders[] = $placeholder;
                $values[$placeholder] = [
                    'value' => $value,
                    'type' => $this->getPdoType($table->getColumnByIndex($colIndex)->getType()),
                ];
            }
            $placeholderRows[] = '('.implode(', ', $placeholders).')';
        }

        $sql = \sprintf(
            'INSERT INTO "%s" (%s) VALUES %s',
            $table->getName(),
            $columnList,
            implode(', ', $placeholderRows)
        );

        $stmt = $this->connection->prepare($sql);

        foreach ($values as $placeholder => $data) {
            $stmt->bindValue($placeholder, $data['value'], $data['type']);
        }

        $stmt->executeStatement();
    }

    /**
     * Obtenir le type PDO approprié
     */
    private function getPdoType(string $columnType): int
    {
        switch (strtoupper($columnType)) {
            case TypeGuesser::INTEGER:
                return \PDO::PARAM_INT;

            case TypeGuesser::BIGINT:
            case TypeGuesser::DECIMAL:
            case TypeGuesser::NUMERIC:
                // PDO doesn't have numeric type
                return \PDO::PARAM_STR;

            default:
                return \PDO::PARAM_STR;
        }
    }

    /**
     * Saves the table for automatic cleaning
     */
    private function registerTable(string $tableName): void
    {
        $sql = 'INSERT INTO temp_table_registry (table_name, created_at) VALUES (:table_name, CURRENT_TIMESTAMP)
                ON CONFLICT (table_name) DO UPDATE SET created_at = CURRENT_TIMESTAMP';

        try {
            $this->connection->executeStatement($sql, ['table_name' => $tableName]);
        } catch (Exception $e) {
            throw new Exception('register table error');
        }
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
                ++$cleanedCount;
            } catch (\Exception $e) {
                $this->logger->error("Erreur lors de la suppression de la table {$tableName}: ".$e->getMessage());
            }
        }

        return $cleanedCount;
    }

    /**
     * Delete a temp table
     */
    public function dropTable(string $tableName): void
    {
        $this->connection->executeStatement(\sprintf('DROP TABLE IF EXISTS "%s"', $tableName));
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
        $sql = 'CREATE TABLE IF NOT EXISTS temp_table_registry (
            table_name VARCHAR(255) PRIMARY KEY,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )';

        try {
            $this->connection->executeStatement($sql);
        } catch (Exception $e) {
            throw new Exception('initializeRegistry error');
        }
    }

    /**
     * Extraire le nom de table d'une commande COPY
     */
    private function getTableNameOnly(string $copyCommand): string
    {
        if (preg_match('/COPY\s+"?([^"(\s]+)"?\s*\(/i', $copyCommand, $matches)) {
            return $matches[1];
        }

        throw new \RuntimeException('Impossible d\'extraire le nom de table de la commande COPY');
    }

    /**
     * Version simplifiée et plus robuste de l'import
     */
    private function importCsvViaCopyFromSimple(string $csvFilePath, Table $table, string $delimiter): void
    {
        // Solution de fallback recommandée : utiliser PDO avec COPY FROM fichier
        $pdo = $this->connection->getNativeConnection();

        if (!($pdo instanceof \PDO) || 'pgsql' !== $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            throw new \RuntimeException('Cette méthode nécessite une connexion PostgreSQL PDO');
        }

        // Créer un fichier temporaire sans header
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_import_');
        $originalHandle = fopen($csvFilePath, 'r');
        $tempHandle = fopen($tempFile, 'w');
        // Skip header
        $header = fgetcsv($originalHandle, 0, $delimiter);
        // dump('Processing file, header:', $header);
        // fclose($originalHandle);
        fclose($tempHandle);

        try {
            $columns = array_map(static fn ($col) => '"'.$col->getName().'"', $table->getColumns()->toArray());
            $columnList = implode(', ', $columns);

            $copyCommand = \sprintf(
                "COPY \"%s\" (%s) FROM '%s' WITH (FORMAT CSV, DELIMITER '%s', NULL '', QUOTE '\"', HEADER true)",
                $table->getFullName(),
                $columnList,
                $csvFilePath,
                $delimiter
            );
            $result = $this->connection->executeStatement($copyCommand);
        }catch(\Doctrine\DBAL\Exception $e){
            dump($e->getMessage());
            throw new \Doctrine\DBAL\Exception($e->getMessage());
        } finally {
            unlink($tempFile);
        }
    }
}
