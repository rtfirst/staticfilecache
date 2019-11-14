<?php

namespace SFC\Staticfilecache\ToolbarItem;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use SFC\Staticfilecache\QueueManager;
use TYPO3\CMS\Backend\Controller\BackendController;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Core\Http\AjaxRequestHandler;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Class WarmUpQueueToolbarItem
 *
 * @author Markus Hölzle <m.hoelzle@andersundsehr.com>
 * @author Stefan Lamm <s.lamm@andersundsehr.com>
 * @package SFC\Staticfilecache\ToolbarItem
 */
class WarmUpQueueToolbarItem implements ToolbarItemInterface
{
    /**
     * Constructor, loads the documents from the user control
     *
     * @param BackendController $backendReference TYPO3 backend object reference
     */
    public function __construct(BackendController $backendReference = null)
    {
        // httpdocs/typo3conf/ext/staticfilecache/Resources/Public/JavaScript/WarmUpQueueToolbarItem.js
        $fullJsPath = PathUtility::getRelativePath(PATH_typo3, GeneralUtility::getFileAbsFileName('EXT:staticfilecache/Resources/Public/JavaScript/'));

        // requirejs
        $this->getPageRenderer()->addRequireJsConfiguration(
            [
                'paths' => [
                    'SFC/Staticfilecache/WarmUpQueueToolbarItem' => $fullJsPath . 'WarmUpQueueToolbarItem',
                ],
            ]
        );
        $this->getPageRenderer()->loadRequireJsModule('SFC/Staticfilecache/WarmUpQueueToolbarItem');
    }

    /**
     * Returns current PageRenderer
     *
     * @return PageRenderer
     */
    protected function getPageRenderer()
    {
        return GeneralUtility::makeInstance(PageRenderer::class);
    }

    public function hasDropDown()
    {
        return false;
    }

    public function getDropDown()
    {
    }

    public function getIndex()
    {
        return 99;
    }

    /**
     * Checks whether the user has access to this toolbar item
     *
     * @return boolean TRUE if user has access, FALSE if not
     */
    public function checkAccess()
    {
        return true;
    }

    /**
     * Returns additional attributes for the list item in the toolbar
     *
     * @return string List item HTML attibutes
     */
    public function getAdditionalAttributes()
    {
    }

    /**
     * This function returns the toolbar item as html.
     *
     * @return string
     * @todo This should be moved to a fluid template with an external css file
     */
    public function getItem()
    {
        return '<div class="clearfix" style="color: white; background: #282828; height:100%" title="Anzahl der Seiten in der Cache Warm-Up Warteschlange">
                  <div style="float: left;padding: 4px 2px 4px 7px;" id="pluswerk-warm-up-queue-count">0</div>
                  <img alt="Cachequeue empty" src="/typo3conf/ext/staticfilecache/Resources/Public/Icons/ok.png" id="pluswerk-warm-up-queue-icon-ok" style="width: 18px; margin: 3px 3px 0 0; float: left;" />
                  <img alt="Cachequeue not empty" src="/typo3conf/ext/staticfilecache/Resources/Public/Icons/ripple.svg" id="pluswerk-warm-up-queue-icon-running" style="width: 25px; margin: 0 1px 0 0; float: left; display: none;" />
                </div>';
    }

    /**
     * Renders the menu so that it can be returned as response to an AJAX call
     *
     * @param array $params Array of parameters from the AJAX interface, currently unused
     * @param AjaxRequestHandler $ajaxObj Object of type AjaxRequestHandler
     * @return void
     */
    public function renderAjaxStatus($params = [], AjaxRequestHandler $ajaxObj = null)
    {
        $queueManager = GeneralUtility::makeInstance(QueueManager::class);

        header('Content-Type: application/json');
        $ajaxObj->addContent(
            'warmUpQueueCount',
            json_encode(
                [
                    'warmUpQueueCount' => $queueManager->count(),
                ]
            )
        );
    }
}
