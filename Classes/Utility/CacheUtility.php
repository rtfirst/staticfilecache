<?php
/**
 * Cache Utility
 *
 * @author  Tim Lochmüller
 */

namespace SFC\Staticfilecache\Utility;

use SFC\Staticfilecache\Cache\StaticFileBackend;
use SFC\Staticfilecache\Configuration;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Cache Utility
 */
class CacheUtility implements SingletonInterface
{
    public static function getInstance(): CacheUtility
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return GeneralUtility::makeInstance(static::class);
    }

    /**
     * Get the static file cache
     *
     * @return FrontendInterface
     * @throws NoSuchCacheException
     */
    public function getCache()
    {
        /** @var CacheManager $cacheManager */
        $objectManager = new ObjectManager();
        $cacheManager = $objectManager->get(CacheManager::class);
        return $cacheManager->getCache('staticfilecache');
    }

    /**
     * Clear cache by page ID
     *
     * @param int $pageId
     */
    public function clearByPageId($pageId)
    {
        $cache = $this->getCache();
        $cacheEntries = array_keys($cache->getByTag('pageId_' . (int)$pageId));
        foreach ($cacheEntries as $cacheEntry) {
            $cache->remove($cacheEntry);
        }
    }

    /**
     * Remove the static files of the given identifier
     *
     * @param $entryIdentifier
     */
    public function removeStaticFiles($entryIdentifier)
    {
        $fileName = $this->getCacheFilename($entryIdentifier);
        $files = [
            $fileName,
            $fileName . '.gz',
            PathUtility::pathinfo($fileName, PATHINFO_DIRNAME) . '/.htaccess'
        ];
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Get the cache folder for the given entry
     *
     * @param $entryIdentifier
     *
     * @return string
     */
    protected function getCacheFilename($entryIdentifier)
    {
        $urlParts = parse_url($entryIdentifier);
        $cacheFilename = GeneralUtility::getFileAbsFileName(
            StaticFileBackend::CACHE_DIRECTORY . $urlParts['scheme'] . '/' . $urlParts['host'] . '/' . trim($urlParts['path'], '/')
        );
        $fileExtension = PathUtility::pathinfo(basename($cacheFilename), PATHINFO_EXTENSION);
        $configuration = GeneralUtility::makeInstance(Configuration::class);
        if (empty($fileExtension) || !GeneralUtility::inList($configuration->get('fileTypes'), $fileExtension)) {

                $cacheFilename = rtrim($cacheFilename, '/') . '/index.html';
        }
        return $cacheFilename;
    }
}
