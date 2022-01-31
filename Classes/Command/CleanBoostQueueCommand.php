<?php

declare(strict_types=1);

namespace SFC\Staticfilecache\Command;

use SFC\Staticfilecache\Cache\IdentifierBuilder;
use SFC\Staticfilecache\Domain\Repository\QueueRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function count;

class CleanBoostQueueCommand extends AbstractCommand
{
    protected QueueRepository $queueRepository;

    public function __construct(QueueRepository $queueRepository)
    {
        $this->queueRepository = $queueRepository;
        parent::__construct('staticfilecache:cleanBoostQueue');
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('If an error occured in boostqueue those static files along with the queue entry get removed');
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rows = $this->queueRepository->findStatistical();
        foreach ($rows as $row) {
            $identifierBuilder = GeneralUtility::makeInstance(IdentifierBuilder::class);
            $fileName = $identifierBuilder->getFilepath($row['url']);
            $path = dirname($fileName);
            if (file_exists($path)) {
                $files = glob($path . '/index*');
                foreach ($files as $file) {
                    unlink($file);
                    $output->writeln('deleted ' . $file);
                }
                $file = $path . '/.htaccess';
                if (file_exists($file)) {
                    unlink($file);
                    $output->writeln('deleted ' . $file);
                }
                if (count(scandir($path)) === 2) {
                    rmdir($path);
                    $output->writeln('deleted ' . $path);
                }
            }
            $this->queueRepository->delete(['uid' => $row['uid']]);
        }

        return self::SUCCESS;
    }
}
