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
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
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
                if (isset($fieldArray['l10n_parent'])) {
                    $fullRecord = $fieldArray;
                } else {
                    $fullRecord = BackendUtility::getRecord($table, $id);
                }
                $cacheTags[] = $table . '%' . $id;
                if (!empty($fullRecord['l10n_parent'])) {
                    $cacheTags[] = $table . '%' . $fullRecord['l10n_parent'];
                }
                break;
            case 'new':
                if (!is_numeric($id)) {
                    $id = $pObj->substNEWwithIDs[$id];
                }
                $cacheTags[] = $table . '%' . $id;
                break;
        }

        if (!empty($cacheTags)) {
            $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('simple_api');
            $cache->flushByTags($cacheTags);
        }
    }
}
