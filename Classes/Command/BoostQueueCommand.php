<?php

/**
 * BoostQueueRunCommand.
 */
declare(strict_types=1);

namespace SFC\Staticfilecache\Command;

use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use SFC\Staticfilecache\Domain\Repository\QueueRepository;
use SFC\Staticfilecache\Event\PoolEvent;
use SFC\Staticfilecache\Service\ClientService;
use SFC\Staticfilecache\Service\ConfigurationService;
use SFC\Staticfilecache\Service\QueueService;
use SFC\Staticfilecache\Service\SystemLoadService;
use Spatie\Async\Pool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use function json_decode;
use function json_encode;

class BoostQueueCommand extends AbstractCommand
{
    protected QueueRepository $queueRepository;
    protected QueueService $queueService;
    protected ClientService $clientService;
    protected Pool $pool;
    protected SystemLoadService $systemLoadService;
    protected int $actualConcurrency = 1;
    protected int $concurrency = 1;
    protected SymfonyStyle $io;
    protected bool $hasPool = false;

    public function __construct(
        QueueRepository      $queueRepository,
        QueueService         $queueService,
        SystemLoadService    $systemLoadService,
        ClientService        $clientService,
        ConfigurationService $configurationService
    )
    {
        $this->queueRepository = $queueRepository;
        $this->queueService = $queueService;
        $this->systemLoadService = $systemLoadService;
        $this->clientService = $clientService;
        $this->concurrency = (int)$configurationService->get('concurrency');
        parent::__construct('staticfilecache:boostQueue');
    }

    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Run (work on) the cache boost queue. Call this task every 5 minutes.')
            ->addOption('avoid-cleanup', null, InputOption::VALUE_NONE, 'Avoid the cleanup of the queue items')
            ->addOption('limit-items', null, InputOption::VALUE_REQUIRED, 'Limit the items that are crawled. 0 => all', 1000)
            ->addOption('concurrency', null, InputOption::VALUE_OPTIONAL, 'If concurrent mode is enabled spawns up to N-threads');
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @return null|int null or 0 if everything went fine, or an error code
     *
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     * @throws \Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     *
     * @see setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->io = $io;

        $list = [];
        exec('find /proc -mindepth 2 -maxdepth 2 -name cmdline -print0|xargs  -0 -n1', $list);
        $c = 0;
        foreach ($list as $file) {
            if (file_exists($file) && is_readable($file)) {
                $c += preg_match('/staticfilecache:boostQueue/', file_get_contents($file));
            }
        }

        if ($c > 1) {
            $io->isVerbose() && $io->error('Process already running');
            return Command::FAILURE;
        }

        // reset picked items as we assume this command is the master
        $io->note('Reset items which are picked by a process and were not done for whatever reason');
        $entries = $this->queueRepository->findStatistical();
        while ($row = $entries->fetchAssociative()) {
            $row['call_date'] = 0;
            $this->queueRepository->update($row);
        }

        if ($input->getOption('concurrency')) {
            $this->concurrency = (int)$input->getOption('concurrency');
        }

        if ($this->hasAsyncAbility()) {
            $this->createPool();
            if ($this->hasPool) {
                if ($this->systemLoadService->enabled()) {
                    $io->note('Ability to lower/raise concurrency enabled, up to ' . $this->concurrency . ' child processes');
                } else {
                    $io->note('Ability to lower/raise concurrency is not available');
                    $this->actualConcurrency = $this->concurrency;
                }
            }
        } else {
            $io->note('Async ability not available');
        }

        if (!(bool)$input->getOption('avoid-cleanup')) {
            $this->cleanupQueue($io);
        }

        $limit = (int)$input->getOption('limit-items');
        $limit = $limit > 0 ? $limit : 99999999;
        $rows = $this->queueRepository->findOpen($limit);

