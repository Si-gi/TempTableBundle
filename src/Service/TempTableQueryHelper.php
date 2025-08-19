<?php

namespace Sigi\TempTableBundle\Service;

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Helper pour construire des requêtes complexes sur les tables temporaires
 */
class TempTableQueryHelper
{
    private TempTableQuery $tempTableQuery;

    public function __construct(TempTableQuery $tempTableQuery)
    {
        $this->tempTableQuery = $tempTableQuery;
    }

    /**
     * Construit une requête paginée avec filtres
     */
    public function createPaginatedQuery(
        string $tableName,
        array $filters = [],
        array $orderBy = [],
        ?int $page = null,
        int $perPage = 50
    ): array {
        $qb = $this->tempTableQuery->createQueryBuilder($tableName)
            ->select('*');

        // Ajouter les filtres
        $this->applyFilters($qb, $filters);

        // Ajouter le tri
        $this->applyOrderBy($qb, $orderBy);

        // Cloner pour le comptage
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(*)')->executeQuery()->fetchOne();

        // Appliquer la pagination
        if ($page !== null) {
            $offset = ($page - 1) * $perPage;
            $qb->setFirstResult($offset)->setMaxResults($perPage);
        }

        return [
            'data' => $qb->executeQuery()->fetchAllAssociative(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $page ? ceil($total / $perPage) : 1
        ];
    }

    /**
     * Requête avec agrégations
     */
    public function createAggregateQuery(string $tableName, array $groupBy, array $aggregations): array
    {
        $qb = $this->tempTableQuery->createQueryBuilder($tableName);

        // Ajouter les colonnes de groupement
        $selectParts = [];
        foreach ($groupBy as $column) {
            $selectParts[] = $this->quoteName($column);
        }

        // Ajouter les agrégations
        foreach ($aggregations as $alias => $expression) {
            $selectParts[] = $expression . ' as ' . $alias;
        }

        $qb->select(implode(', ', $selectParts));

        // GROUP BY
        foreach ($groupBy as $column) {
            $qb->addGroupBy($this->quoteName($column));
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Applique des filtres complexes
     */
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        foreach ($filters as $column => $filter) {
            if (is_array($filter)) {
                $this->applyComplexFilter($qb, $column, $filter);
            } else {
                // Filtre simple
                $qb->andWhere($this->quoteName($column) . ' = :' . $column)
                   ->setParameter($column, $filter);
            }
        }
    }

    /**
     * Applique un filtre complexe (opérateurs, plages, etc.)
     */
    private function applyComplexFilter(QueryBuilder $qb, string $column, array $filter): void
    {
        $quotedColumn = $this->quoteName($column);
        
        if (isset($filter['operator'])) {
            $paramName = $column . '_value';
            
            switch ($filter['operator']) {
                case 'like':
                    $qb->andWhere($quotedColumn . ' LIKE :' . $paramName)
                       ->setParameter($paramName, '%' . $filter['value'] . '%');
                    break;
                    
                case 'gt':
                    $qb->andWhere($quotedColumn . ' > :' . $paramName)
                       ->setParameter($paramName, $filter['value']);
                    break;
                    
                case 'gte':
                    $qb->andWhere($quotedColumn . ' >= :' . $paramName)
                       ->setParameter($paramName, $filter['value']);
                    break;
                    
                case 'lt':
                    $qb->andWhere($quotedColumn . ' < :' . $paramName)
                       ->setParameter($paramName, $filter['value']);
                    break;
                    
                case 'lte':
                    $qb->andWhere($quotedColumn . ' <= :' . $paramName)
                       ->setParameter($paramName, $filter['value']);
                    break;
                    
                case 'in':
                    $qb->andWhere($quotedColumn . ' IN (:' . $paramName . ')')
                       ->setParameter($paramName, $filter['value'], \Doctrine\DBAL\ArrayParameterType::STRING);
                    break;
                    
                case 'between':
                    $qb->andWhere($quotedColumn . ' BETWEEN :' . $column . '_min AND :' . $column . '_max')
                       ->setParameter($column . '_min', $filter['min'])
                       ->setParameter($column . '_max', $filter['max']);
                    break;
            }
        } elseif (isset($filter['min']) || isset($filter['max'])) {
            // Filtre de plage
            if (isset($filter['min'])) {
                $qb->andWhere($quotedColumn . ' >= :' . $column . '_min')
                   ->setParameter($column . '_min', $filter['min']);
            }
            if (isset($filter['max'])) {
                $qb->andWhere($quotedColumn . ' <= :' . $column . '_max')
                   ->setParameter($column . '_max', $filter['max']);
            }
        } elseif (isset($filter['values'])) {
            // Filtre IN
            $qb->andWhere($quotedColumn . ' IN (:' . $column . '_values)')
               ->setParameter($column . '_values', $filter['values'], \Doctrine\DBAL\ArrayParameterType::STRING);
        }
    }

    /**
     * Applique l'ordre de tri
     */
    private function applyOrderBy(QueryBuilder $qb, array $orderBy): void
    {
        foreach ($orderBy as $column => $direction) {
            $qb->addOrderBy($this->quoteName($column), strtoupper($direction));
        }
    }

    private function quoteName(string $name): string
    {
        return '"' . $name . '"';
    }
}