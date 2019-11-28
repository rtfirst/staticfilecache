<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

if (TYPO3_MODE === 'BE') {
    // Add Web>Info module:
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
        'web_info',
        \SFC\Staticfilecache\Module\CacheModule::class,
        null,
        'LLL:EXT:staticfilecache/Resources/Private/Language/locallang.xml:module.title'
    );

    // Register Toolbar Item for WarmUpQueue
    $extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('staticfilecache');
    $GLOBALS['TYPO3_CONF_VARS']['typo3/backend.php']['additionalBackendItems'][] = $extensionPath . 'registerToolbarItem.php';

    // Register AJAX calls
    /** @var \SFC\Staticfilecache\Configuration $configuration */
    $configuration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\SFC\Staticfilecache\Configuration::class);
    if ($configuration->get('boostMode') && !defined('SFC_QUEUE_WORKER')) {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerAjaxHandler(
            'WarmUpQueueToolbarItem::getStatus',
            \SFC\Staticfilecache\ToolbarItem\WarmUpQueueToolbarItem::class . '->renderAjaxStatus'
        );
    }
}
