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
 * LICENSE file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Causal\SimpleApi\Service;

final class HandlerService
{
    /**
     * Returns the API handler to use for a given route.
     *
     * @param string $route
     * @return array|null
     */
    public static function decodeHandler(string $route): ?array
    {
        if ($route === '' || $route === '/') {
            // No need to try to match an empty route
            return null;
        }

        $handler = null;
        $availableHandlers = array_merge(self::getAvailableApiPatternHandlers(), self::getAvailableApiHandlers());

        foreach ($availableHandlers as $apiHandler) {
            if (preg_match('#^' . $apiHandler['route'] . '($|/|\?)#', $route)) {
                if (!isset($apiHandler['methods'])) {
                    $apiHandler['methods'] = 'GET';
                }
                $handler = $apiHandler;
                break;
            }
        }

        return $handler;
    }

    /**
     * Returns the list of available API pattern handlers.
     *
     * @return array
     */
    public static function getAvailableApiPatternHandlers(): array
    {
        $apiPatternHandlers = null;
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['simple_api']['apiPatternHandlers'])) {
            $apiPatternHandlers = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['simple_api']['apiPatternHandlers'];
            foreach ($apiPatternHandlers as &$handler) {
                $handler['hasPattern'] = true;
            }
        }
        return is_array($apiPatternHandlers) ? $apiPatternHandlers : [];
    }

    /**
     * Returns the list of available API handlers.
     *
     * @return array
     */
    public static function getAvailableApiHandlers(): array
    {
        $apiHandlers = null;
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['simple_api']['apiHandlers'])) {
            $apiHandlers = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['simple_api']['apiHandlers'];
        }
        return is_array($apiHandlers) ? $apiHandlers : [];
    }
}
