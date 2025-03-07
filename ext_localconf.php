<?php

defined('TYPO3') || die();

(static function (string $_EXTKEY) {
    /*****************************************************
     * API Caching
     *****************************************************/

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$_EXTKEY] ??= [];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$_EXTKEY]['backend'] ??= \Causal\SimpleApi\Cache\Backend\Typo3DatabaseNoFlushBackend::class;

    // Register hooks for \TYPO3\CMS\Core\DataHandling\DataHandler
    // in order to automatically flush cache when a record is edited
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \Causal\SimpleApi\Hooks\DataHandler::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = \Causal\SimpleApi\Hooks\DataHandler::class;

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Causal\SimpleApi\Task\AsynchronousClearCacheTask::class] = [
        'extension' => $_EXTKEY,
        'title' => 'Clear Simple API cache asynchronously',
        'description' => 'This task will process asynchronous cache clearing operations. This is only useful if you have a load-balanced setup with files being copied with some delay.',
    ];
})('simple_api');
