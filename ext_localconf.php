<?php
defined('TYPO3_MODE') || die();

$boot = function ($_EXTKEY) {
    $settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY]);

    // Register API provider
    $eIDName = $settings['eIDName'];
    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include'][$eIDName] = 'EXT:' . $_EXTKEY . '/Classes/Controller/ApiController.php';

    /*****************************************************
     * API Caching
     *****************************************************/

    if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$_EXTKEY])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$_EXTKEY] = [];
    }
};

$boot($_EXTKEY);
unset($boot);
