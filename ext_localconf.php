<?php
defined('TYPO3_MODE') || die();

(static function (string $_EXTKEY) {
    // Register API provider
    $typo3Branch = class_exists(\TYPO3\CMS\Core\Information\Typo3Version::class)
        ? (new \TYPO3\CMS\Core\Information\Typo3Version())->getBranch()
        : TYPO3_branch;
    if (version_compare($typo3Branch, '9.5', '<')) {
        $settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY]);
        $eIDName = $settings['eIDName'];
        $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include'][$eIDName] = 'EXT:' . $_EXTKEY . '/Classes/Controller/EidController.php';
    } else {
        $settings = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][$_EXTKEY];
        $eIDName = $settings['eIDName'];
        $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include'][$eIDName] = \Causal\SimpleApi\Controller\EidController::class . '::start';
    }

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
})('simple_api');
