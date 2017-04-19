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

namespace Causal\SimpleApi\Controller;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Abstract class for Simple API handlers.
 *
 * @category    Controller
 * @package     simple_api
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   2012-2017 Causal Sàrl
 * @license     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
abstract class AbstractHandler
{
    const HTTP_GET = 1;
    const HTTP_POST = 2;

    /**
     * @var ApiController
     */
    protected $apiController = null;

    /**
     * @var string
     */
    protected $fullRequestUri = '';

    /**
     * @var \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend
     */
    protected static $cache;

    /**
     * Default constructor.
     *
     * @param ApiController $controller
     * @param string $fullRequestUri
     */
    public function __construct(ApiController $controller, $fullRequestUri)
    {
        $this->apiController = $controller;
        $this->fullRequestUri = $fullRequestUri;
    }

    /**
     * @param string $extensionName
     * @return void
     */
    public function includeTCA($extensionName)
    {
        $tcaPath = ExtensionManagementUtility::extPath($extensionName) . 'Configuration/TCA/';
        $files = GeneralUtility::getFilesInDir($tcaPath);
        foreach ($files as $file) {
            $table = substr($file, 0, -4); // strip ".php" at the end
            $GLOBALS['TCA'][$table] = include($tcaPath . $file);
        }
    }

    /**
     * Handles an API request.
     *
     * @param string $route
     * @param string $subroute
     * @param array $parameters
     */
    abstract function handle($route, $subroute = '', array $parameters = []);

    /**
     * @return \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend
     */
    protected static function getCache()
    {
        if (static::$cache === null) {
            static::$cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('simple_api');
        }
        return static::$cache;
    }

    /**
     * Returns the database connection.
     *
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected static function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }

    /**
     * Returns the documentation for a given route.
     *
     * @param string $route
     * @return array
     */
    public static function getDocumentation($route)
    {
        $out = [];
        $out[] = [
            'http' => static::HTTP_GET,
            'path' => $route,
            'parameters' => [
                //'foo' => 'string'
            ],
            'description' => 'returns a list of objects',
            'response' => [
                'type' => 'application/json',
                'data' => '[{"id"...}]',
            ],
        ];
        return $out;
    }

    /**
     * Returns a logger.
     *
     * @return \TYPO3\CMS\Core\Log\Logger
     */
    protected static function getLogger()
    {
        /** @var \TYPO3\CMS\Core\Log\Logger $logger */
        static $logger = null;
        if ($logger === null) {
            $logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        }
        return $logger;
    }

}
