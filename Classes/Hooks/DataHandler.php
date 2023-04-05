<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with TYPO3 source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Causal\SimpleApi\Hooks;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hooks for \TYPO3\CMS\Core\DataHandling\DataHandler.
 */
class DataHandler
{
    /**
     * Hooks into \TYPO3\CMS\Core\DataHandling\DataHandler after records have been saved to the database.
     *
     * @param string $operation
     * @param string $table
     * @param mixed $id
     * @param array $fieldArray
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $pObj
     */
    public function processDatamap_afterDatabaseOperations($operation, $table, $id, array $fieldArray, \TYPO3\CMS\Core\DataHandling\DataHandler $pObj)
    {
        $cacheTags = [];

        switch ($operation) {
            case 'update':
                $cacheTags[] = $table . '%' . $id;

                $record = BackendUtility::getRecord($table, $id);
                $cacheTags[] = 'pages%' . $record['pid'];
                if (!empty($record['l10n_parent'])) {
                    $cacheTags[] = $table . '%' . $record['l10n_parent'];
                }
                break;
            case 'new':
                if (!is_numeric($id)) {
                    $id = $pObj->substNEWwithIDs[$id];
                }
                $cacheTags[] = $table . '%' . $id;
                $cacheTags[] = 'pages%' . $fieldArray['pid'];
                break;
        }

        if (!empty($cacheTags)) {
            $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('simple_api');
            $cache->flushByTags($cacheTags);
        }
    }

    /**
     * Hooks into \TYPO3\CMS\Core\DataHandling\DataHandler after one of the following commands as been handled:
     * 'move', 'copy', 'localize', 'delete' or 'undelete'.
     *
     * @param string $command
     * @param string $table
     * @param int $id
     * @param mixed $value
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $pObj
     */
    public function processCmdmap_postProcess($command, $table, $id, $value, \TYPO3\CMS\Core\DataHandling\DataHandler $pObj)
    {
        $cacheTags[] = $table . '%' . $id;

        $record = BackendUtility::getRecord($table, $id, 'pid', '', false);
        if ($record !== null) {
            $cacheTags[] = 'pages%' . $record['pid'];
        }

        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('simple_api');
        $cache->flushByTags($cacheTags);
    }
}
