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
use Causal\SimpleApi\Exception\ForbiddenException;

/**
 * API controller.
 *
 * @category    Controller
 * @package     simple_api
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   2012-2017 Causal Sàrl
 * @license     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class ApiController
{

    /** @var int */
    public $maxAge = 86400;    // 86400 = 1 day of caching by default

    /** @var string */
    protected $extKey = 'simple_api';

    /**
     * Dispatches the request and returns data.
     *
     * @return array
     * @throws \RuntimeException
     */
    public function dispatch()
    {
        $this->initTSFE();

        $route = GeneralUtility::_GET('route');
        $apiHandler = $this->decodeHandler($route);
        static::getLogger()->debug('dispatch()', ['route' => $route, 'handler' => $apiHandler]);

        if (!$apiHandler) {
            $this->usageAction();
            exit;
        }

        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
        $objectManager = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);

        // FULL_REQUEST_URI = [scheme]://[host][:[port]][path]?[query]
        $requestUri = $_SERVER['REQUEST_URI'];
        if ($this->isProxied()) {
            // Remove prefix /?[id=int&]eID=simple_api&route=
            $requestUri = substr($requestUri, strpos($requestUri, '&route=') + strlen('&route='));
            // Transform first & into a ? in the query
            if (($pos = strpos($requestUri, '&')) !== false) {
                $requestUri{$pos} = '?';
            }
        }
        $fullRequestUri = (GeneralUtility::getIndpEnv('TYPO3_SSL') ? 'https://' : 'http://') . $this->getHost() . $requestUri;

        /** @var AbstractHandler $hookObj */
        $hookObj = $objectManager->get($apiHandler['class'], $this, $fullRequestUri);
        if (!($hookObj instanceof AbstractHandler)) {
            throw new \RuntimeException('Handler for route ' . $apiHandler['route'] . ' does not implement \\Causal\\SimpleApi\\Controller\\AbstractHandler', 1492589300);
        }

        if (!GeneralUtility::inList($apiHandler['methods'], $_SERVER['REQUEST_METHOD'])) {
            throw new \RuntimeException('This request does not support HTTP method ' . $_SERVER['REQUEST_METHOD'], 1492589304);
        }

        $parameters = [];
        $basicGetParams = ['route', 'id', 'eID'];
        $limitedGetParams = ($_SERVER['REQUEST_METHOD'] !== 'GET');

        foreach ($_GET as $key => $_) {
            if (!in_array($key, $basicGetParams) && !$limitedGetParams) {
                $parameters[$key] = GeneralUtility::_GET($key);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!empty($apiHandler['contentType']) && $apiHandler['contentType'] === 'application/json') {
                $data = json_decode(file_get_contents('php://input'), true);
                if (is_array($data)) {
                    $parameters = array_merge($parameters, $data);
                }
            } else {
                foreach ($_POST as $key => $_) {
                    $parameters[$key] = GeneralUtility::_POST($key);
                }
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            foreach ($_GET as $key => $_) {
                if (!in_array($key, $basicGetParams)) {
                    $parameters[$key] = GeneralUtility::_GET($key);
                }
            }
        }

        // Request is by default NOT authenticated
        $parameters['_authenticated'] = false;
        $parameters['_demo'] = false;
        $parameters['_method'] = $_SERVER['REQUEST_METHOD'];
        $parameters['_userAgent'] = $_SERVER['HTTP_USER_AGENT'];
        if (!empty($_SERVER['HTTP_X_AUTHORIZATION'])) {
            // No caching by default for authenticated requests
            $this->maxAge = 0;

            $accessToken = $_SERVER['HTTP_X_AUTHORIZATION'];
            $authenticationHandler = $this->decodeHandler('/authenticate');
            if ($authenticationHandler) {
                /** @var AbstractHandler $authenticationObj */
                $authenticationObj = $objectManager->get($authenticationHandler['class'], $this, $fullRequestUri);
                if (!($authenticationObj instanceof AbstractHandler)) {
                    throw new \RuntimeException('Handler for route ' . $authenticationObj['route'] . ' does not implement \\Causal\\SimpleApi\\Controller\\AbstractHandler', 1492589310);
                }
                $authenticationData = $authenticationObj->handle('/authenticate', $accessToken, $parameters);
                if ($authenticationData['success']) {
                    static::getLogger()->debug('Successful authentication');
                    $parameters['_authenticated'] = true;
                    foreach ($authenticationData as $key => $value) {
                        if ($key !== 'success') {
                            $parameters['_' . $key] = $value;
                        }
                    }
                } else {
                    static::getLogger()->notice('Invalid authentication', ['token' => $accessToken]);
                }
            }
        }

        // If access is restricted and the user is either not authenticated or is a demo user, then access is forbidden
        // Access to restricted data with a demo user should not be handled by marking the api handler as restricted but
        // explicitly checking for the "demo" flag and handling it accordingly.
        if (isset($apiHandler['restricted']) && (bool)$apiHandler['restricted'] && (!$parameters['_authenticated'] || $parameters['_demo'])) {
            static::getLogger()->notice('Access denied');
            throw new ForbiddenException('Access to this API is restricted to authenticated users.', 1455980530);
        }

        if (isset($apiHandler['hasPattern']) && (bool)$apiHandler['hasPattern']) {
            list($baseRoute, ) = explode('/', ltrim($apiHandler['route'], '/'), 2);
            $baseRoute = '/' . $baseRoute;
            $subroute = substr($route, strlen($baseRoute) + 1);
            $route = $baseRoute;
        } else {
            $subroute = ltrim((string)substr($route, strlen($apiHandler['route'])), '/');
            $route = $apiHandler['route'];
        }

        $data = $hookObj->handle(
            $route,
            $subroute,
            $parameters,
            $this
        );

        return $data;
    }

    /**
     * Returns the list of available API pattern handlers.
     *
     * @return array
     */
    protected function getAvailabbleApiPatternHandlers()
    {
        $apiPatternHandlers = null;
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['apiPatternHandlers'])) {
            $apiPatternHandlers = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['apiPatternHandlers'];
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
    protected function getAvailableApiHandlers()
    {
        $apiHandlers = null;
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['apiHandlers'])) {
            $apiHandlers = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['apiHandlers'];
        }
        return is_array($apiHandlers) ? $apiHandlers : [];
    }

    /**
     * Returns the API handler to use for a given route.
     *
     * @param string $route
     * @return array
     */
    protected function decodeHandler($route)
    {
        $handler = null;
        $availableHandlers = array_merge($this->getAvailabbleApiPatternHandlers(), $this->getAvailableApiHandlers());

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
     * Returns TRUE if this API controller is accessed over a reverse-proxy.
     *
     * @return bool
     */
    protected function isProxied()
    {
        return isset($_SERVER['HTTP_X_FORWARDED_HOST']);
    }

    /**
     * Returns the base URL to use for requesting the API.
     *
     * @return string
     */
    protected function getBaseUrl()
    {
        $baseUrl = '';
        if (!$this->isProxied()) {
            $baseUrl .= '/?';
            $id = GeneralUtility::_GET('id');
            if (!empty($id)) {
                $baseUrl .= 'id=' . $id . '&';
            }
            $baseUrl .= 'eID=' . GeneralUtility::_GET('eID') . '&route=';
        }
        return $baseUrl;
    }

    /**
     * Returns the host name.
     *
     * @return string
     */
    protected function getHost()
    {
        return isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['HTTP_HOST'];
    }

    /**
     * Shows API usage.
     *
     * @return void
     */
    protected function usageAction()
    {
        $settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);

        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
        $objectManager = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);
        /** @var \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext $controllerContext */
        $controllerContext = $objectManager->get(\TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext::class);
        /** @var \TYPO3\CMS\Extbase\Mvc\Request $request */
        $request = $objectManager->get(\TYPO3\CMS\Extbase\Mvc\Request::class);
        $request->setControllerExtensionName($this->extKey);
        $request->setControllerName('Api');
        $request->setControllerActionName('usage');
        $request->setFormat('html');
        $controllerContext->setRequest($request);

        /** @var $view \TYPO3\CMS\Fluid\View\TemplateView */
        $view = $objectManager->get(\TYPO3\CMS\Fluid\View\TemplateView::class);
        $view->setControllerContext($controllerContext);

        // Set the paths to the template resources
        $view->setLayoutRootPath(ExtensionManagementUtility::extPath($this->extKey) . 'Resources/Private/Layouts');
        $view->setTemplateRootPath(ExtensionManagementUtility::extPath($this->extKey) . 'Resources/Private/Templates');
        $view->setPartialRootPath(ExtensionManagementUtility::extPath($this->extKey) . 'Resources/Private/Partials');

        $view->assign('settings', $settings);

        $apiHandlers = [];
        foreach ($this->getAvailableApiHandlers() as $apiHandler) {
            $apiHandlers[$apiHandler['route']] = $apiHandler;
        }
        ksort($apiHandlers);

        $handler = null;
        $route = (string)GeneralUtility::_GET('op');
        if ($route !== '') {
            foreach ($apiHandlers as $apiHandler) {
                if ($apiHandler['route'] === $route) {
                    $handler = $apiHandler;
                    break;
                }
            }
        }
        if ($handler === null) {
            $routes = [];
            foreach ($apiHandlers as $apiHandler) {
                $routes[] = $this->getDescriptionLink($apiHandler);
            }
            $view->assignMultiple([
                'showAll' => true,
                'routes' => $routes,
            ]);
        } else {
            /** @var AbstractHandler $classHandler */
            $classHandler = $handler['class'];
            $documentation = $classHandler::getDocumentation($route);

            // Append pattern-based routes afterwards
            foreach ($this->getAvailabbleApiPatternHandlers() as $apiHandler) {
                list($baseRoute, ) = explode('/', ltrim($apiHandler['route'], '/'), 2);
                if ('/' . $baseRoute === $route) {
                    $classHandler = $apiHandler['class'];
                    $additionalDocumentation = $classHandler::getDocumentation($route);
                    if (!empty($additionalDocumentation)) {
                        $documentation = array_merge($documentation, $additionalDocumentation);
                    }
                }
            }

            $contentType = empty($handler['contentType'])
                ? 'application/x-www-form-urlencoded'
                : $handler['contentType'];

            $view->assignMultiple([
                'host' => $this->getHost(),
                'baseUrl' => $this->getBaseUrl(),
                'contentType' => $contentType,
                'intro' => 'Click ' . $this->getDescriptionLink() . ' for a complete list of routes.',
                'route' => $handler['route'],
                'methods' => $documentation,
                'json' => $contentType === 'application/json',
                'deprecated' => $handler['deprecated'],
            ]);
        }

        echo $view->render();
    }

    /**
     * Returns a description link.
     *
     * @param array $apiHandler
     * @return string
     */
    protected function getDescriptionLink(array $apiHandler = [])
    {
        $parameters = [];
        if (!$this->isProxied()) {
            $id = GeneralUtility::_GET('id');
            if (!empty($id)) {
                $parameters[] = 'id=' . $id;
            }
            $parameters[] = 'eID=' . GeneralUtility::_GET('eID');
        }
        $suffix = '';
        if ($apiHandler) {
            $parameters[] = 'op=' . $apiHandler['route'];
            $label = $apiHandler['route'];
            if (!empty($apiHandler['deprecated'])) {
                $suffix = ' <span class="label label-danger">deprecated</span>';
            }
        } else {
            $label = 'here';
        }

        $url = '/?' . implode('&', $parameters);
        return '<a href="' . htmlspecialchars($url) . '">' . $label . '</a>' . $suffix;
    }

    /**
     * Initializes TSFE and sets $GLOBALS['TSFE'].
     *
     * @return void
     */
    protected function initTSFE()
    {
        // This is needed for Extbase with new property mapper
        $files = [
            'EXT:core/Configuration/TCA/pages.php',
            'EXT:core/Configuration/TCA/sys_file_storage.php',
            'EXT:frontend/Configuration/TCA/pages_language_overlay.php',
        ];
        foreach ($files as $file) {
            $file = GeneralUtility::getFileAbsFileName($file);
            $table = substr($file, strrpos($file, '/') + 1, -4); // strip ".php" at the end
            $GLOBALS['TCA'][$table] = include($file);
        }

        $GLOBALS['TSFE'] = GeneralUtility::makeInstance(
            \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::class,
            $GLOBALS['TYPO3_CONF_VARS'],
            GeneralUtility::_GP('id'),
            ''
        );
        $GLOBALS['TSFE']->connectToDB();
        $GLOBALS['TSFE']->initFEuser();
        $GLOBALS['TSFE']->checkAlternativeIdMethods();
        $GLOBALS['TSFE']->determineId();
        $GLOBALS['TSFE']->initTemplate();
        $GLOBALS['TSFE']->getConfigArray();

        $locale = GeneralUtility::_GET('locale');
        if (!empty($locale)) {
            // Initialize language
            $locale = strtolower(substr($locale, 0, 2));

            /** @var \TYPO3\CMS\Core\Database\DatabaseConnection $database */
            $database = $GLOBALS['TYPO3_DB'];
            $language = $database->exec_SELECTgetSingleRow(
                'l.uid',
                'sys_language l INNER JOIN static_languages sl ON sl.uid=l.static_lang_isocode',
                'sl.lg_typo3=' . $database->fullQuoteStr($locale, 'static_languages')
            );
            if (!empty($language['uid'])) {
                $GLOBALS['TSFE']->config['config']['language'] = $locale;
                $GLOBALS['TSFE']->config['config']['sys_language_uid'] = (int)$language['uid'];
                $GLOBALS['TSFE']->config['config']['sys_language_mode'] = 'ignore';
                $GLOBALS['TSFE']->settingLanguage();
            }
        }

        // Get linkVars, absRefPrefix, etc
        \TYPO3\CMS\Frontend\Page\PageGenerator::pagegenInit();
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

if (!isset($GLOBALS['_ENV']['PHP_UNIT'])) {
    /** @var ApiController $output */
    $output = GeneralUtility::makeInstance(ApiController::class);

    try {
        $ret = $output->dispatch();
    } catch (ForbiddenException $e) {
        header($e::HTTP_STATUS);
        echo 'Error ' . $e->getCode() . ': ' . $e->getMessage();
        exit;
    } catch (\Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo 'Error ' . $e->getCode() . ': ' . $e->getMessage();
        exit;
    }

    if ($ret === null) {
        header('HTTP/1.0 404 Not Found');
        echo <<<HTML
<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">
<html>
<head>
<title>Action not found</title>
<style>
    html, body, pre {
        margin: 0;
        padding: 0;
        font-family: Monaco, 'Lucida Console', monospace;
        background: #ECECEC;
    }
    h1 {
        margin: 0;
        background: #AD632A;
        padding: 20px 45px;
        color: #fff;
        text-shadow: 1px 1px 1px rgba(0,0,0,.3);
        border-bottom: 1px solid #9F5805;
        font-size: 28px;
    }
    p#detail {
        margin: 0;
        padding: 15px 45px;
        background: #F6A960;
        border-top: 4px solid #D29052;
        color: #733512;
        text-shadow: 1px 1px 1px rgba(255,255,255,.3);
        font-size: 14px;
        border-bottom: 1px solid #BA7F5B;
    }
</style>
</head>
<body>
<h1>Action not found</h1>

<p id="detail">
    For request '{$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']}'
</p>
</body>
</html>
HTML;
        exit();
    }

    $contentType = 'application/json';
    $payload = json_encode($ret);

    $acceptedEncoding = !empty($_SERVER['HTTP_ACCEPT_ENCODING']) ? GeneralUtility::trimExplode(',', $_SERVER['HTTP_ACCEPT_ENCODING']) : [];
    if (in_array('gzip', $acceptedEncoding) && function_exists('gzencode')) {
        $payload = gzencode($payload, 6);
        header('Content-Encoding: gzip');
    }

    header('Content-Length: ' . strlen($payload));
    header('Content-Type: ' . $contentType);
    header('Cache-Control: max-age=' . $output->maxAge);

    echo $payload;
}
