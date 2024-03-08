<?php
declare(strict_types=1);

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

namespace Causal\SimpleApi\Task;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class AsynchronousClearCacheTask
 */
class AsynchronousClearCacheTask extends \TYPO3\CMS\Scheduler\Task\AbstractTask
{

    /**
     * Method executed from the Scheduler.
     *
     * @return bool
     */
    public function execute()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_simpleapi_cache_queue');
        $rows = $queryBuilder
            ->select('*')
            ->from('tx_simpleapi_cache_queue')
            ->where(
                $queryBuilder->expr()->lte('crdate', $queryBuilder->createNamedParameter(time() - 600 /* 10 min */, Connection::PARAM_INT))
            )
            ->execute()
            ->fetchAll();

        $uids = [];
        $cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('simple_api');
        foreach ($rows as $row) {
            $uids[] = $row['uid'];
            $cache->flushByTag($row['cache_tag']);
        }

        if (!empty($uids)) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('tx_simpleapi_cache_queue');
            $queryBuilder
                ->delete('tx_simpleapi_cache_queue')
                ->where(
                    $queryBuilder->expr()->in('uid', $uids)
                )
                ->execute();
        }

        return true;
    }

    /**
     * @param string $cacheTag
     * @api
     */
    public static function insert(string $cacheTag): void
    {
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_simpleapi_cache_queue')
            ->insert(
                'tx_simpleapi_cache_queue',
                [
                    'crdate' => time(),
                    'cache_tag' => $cacheTag,
                ]
            );
    }
}
