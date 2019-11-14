<?php
/**
 * Queue manager
 *
 * @author  Tim Lochmüller
 */

namespace SFC\Staticfilecache;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use SFC\Staticfilecache\Utility\CacheUtility;
use SFC\Staticfilecache\Utility\ComposerUtility;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Queue manager
 *
 * @todo migrate to caching framework with UriFrontend and QueueBackend
 */
class QueueManager implements SingletonInterface
{

    /**
     * Queue table
     */
    const QUEUE_TABLE = 'tx_staticfilecache_queue';

    /**
     * Run the queue
     *
     * @param int $limitItems
     */
    public function run($limitItems = 0)
    {
        define('SFC_QUEUE_WORKER', true);
        $limit = $limitItems > 0 ? (int)$limitItems : '';

        $dbConnection = $this->getDatabaseConnection();
        $runEntriesResult = $dbConnection->sql_query($dbConnection->SELECTquery('*', self::QUEUE_TABLE, '', '', '', $limit));
        while ($runEntry = $dbConnection->sql_fetch_assoc($runEntriesResult)) {
            $this->runSingleRequest($runEntry);
        }
    }

    public function count(): int
    {
        return (int)$this->getDatabaseConnection()->exec_SELECTcountRows('*', self::QUEUE_TABLE);
    }

    /**
     * Run a single request with guzzle
     *
     * @param array $runEntry
     */
    protected function runSingleRequest(array $runEntry)
    {
        try {
            // i thinks it is ok to remove the file first and if the curl does not proceed the cache is invalidated anyway also it's not warmed up (failproof)
            CacheUtility::getInstance()->removeStaticFiles($runEntry['cache_url']);
            $client = $this->getCallableClient(parse_url($runEntry['cache_url'], PHP_URL_HOST));
            $response = $client->get($runEntry['cache_url']);
            $statusCode = $response->getStatusCode();
        } catch (\Exception $ex) {
            $statusCode = 900;
        }

        if ($statusCode !== 200) {
            // Call the flush, if the page is not accessable
            $cache = CacheUtility::getInstance()->getCache();
            $cache->flushByTag('sfc_pageId_' . $runEntry['page_uid']);
            if ($cache->has($runEntry['cache_url'])) {
                $cache->remove($runEntry['cache_url']);
            }
        }

        $dbConnection = $this->getDatabaseConnection();
        $dbConnection->exec_DELETEquery(self::QUEUE_TABLE, "identifier='" . $runEntry['identifier'] . "'");
    }

    /**
     * @todo Alternative for runSingleRequest (not used at the moment)
     * @param array $data
     * @param array $options
     * @return array
     */
    public function runMultiRequest(array $data, $options = [])
    {

        $curly = [];
        $result = [];
        $mh = curl_multi_init();

        foreach ($data as $id => $d) {
            $curly[$id] = curl_init();

            $url = (is_array($d) && !empty($d['cache_url'])) ? $d['cache_url'] : $d;
            curl_setopt($curly[$id], CURLOPT_URL, $url);
            curl_setopt($curly[$id], CURLOPT_HEADER, 0);
            curl_setopt($curly[$id], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curly[$id], CURLOPT_FRESH_CONNECT, true);
            curl_setopt($curly[$id], CURLOPT_TIMEOUT_MS, 2000);
            curl_setopt($curly[$id], CURLOPT_COOKIE, 'staticfilecache=1');
            curl_setopt($curly[$id], CURLOPT_USERAGENT, 'Staticfilecache Crawler');

            if (is_array($d)) {
                if (!empty($d['post'])) {
                    curl_setopt($curly[$id], CURLOPT_POST, 1);
                    curl_setopt($curly[$id], CURLOPT_POSTFIELDS, $d['post']);
                }
            }

            // extra options?
            if (!empty($options)) {
                curl_setopt_array($curly[$id], $options);
            }

            curl_multi_add_handle($mh, $curly[$id]);
        }

        // execute the handles
        $running = null;
        do {
            curl_multi_exec($mh, $running);
        } while ($running > 0);

        // get content and remove handles
        foreach ($curly as $id => $c) {
            $result[$id] = [
                'call_date'   => time(),
                'call_result' => curl_getinfo($c),
                'page_uid'    => $data[$id]['page_uid'],

            ];
            curl_multi_remove_handle($mh, $c);
        }

        // all done
        curl_multi_close($mh);

        return $result;
    }

    /**
     * Add identifier to Queue
     * This method needs mysql-compatible database connection
     *
     * @param string $cache_url
     */
    public function addIdentifier($cache_url)
    {
        $db = $this->getDatabaseConnection();
        $identifier = sha1($cache_url);
        $sql = 'REPLACE INTO ' . self::QUEUE_TABLE . "(identifier, cache_url, page_uid, invalid_date, call_result) VALUES('" . $identifier . "'".', ' . $db->fullQuoteStr($cache_url, self::QUEUE_TABLE) . ', 0, ' .time().", '')";
        $db->sql_query($sql);
    }

    /**
     * Get a cllable client
     *
     * @param string $domain
     *
     * @return Client
     */
    protected function getCallableClient($domain)
    {
        ComposerUtility::check();
        $jar = GeneralUtility::makeInstance(CookieJar::class);
        $cookie = GeneralUtility::makeInstance(SetCookie::class);
        $cookie->setName('staticfilecache');
        $cookie->setValue('1');
        $cookie->setPath('/');
        $cookie->setExpires(time() + 30);
        $cookie->setDomain($domain);
        $jar->setCookie($cookie);
        $options = [
            'cookies' => $jar,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:47.0) Gecko/20100101 Firefox/47.0'
            ]
        ];
        return GeneralUtility::makeInstance(Client::class, $options);
    }

    /**
     * Get the database connection
     *
     * @return DatabaseConnection
     */
    protected function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }
}
