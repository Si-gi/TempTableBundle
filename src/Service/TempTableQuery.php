<?php
declare(strict_types=1);
namespace Sigi\TempTableBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Service pour exécuter des requêtes sur les tables temporaires
 * Utilise le QueryBuilder natif de Doctrine DBAL
 */
class TempTableQuery
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Exécute une requête SELECT sur une table temporaire
     */
    public function query(string $tableName, array $conditions = [], ?int $limit = null, int $offset = 0): array
    {
        $qb = $this->createSelectQueryBuilder($tableName)
            ->select('*');

        $this->addConditions($qb, $conditions);

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        if ($offset > 0) {
            $qb->setFirstResult($offset);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Compte les enregistrements
     */
    public function count(string $tableName, array $conditions = []): int
    {
        $qb = $this->createSelectQueryBuilder($tableName)
            ->select('COUNT(*)');

        $this->addConditions($qb, $conditions);

        return (int) $qb->executeQuery()->fetchOne();
    }

    /**
     * Crée un QueryBuilder pré-configuré pour une table
     */
    public function createQueryBuilder(string $tableName): QueryBuilder
    {
        return $this->createSelectQueryBuilder($tableName);
    }

    /**
     * Exécute une requête SQL personnalisée
     */
    public function executeRawQuery(string $sql, array $params = []): array
    {
        return $this->connection->executeQuery($sql, $params)->fetchAllAssociative();
    }

    /**
     * Exécute un QueryBuilder Doctrine
     */
    public function executeQueryBuilder(QueryBuilder $queryBuilder): array
    {
        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * Exécute un QueryBuilder et retourne une seule valeur
     */
    public function executeQueryBuilderSingle(QueryBuilder $queryBuilder): mixed
    {
        return $queryBuilder->executeQuery()->fetchOne();
    }

    /**
     * Récupère la structure de la table
     */
    public function getTableStructure(string $tableName): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('column_name', 'data_type', 'is_nullable')
            ->from('information_schema.columns')
            ->where('table_name = :table_name')
            ->orderBy('ordinal_position')
            ->setParameter('table_name', $tableName);

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Recherche avec LIKE sur plusieurs colonnes
     */
    public function search(string $tableName, string $searchTerm, array $searchColumns, ?int $limit = null): array
    {
        $qb = $this->createSelectQueryBuilder($tableName)
            ->select('*');

        if (!empty($searchColumns)) {
            $orConditions = [];
            foreach ($searchColumns as $index => $column) {
                $paramName = 'search_' . $index;
                $orConditions[] = $qb->expr()->like(
                    $this->quoteName($column),
                    ':' . $paramName
                );
                $qb->setParameter($paramName, '%' . $searchTerm . '%');
            }
            $qb->where($qb->expr()->or(...$orConditions));
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Récupère des statistiques sur une colonne
     */
    public function getColumnStatistics(string $tableName, string $column): array
    {
        $quotedColumn = $this->quoteName($column);
        
        $qb = $this->connection->createQueryBuilder()
            ->select(
                'COUNT(*) as total',
                'COUNT(DISTINCT ' . $quotedColumn . ') as unique_values',
                'COUNT(' . $quotedColumn . ') as non_null_values',
                'MIN(' . $quotedColumn . ') as min_value',
                'MAX(' . $quotedColumn . ') as max_value'
            )
            ->from($this->quoteName($tableName));

        return $qb->executeQuery()->fetchAssociative();
    }

    /**
     * Récupère les valeurs distinctes d'une colonne
     */
    public function getDistinctValues(string $tableName, string $column, ?int $limit = null): array
    {
        $qb = $this->createSelectQueryBuilder($tableName)
            ->select('DISTINCT ' . $this->quoteName($column))
            ->orderBy($this->quoteName($column));

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return array_column($qb->executeQuery()->fetchAllAssociative(), $column);
    }

    /**
     * Helper pour créer un QueryBuilder avec la table quotée
     */
    private function createSelectQueryBuilder(string $tableName): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->from($this->quoteName($tableName));
    }

    /**
     * Ajoute des conditions WHERE simples au QueryBuilder
     */
    private function addConditions(QueryBuilder $qb, array $conditions): void
    {
        foreach ($conditions as $column => $value) {
            $paramName = 'param_' . $column;
            $qb->andWhere($this->quoteName($column) . ' = :' . $paramName)
               ->setParameter($paramName, $value);
        }
    }

    /**
     * Quote un nom de table/colonne pour PostgreSQL
     */
    private function quoteName(string $name): string
    {
        return $this->connection->quoteIdentifier($name);
    }
}