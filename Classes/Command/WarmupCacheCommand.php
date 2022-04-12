<?php

declare(strict_types=1);

namespace SFC\Staticfilecache\Command;

use Exception;
use SFC\Staticfilecache\Service\ConfigurationService;
use SFC\Staticfilecache\Service\QueueService;
use SFC\Staticfilecache\Utility\UriUtility;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;


class WarmupCacheCommand extends AbstractCommand
{
    protected Connection $connection;
    protected UriUtility $uriUtility;
    protected QueueService $queueService;
    protected bool $isBoostMode;
    protected SiteFinder $siteFinder;

    public function __construct(ConnectionPool $connectionPool, UriUtility $uriUtility, QueueService $queueService, ConfigurationService $configurationService, SiteFinder $siteFinder)
    {
        parent::__construct('staticfilecache:warmup');
        $this->connection = $connectionPool->getConnectionForTable('pages');
        $this->uriUtility = $uriUtility;
        $this->queueService = $queueService;
        $this->isBoostMode = (bool)$configurationService->get('boostMode');
        $this->siteFinder = $siteFinder;
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->isBoostMode) {
            $output->writeln('Boostmode is not active');
            return self::FAILURE;
        }

        $queryBuilder = $this->connection->createQueryBuilder();
        $result = $queryBuilder->select('uid')->from('pages')->execute();
        while ($row = $result->fetchAssociative()) {
            try {
                $pageUid = $row['uid'];
                $site = $this->siteFinder->getSiteByPageId($pageUid);
                $host = $site->getBase()->getHost();
                if ($host !== $_SERVER['HTTP_HOST']) {
                    $_SERVER['HTTP_HOST'] = $host;
                    GeneralUtility::flushInternalRuntimeCaches();
                }
                $urls = $this->uriUtility->generate($pageUid);
                $this->queueService->addUrls($urls);
            } catch (Exception $exception) {
                $output->writeln($exception->getMessage());
                continue;
            }
        }
        return self::SUCCESS;
    }
}
