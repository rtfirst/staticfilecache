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
            ->where($queryBuilder->expr()->eq('call_date', 0))
            ->setMaxResults($limit)
            ->orderBy('cache_priority', 'desc')
            ->execute()
            ->fetchAllAssociative()
        ;
    }

    public function findByIdentifier(string $identifier): array
    {
        $queryBuilder = $this->createQuery();
        $where = $queryBuilder->expr()->andX(
            $queryBuilder->expr()->eq('identifier', $queryBuilder->createNamedParameter($identifier))
        );

        return $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where($where)
            ->execute()
            ->fetchAssociative() ?: []
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
