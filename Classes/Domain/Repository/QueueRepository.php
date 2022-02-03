<?php

/**
 * QueueRepository.
 */

declare(strict_types=1);

namespace SFC\Staticfilecache\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * QueueRepository.
 */
class QueueRepository extends AbstractRepository
{
    /**
     * Find the entries for the worker.
     *
     * @param int $limit
     */
    public function findOpen(int $limit = 99999999): array
    {
        $queryBuilder = $this->createQuery();

        return $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where(
                $queryBuilder->expr()->eq('call_date', 0),
                $queryBuilder->expr()->isNull('error'),
            )
            ->setMaxResults($limit)
            ->orderBy('cache_priority', 'desc')
            ->execute()
            ->fetchAllAssociative()
        ;
    }

    /**
     * @param array $identifier
     * @return array<string>
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function increaseCachePriorityByIdentifiers(array $identifiers): array
    {
        $c = count($identifiers);
        if ($c === 0) {
            return [];
        }

        $queryBuilder = $this->createQuery();
        for ($i = 0; $i < $c; $i++) {
            $identifiers[$i] = $queryBuilder->quote($identifiers[$i]);
        }

        $where = $queryBuilder->expr()->andX(
            $queryBuilder->expr()->in('identifier', $identifiers)
        );

        $queryBuilder->update($this->getTableName())
            ->set('call_result', '')
            ->set('cache_priority', 'cache_priority + 1', false, \PDO::PARAM_STMT)
            ->where($where)
            ->execute();

        $queryBuilder = $this->createQuery();
        return $queryBuilder->

        select('*')
            ->from($this->getTableName())
            ->where($where)
            ->execute()
            ->fetchAllAssociative() ?: []
        ;
    }

    public function findOld(): array
    {
        $queryBuilder = $this->createQuery();

        return $queryBuilder->select('uid')
            ->from($this->getTableName())
            ->where(
                $queryBuilder->expr()->isNull('error'),
                $queryBuilder->expr()->neq('call_result', $queryBuilder->quote(''))
            )
            ->execute()
            ->fetchAllAssociative();
    }

    public function findError(): array
    {
        $queryBuilder = $this->createQuery();

        return $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where(
                $queryBuilder->expr()->isNotNull('error'),
            )
            ->execute()
            ->fetchAllAssociative();
    }

    public function findByStatus(int $status): array
    {
        $queryBuilder = $this->createQuery();

        return $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where(
                $queryBuilder->expr()->eq('call_result', $status)
            )
            ->execute()
            ->fetchAllAssociative();
    }

    public function findStatistical()
    {
        $queryBuilder = $this->createQuery();
        return $queryBuilder->select('*')
            ->from($this->getTableName())
            ->orderBy('error', 'DESC')
            ->addOrderBy('cache_priority')
            ->addOrderBy('call_result')
            ->execute();
    }

    /**
     * Get the table name.
     */
    protected function getTableName(): string
    {
        return 'tx_staticfilecache_queue';
    }
}
