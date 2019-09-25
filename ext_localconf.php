<?php
defined('TYPO3_MODE') || die();

$boot = function ($_EXTKEY) {
    $settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY]);

    // Register API provider
    $eIDName = $settings['eIDName'];
    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include'][$eIDName] = 'EXT:' . $_EXTKEY . '/Classes/Controller/EidController.php';

    /*****************************************************
     * API Caching
     *****************************************************/

    if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$_EXTKEY])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$_EXTKEY] = [];
    }

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Causal\SimpleApi\Task\AsynchronousClearCacheTask::class] = [
        'extension' => $_EXTKEY,
        'title' => 'Clear Simple API cache asynchronously',
        'description' => 'This task will process asynchronous cache clearing operations. This is only useful if you have a load-balanced setup with files being copied with some delay.',
    ];
};

$boot($_EXTKEY);
unset($boot);
