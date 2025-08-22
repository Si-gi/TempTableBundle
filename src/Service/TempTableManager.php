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
        $this->connection->executeStatement('DROP TABLE IF EXISTS temp_csv_test');
        $this->initializeRegistry();

        try {
            $table = $this->tableFactory->analyzeStructure($csvFilePath, $delimiter, $this->tablePrefix.$tableName);

            $query = $this->createTableQuery($table);
            $this->connection->executeStatement($query);

            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'temp_csv_test'";

            dump($this->connection->fetchAllAssociative($sql));

            dump('table created');
            $this->logger->info('Table created '.$table->getName());

            // Import Data with COPY (faster than INSERT)
            $this->importCsvData($csvFilePath, $table->getName(), $table, $delimiter);
            dump('table imported');

            // $this->registerTable($table->getName());

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
            $columns[] = \sprintf('"%s" %s', $column->getName(), $column->getType());
        }

        $sql = \sprintf(
            'CREATE TABLE IF NOT EXISTS "%s" (
                %s
            )',
            $table->getName(),
            implode(",\n                ", $columns)
        );
        dump($sql);

        return $sql;
    }

    /**
     * Import data with proper type detection and COPY FROM optimization
     */
    private function importCsvData(string $csvFilePath, string $tableName, Table $table, ?string $delimiter = null): void
    {
        $delimiter ??= ',';

        $this->connection->beginTransaction();

        try {
            dump("COPY temp_csv_test(identifiant_pp, pr__nom_d_exercice, nom_d_exercice, adresse, telephone)
        FROM '{$csvFilePath}'
        WITH (FORMAT CSV, HEADER TRUE, DELIMITER ',', NULL '', QUOTE '\"')");

            $this->connection->executeStatement("
        COPY temp_csv_test(identifiant_pp, pr__nom_d_exercice, nom_d_exercice, adresse, telephone)
        FROM '{$csvFilePath}'
        WITH (FORMAT CSV, HEADER TRUE, DELIMITER ',', NULL '', QUOTE '\"')
    ");
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }
        // try {
        //     dump('Début import, taille fichier:', filesize($csvFilePath));

        //     if ($this->canUseCopyFrom()) {
        //         dump('COPY FROM disponible, tentative...');
        //         try {
        //             $this->importCsvViaCopyFrom($csvFilePath, $tableName, $table, $delimiter);
        //             dump('COPY FROM réussi !');
        //         } catch (\Exception $e) {
        //             dump('COPY FROM échoué, fallback batch insert:', $e->getMessage());
        //             $this->importCsvViaBatchInsertTyped($csvFilePath, $table, $delimiter);
        //         }
        //     } else {
        //         dump('COPY FROM non disponible, utilisation batch insert');
        //         $this->importCsvViaBatchInsertTyped($csvFilePath, $table, $delimiter);
        //     }

        //     $this->connection->commit();
        //     dump('Transaction committée avec succès');
        // } catch (\Exception $e) {
        //     // $this->connection->rollBack();
        //     dump('Rollback effectué:', $e->getMessage());
        //     throw $e;
        // }
    }

    /**
     * Verify if COPY FROM is available
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
     * Import optimisé avec COPY FROM
     */
    private function importCsvViaCopyFrom(string $csvFilePath, string $tableName, Table $table, string $delimiter): void
    {
        dump('Début import COPY FROM');

        // Essayer la méthode PDO simple en premier (plus fiable)
        try {
            $this->importCsvViaCopyFromSimple($csvFilePath, $tableName, $table, $delimiter);
            dump('Import COPY FROM terminé avec succès');

            return;
        } catch (\Exception $e) {
            dump('Échec PDO COPY FROM, tentative pg_connect:', $e->getMessage());
        }

        // Fallback sur pg_connect seulement si PDO échoue
        if (\function_exists('pg_connect')) {
            try {
                $columns = array_map(static fn ($col) => '"'.$col->getName().'"', $table->getColumns()->toArray());
                $columnList = implode(', ', $columns);
                $copyCommand = \sprintf(
                    "COPY \"%s\" (%s) FROM %s WITH (FORMAT CSV, HEADER true, DELIMITER '%s', ENCODING 'UTF8')",
                    $tableName,
                    $columnList,
                    $csvFilePath,
                    $delimiter
                );
                dump($copyCommand);

                $this->importViaPgCopyFrom($copyCommand, $csvFilePath, $delimiter);

                return;
            } catch (\Exception $e) {
                dump('Échec pg_connect COPY FROM:', $e->getMessage());
            }
        }

        // Si tout échoue, lever une exception pour utiliser le fallback
        throw new \RuntimeException('COPY FROM non disponible, utilisation du fallback batch insert');
    }

    /**
     * COPY FROM via les fonctions PostgreSQL natives (recommandé)
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
            throw new \RuntimeException('Impossible de se connecter à PostgreSQL pour COPY FROM');
        }
        dump('Connexion établie');

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

        dump('Header skipped:', $header);

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

            dump('Commande COPY:', $copyFromFileCommand);

            $result = pg_query($pgConn, $copyFromFileCommand);

            if (!$result) {
                throw new \RuntimeException('Erreur lors de COPY FROM: '.pg_last_error($pgConn));
            }

            $affectedRows = pg_affected_rows($result);
            dump("Lignes importées: {$affectedRows}");
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
     * COPY FROM via PDO (plus complexe mais plus portable)
     */
    private function importViaPdoCopyFrom(string $copyCommand, string $csvFilePath, string $delimiter): void
    {
        // Cette approche nécessite des drivers PostgreSQL spéciaux
        // En pratique, on utilise souvent un fichier temporaire

        $pdo = $this->connection->getNativeConnection();

        // Préparer le contenu CSV pour COPY FROM
        $handle = fopen($csvFilePath, 'r');
        $header = fgetcsv($handle, 0, $delimiter); // Skip header

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_import_');
        $tempHandle = fopen($tempFile, 'w');

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Nettoyer les données pour PostgreSQL
            $cleanedRow = array_map(static function ($value) {
                if (null === $value || '' === $value) {
                    return ''; // PostgreSQL comprendra comme NULL avec NULL ''
                }

                return (string) $value;
            }, $row);

            fputcsv($tempHandle, $cleanedRow, $delimiter);
        }

        fclose($handle);
        fclose($tempHandle);

        // Utiliser COPY FROM avec le fichier temporaire
        $copyCommand = str_replace('FROM STDIN', "FROM '{$tempFile}'", $copyCommand);

        try {
            $pdo->exec($copyCommand);
        } finally {
            unlink($tempFile);
        }
    }

    /**
     * Fallback : Batch inserts avec gestion des types appropriés
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
            case 'INTEGER':
                return false !== filter_var($value, \FILTER_VALIDATE_INT) ? (int) $value : null;

            case 'BIGINT':
                // Pour les BIGINT, garder en string pour éviter les problèmes de précision PHP
                return is_numeric($value) ? $value : null;

            case 'DECIMAL':
            case 'NUMERIC':
                return is_numeric($value) ? $value : null;

            case 'TIMESTAMP':
            case 'DATE':
                $timestamp = strtotime($value);

                return false !== $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;

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
                // Pour BIGINT et DECIMAL, utiliser STRING pour éviter les problèmes de précision
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
    private function importCsvViaCopyFromSimple(string $csvFilePath, string $tableName, Table $table, string $delimiter): void
    {
        // Solution de fallback recommandée : utiliser PDO avec COPY FROM fichier
        $pdo = $this->connection->getNativeConnection();

        if (!($pdo instanceof \PDO) || 'pgsql' !== $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            throw new \RuntimeException('Cette méthode nécessite une connexion PostgreSQL PDO');
        }

        // Créer un fichier temporaire sans header
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_import_');
        // $originalHandle = fopen($csvFilePath, 'r');
        $tempHandle = fopen($tempFile, 'w');

        // Skip header
        // $header = fgetcsv($originalHandle, 0, $delimiter);
        // dump('Processing file, header:', $header);

        $lineCount = 0;
        // while (($row = fgetcsv($originalHandle, 0, $delimiter)) !== false) {
        //     // Ajuster le nombre de colonnes selon la structure
        //     while(count($row) > count($table->getColumns())) {
        //         array_pop($row);
        //     }
        //     while(count($row) < count($table->getColumns())) {
        //         $row[] = '';
        //     }

        //     // Nettoyer les données
        //     $cleanedRow = array_map(function($value) {
        //         return $value === null ? '' : (string)$value;
        //     }, $row);

        //     fputcsv($tempHandle, $cleanedRow, $delimiter, '"');
        //     $lineCount++;
        // }

        // fclose($originalHandle);
        fclose($tempHandle);

        dump("Fichier préparé avec {$lineCount} lignes");

        try {
            $columns = array_map(static fn ($col) => '"'.$col->getName().'"', $table->getColumns()->toArray());
            $columnList = implode(', ', $columns);
            // $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'temp_csv_test'";

            // dump($this->connection->fetchAllAssociative($sql));
            // Utiliser une requête COPY FROM simple
            $copyCommand = \sprintf(
                "COPY \"%s\" (%s) FROM '%s' WITH (FORMAT CSV, DELIMITER '%s', NULL '', QUOTE '\"', HEADER true)",
                $tableName,
                $columnList,
                $csvFilePath, // Échapper les backslashes pour Windows
                $delimiter
            );

            dump('Executing COPY command...');
            dump($copyCommand);
            $result = $this->connection->executeStatement($copyCommand);
            dump("Import terminé, résultat: {$result}");
        } finally {
            unlink($tempFile);
        }
    }
}
