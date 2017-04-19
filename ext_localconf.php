<?php
defined('TYPO3_MODE') || die();

$boot = function ($_EXTKEY) {
    $settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['simple_api']);

    // Register API provider
    $eIDName = $settings['eIDName'];
    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include'][$eIDName] = 'EXT:' . $_EXTKEY . '/Classes/Controller/ApiController.php';
};

$boot($_EXTKEY);
unset($boot);
