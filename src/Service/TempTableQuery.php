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
    private QueryBuilder $qb;
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getQb(): QueryBuilder
    {
        return $this->qb;
    }
    /**
     * Exécute une requête SELECT sur une table temporaire
     */
    public function query(string $tableName, array $columnsNames = [], ?int $limit = null, int $offset = 0): self
    {
        $this->qb = $this->createSelectQueryBuilder($tableName);
        if (empty($columnsNames)){
            $this->qb->select('*');
        } else {
            $this->qb->select(implode(',', $columnsNames));
        }

        // $this->addConditions($this->qb, $conditions);

        if (null !== $limit) {
            $this->qb->setMaxResults($limit);
        }

        if ($offset > 0) {
            $this->qb->setFirstResult($offset);
        }

        return $this;
    }

    /**
     * Count rows
     */
    public function count(string $tableName, array $conditions = []): int
    {
        $this->qb = $this->createSelectQueryBuilder($tableName)
            ->select('COUNT(*)')
        ;

        $this->addConditions($conditions);

        return (int) $this->qb->executeQuery()->fetchOne();
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
        $this->qb = $this->connection->createQueryBuilder()
            ->select('column_name', 'data_type', 'is_nullable')
            ->from('information_schema.columns')
            ->where('table_name = :table_name')
            ->orderBy('ordinal_position')
            ->setParameter('table_name', $tableName)
        ;

        return $this->qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Recherche avec LIKE sur plusieurs colonnes
     */
    public function search(string $tableName, string $searchTerm, array $searchColumns, ?int $limit = null): array
    {
        $this->qb = $this->createSelectQueryBuilder($tableName)
            ->select('*')
        ;

        if (!empty($searchColumns)) {
            $orConditions = [];
            foreach ($searchColumns as $index => $column) {
                $paramName = 'search_'.$index;
                $orConditions[] = $this->qb->expr()->like(
                    $this->quoteName($column),
                    ':'.$paramName
                );
                $this->qb->setParameter($paramName, '%'.$searchTerm.'%');
            }
            $this->qb->where($this->qb->expr()->or(...$orConditions));
        }

        if (null !== $limit) {
            $this->qb->setMaxResults($limit);
        }

        return $this->qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Récupère des statistiques sur une colonne
     */
    public function getColumnStatistics(string $tableName, string $column): array
    {
        $quotedColumn = $this->quoteName($column);

        $this->qb = $this->connection->createQueryBuilder()
            ->select(
                'COUNT(*) as total',
                'COUNT(DISTINCT '.$quotedColumn.') as unique_values',
                'COUNT('.$quotedColumn.') as non_null_values',
                'MIN('.$quotedColumn.') as min_value',
                'MAX('.$quotedColumn.') as max_value'
            )
            ->from($this->quoteName($tableName))
        ;

        return $this->qb->executeQuery()->fetchAssociative();
    }

    /**
     * Récupère les valeurs distinctes d'une colonne
     */
    public function getDistinctValues(string $tableName, string $column, ?int $limit = null): array
    {
        $this->qb = $this->createSelectQueryBuilder($tableName)
            ->select('DISTINCT '.$this->quoteName($column))
            ->orderBy($this->quoteName($column))
        ;

        if (null !== $limit) {
            $this->qb->setMaxResults($limit);
        }

        return array_column($this->qb->executeQuery()->fetchAllAssociative(), $column);
    }

    /**
     * Helper pour créer un QueryBuilder avec la table quotée
     */
    private function createSelectQueryBuilder(string $tableName): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->from($this->quoteName($tableName))
        ;
    }

    /**
     * Ajoute des conditions WHERE simples au QueryBuilder
     */
    public function addConditions(array &$conditions): self
    {
        foreach ($conditions as $column => $value) {
            $paramName = 'param_'.$column;
            $this->qb->andWhere($this->quoteName($column).' = :'.$paramName)
                ->setParameter($paramName, $value)
            ;
        }

        return $this;
    }

    /**
     * Quote un nom de table/colonne pour PostgreSQL
     */
    private function quoteName(string $name): string
    {
        return $this->connection->quoteIdentifier($name);
    }
}
