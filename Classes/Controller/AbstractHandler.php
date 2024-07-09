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

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract class for Simple API handlers.
 *
 * @category    Controller
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   2012-2024 Causal Sàrl
 * @license     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
abstract class AbstractHandler
{
    protected const HTTP_GET = 1;
    protected const HTTP_POST = 2;
    protected const HTTP_PUT = 3;
    protected const HTTP_DELETE = 4;

    /**
     * @var VariableFrontend
     */
    protected static $cache;

    /**
     * Includes the TCA definitions of a given extension.
     *
     * @param string $extensionName
     */
    protected function includeTCA(string $extensionName): void
    {
        $tcaPath = ExtensionManagementUtility::extPath($extensionName) . 'Configuration/TCA/';
        $files = GeneralUtility::getFilesInDir($tcaPath);
        foreach ($files as $file) {
            $table = substr($file, 0, -4); // strip ".php" at the end
            $GLOBALS['TCA'][$table] = include($tcaPath . $file);
        }
        $tcaOverridePath = $tcaPath . 'Overrides/';
        $files = GeneralUtility::getFilesInDir($tcaOverridePath);
        foreach ($files as $file) {
            include($tcaOverridePath . $file);
        }
    }

    /**
     * Initializes an API request.
     *
     * Will be called before invoking @see handle();
     */
    public function initialize(): void
    {
        // Override in your class if needed
    }

    /**
     * Handles an API request.
     *
     * @param string $route
     * @param string $subroute
     * @param array $parameters
     * @return array|null
     * @throws \Causal\SimpleApi\Exception\ForbiddenException
     */
    abstract public function handle(string $route, string $subroute = '', array $parameters = []): ?array;

    /**
     * @return VariableFrontend
     */
    protected static function getCache(): VariableFrontend
    {
        if (static::$cache === null) {
            static::$cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('simple_api');
        }
        return static::$cache;
    }

    /**
     * @param string $extensionKey
     * @return array
     */
    protected static function getExtensionConfiguration(string $extensionKey): array
    {
        return GeneralUtility::makeInstance(ExtensionConfiguration::class)->get($extensionKey) ?? [];
    }

    /**
     * Returns the documentation for a given route.
     *
     * @param string $route
     * @return array
     */
    public static function getDocumentation(string $route): array
    {
        $out = [];

        switch ($route) {
            case '/demo-documentation':
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
                break;
        }

        return $out;
    }

    /**
     * Generates a definition list, typically to be used as the description of one of the parameters
     * of a "definitions" key item.
     *
     * @param array $items
     * @return string
     */
    protected static function makeDocumentationDefinitionList(array $items): string
    {
        $simpleTagsAllowed = [
            '<strong>' => '__STRONG__', '</strong>' => '__/STRONG__',
            '<em>' => '__EM__',         '</em>' => '__/EM__',
            '<b>' => '__B__',           '</b>' => '__/B__',
            '<i>' => '__I__',           '</i>' => '__/I__',
            '<code>' => '__CODE__',     '</code>' => '__/CODE__',
        ];

        $out = '<dl class="dl-horizontal">';
        foreach ($items as $key => $value) {
            $value = str_replace(array_keys($simpleTagsAllowed), array_values($simpleTagsAllowed), $value);
            $value = htmlspecialchars($value);
            $value = str_replace(array_values($simpleTagsAllowed), array_keys($simpleTagsAllowed), $value);
            $out .= '<dt>' . htmlspecialchars($key) . '</dt><dd>' . $value . '</dd>';
        }
        $out .= '</dl>';

        return $out;
    }

    /**
     * Returns a logger.
     *
     * @return Logger
     */
    protected static function getLogger(): Logger
    {
        /** @var Logger $logger */
        static $logger = null;
        if ($logger === null) {
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        }
        return $logger;
    }
}
