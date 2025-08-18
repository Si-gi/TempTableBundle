<?php
declare(strict_types=1);
namespace Sigi\TempTableBundle\Service;

use Doctrine\DBAL\Connection;

class TempTableQuery
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Executes a SELECT query on a temporary table
     */
    public function query(string $tableName, array $conditions = [], ?int $limit = null, int $offset = 0): array
    {
        [$sql, $params] = $this->buildSelectQuery($tableName, $conditions, $limit, $offset);

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    /**
     * Build the SQL query and params for selecting from a temporary table.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function buildSelectQuery(string $tableName, array $conditions = [], ?int $limit = null, int $offset = 0): array
    {
        $sql = sprintf('SELECT * FROM "%s"', $tableName);
        $params = [];

        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $column => $value) {
                $whereClause[] = sprintf('"%s" = :%s', $column, $column);
                $params[$column] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $whereClause);
        }

        if ($limit) {
            $sql .= ' LIMIT ' . $limit;
        }

        if ($offset > 0) {
            $sql .= ' OFFSET ' . $offset;
        }

        return [$sql, $params];
    }

    /**
     * Count records
     */
    public function count(string $tableName, array $conditions = []): int
    {
        $sql = sprintf('SELECT COUNT(*) FROM "%s"', $tableName);
        $params = [];

        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $column => $value) {
                $whereClause[] = sprintf('"%s" = :%s', $column, $column);
                $params[$column] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $whereClause);
        }

        return (int) $this->connection->fetchOne($sql, $params);
    }

    public function executeQuery(string $sql, array $params = []): array
    {
        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function getTableStructure(string $tableName): array
    {
        $sql = "SELECT column_name, data_type, is_nullable 
                FROM information_schema.columns 
                WHERE table_name = :table_name 
                ORDER BY ordinal_position";
        
        return $this->connection->fetchAllAssociative($sql, ['table_name' => $tableName]);
    }
}