        $io->progressStart(\count($rows));
        foreach ($rows as $runEntry) {
            $runEntry['call_date'] = time();
            $this->queueRepository->update($runEntry);

            if (!$this->hasPool) {
                $this->queueService->runSingleRequest($runEntry);
                $this->io->progressAdvance();
                continue;
            }

            $url = $runEntry['url'];
            $host = parse_url($url, PHP_URL_HOST);
            if (false === $host) {
                throw new Exception('No host in url', 1263782);
            }
            $client = $this->clientService->getCallableClient($host);
            $this->queueService->removeFromCache($runEntry);

            $this->feedPool(
                static function () use ($client, $url, $runEntry): string {
                    /** @noinspection JsonEncodingApiUsageInspection */
                    return json_encode([
                       'code' => $client->get($url)->getStatusCode(),
                       'runEntry' => $runEntry,
                   ]);
                },
                function (string $pack): string {
                    if ($this->systemLoadService->enabled()) {
                        if ($this->systemLoadService->loadExceeded()) {
                            $this->lowerPoolConcurrency();
                            $this->io->isVerbose() && $this->io->note('Waiting some seconds');
                            $this->systemLoadService->wait();
                        } else {
                            $this->raisePoolConcurrency();
                        }
                    }

                    if ($this->hasPool) {
                        $this->pool->notify();
                    }

                    $this->dispatchPoolEvent();

                    /** @noinspection JsonEncodingApiUsageInspection */
                    $parts = json_decode($pack, true);
                    $message = '';
                    if ($parts) {
                        $this->queueService->setResult($parts['runEntry'], $parts['code'] ?: 900);
                        $message = $parts['runEntry']['url'] . ' returned status code ' . $parts['code'];
                    }

                    $this->io->progressAdvance();
                    return $message;
                },
                $runEntry
            );
        }

        if ($this->hasPool) {
            $this->pool->wait();
        }

        $io->progressFinish();
        $io->success(\count($rows) . ' items are done (perhaps not all are processed).');

        if (!(bool)$input->getOption('avoid-cleanup')) {
            $this->cleanupQueue($io);
        }

        $this->dispatchPoolEvent();

        return Command::SUCCESS;
    }

    protected function dispatchPoolEvent(): void
    {
        $event = new PoolEvent([
            'actualConcurrency' => $this->actualConcurrency
        ]);
        $eventDispatcher = GeneralUtility::makeInstance(ObjectManager::class)->get(EventDispatcherInterface::class);
        $eventDispatcher->dispatch($event);
    }

    protected function hasAsyncAbility(): bool
    {
        /** @noinspection ClassConstantCanBeUsedInspection */
        return class_exists('\Spatie\Async\Pool');
    }

    protected function createPool(): void
    {
        if ($this->concurrency > 1 && $this->hasAsyncAbility()) {
            $this->pool = Pool::create();
            $this->pool->concurrency($this->actualConcurrency);
            $this->pool->timeout(120);
            $this->hasPool = true;
            $this->io->note('Created async pool');
        }
    }

    protected function feedPool(callable $callable, callable $callback, array $runEntry): void
    {
        $this->pool->add($callable)->then(
            function (string $output) use ($callback) {
                $this->io->isVerbose() && $this->io->note($output);
                $callbackOutput = $callback($output);
                $this->io->isVerbose() && $this->io->note($callbackOutput);
            }
        )->catch(
            function (Throwable $exception) use ($runEntry) {
                $this->io->error($exception->getMessage());
                $runEntry['error'] = $exception->getMessage();
                $this->queueRepository->update($runEntry);
            }
        )->timeout(
            function () use ($runEntry) {
                $message = 'Timeout in boostqueue';
                $this->io->error($message);
                $runEntry['error'] = $message;
                $this->queueRepository->update($runEntry);
            }
        );
    }

    protected function lowerPoolConcurrency(): bool
    {
        if ($this->actualConcurrency < 2) {
            return false;
        }
        $this->actualConcurrency--;
        $this->io->isVerbose() && $this->io->note('Actual concurrency lowered to ' . $this->actualConcurrency);
        $this->pool->concurrency($this->actualConcurrency);
        return true;
    }

    protected function raisePoolConcurrency(): bool
    {
        if ($this->actualConcurrency >= $this->concurrency) {
            return false;
        }

        $this->actualConcurrency++;
        $this->io->isVerbose() && $this->io->note('Actual concurrency raised to ' . $this->actualConcurrency);
        $this->pool->concurrency($this->actualConcurrency);
        return true;
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    protected function cleanupQueue(SymfonyStyle $io): void
    {
        $rows = $this->queueRepository->findOld();
        $io->progressStart(\count($rows));
        foreach ($rows as $row) {
            $this->queueRepository->delete(['uid' => $row['uid']]);
            $io->progressAdvance();
        }
        $io->progressFinish();
        $io->success(\count($rows) . ' items are removed.');
    }
}
