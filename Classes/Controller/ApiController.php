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

use Causal\SimpleApi\Exception;
use Causal\SimpleApi\Service\HandlerService;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\TemplateView;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * API controller.
 *
 * @category    Controller
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   2012-2023 Causal Sàrl
 * @license     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @deprecated  Please switch to using the API Middleware instead (configure siteIdentifier from ext_conf_template.txt)
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
        $start = microtime(true);
        $route = GeneralUtility::_GET('route');
        $apiHandler = HandlerService::decodeHandler($route ?? '');
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
                $requestUri = substr($requestUri, 0, $pos) . '?' . substr($requestUri, $pos + 1);
            }
        }
        $fullRequestUri = (GeneralUtility::getIndpEnv('TYPO3_SSL') ? 'https://' : 'http://') . $this->getHost() . $requestUri;

        /** @var AbstractHandler $hookObj */
        $hookObj = $objectManager->get($apiHandler['class'], $this, $fullRequestUri);
        if (!($hookObj instanceof AbstractHandler)) {
            throw new \RuntimeException('Handler for route ' . $apiHandler['route'] . ' does not implement \\Causal\\SimpleApi\\Controller\\AbstractHandler', 1492589300);
        }

        if (!GeneralUtility::inList($apiHandler['methods'], $_SERVER['REQUEST_METHOD'])) {
            throw new Exception\MethodNotAllowedException('This request does not support HTTP method ' . $_SERVER['REQUEST_METHOD'], 1492589304);
        }

        $parameters = [];
        $basicGetParams = ['route', 'id', 'eID'];
        $limitedGetParams = ($_SERVER['REQUEST_METHOD'] !== 'GET');

        foreach ($_GET as $key => $_) {
            if (!in_array($key, $basicGetParams) && !$limitedGetParams) {
                $parameters[$key] = GeneralUtility::_GET($key);
            }
        }

        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'], true)) {
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
        } elseif (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'DELETE'], true)) {
            foreach ($_GET as $key => $_) {
                if (!in_array($key, $basicGetParams)) {
                    $parameters[$key] = GeneralUtility::_GET($key);
                }
            }
        }

        $this->initializeTSFE();

        // Request is by default NOT authenticated
        $parameters['_authenticated'] = false;
        $GLOBALS['SIMPLE_API']['authenticated'] = false;
        $parameters['_demo'] = false;
        $parameters['_method'] = $_SERVER['REQUEST_METHOD'];
        $parameters['_userAgent'] = $_SERVER['HTTP_USER_AGENT'];
        if (!empty($_SERVER['HTTP_X_AUTHORIZATION'])) {
            // No caching by default for authenticated requests
            $this->maxAge = 0;

            $accessToken = $_SERVER['HTTP_X_AUTHORIZATION'];
            $authenticationHandler = HandlerService::decodeHandler('/authenticate');
            if ($authenticationHandler) {
                /** @var AbstractHandler $authenticationObj */
                $authenticationObj = $objectManager->get($authenticationHandler['class'], $this, $fullRequestUri);
                if (!($authenticationObj instanceof AbstractHandler)) {
                    throw new \RuntimeException('Handler for route ' . $authenticationObj['route'] . ' does not implement \\Causal\\SimpleApi\\Controller\\AbstractHandler', 1492589310);
                }
                $authenticationObj->initialize();
                $authenticationData = $authenticationObj->handle('/authenticate', $accessToken, $parameters);
                if ($authenticationData['success']) {
                    static::getLogger()->debug('Successful authentication');
                    $parameters['_authenticated'] = true;
                    $GLOBALS['SIMPLE_API']['authenticated'] = true;
                    foreach ($authenticationData as $key => $value) {
                        if ($key !== 'success') {
                            $GLOBALS['SIMPLE_API'][$key] = $value;
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
            throw new Exception\ForbiddenException('Access to this API is restricted to authenticated users.', 1455980530);
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

        ExtensionManagementUtility::loadExtTables();
        $hookObj->initialize();
        $data = $hookObj->handle(
            $route,
            $subroute,
            $parameters
        );

        $duration = 1000 * (microtime(true) - $start);
        $logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger('Causal\\SimpleApiDebug');
        $logger->debug(round($duration, 2) . ' (ms) ' . $_SERVER['REQUEST_METHOD'] . ' ' . $requestUri);

        return $data;
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
     */
    protected function usageAction()
    {
        $settings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get($this->extKey);

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

        /** @var TemplateView $view */
        $view = $objectManager->get(TemplateView::class);
        $view->setControllerContext($controllerContext);

        // Set the paths to the template resources
        $privatePath = ExtensionManagementUtility::extPath($this->extKey) . 'Resources/Private/';
        $view->setLayoutRootPaths([$privatePath . 'Layouts']);
        $view->setTemplateRootPaths([$privatePath . 'Templates']);
        $view->setPartialRootPaths([$privatePath . 'Partials']);

        $view->assign('settings', $settings);

        if (!empty($settings['siteIdentifier'])) {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            try {
                $site = $siteFinder->getSiteByIdentifier($settings['siteIdentifier']);
                $view->assign('newVersionUri', (string)$site->getBase());
            } catch (SiteNotFoundException $e) {
                // Nothing to do
            }
        }

        $apiHandlers = [];
        foreach (HandlerService::getAvailableApiHandlers() as $apiHandler) {
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
            foreach (HandlerService::getAvailableApiPatternHandlers() as $apiHandler) {
                list($baseRoute, ) = explode('/', ltrim($apiHandler['route'], '/'), 2);
                if ('/' . $baseRoute === $route) {
                    $classHandler = $apiHandler['class'];
                    $additionalDocumentation = $classHandler::getDocumentation($apiHandler['route']);
                    if (!empty($additionalDocumentation)) {
                        $documentation = array_merge($documentation, $additionalDocumentation);
                    }
                }
            }

            $contentType = empty($handler['contentType'])
                ? 'application/x-www-form-urlencoded'
                : $handler['contentType'];

            $baseUrl = $this->getBaseUrl();
            $view->assignMultiple([
                'host' => $this->getHost(),
                'baseUrl' => $baseUrl,
                'querySeparator' => strpos($baseUrl, '?') === false ? '?' : '&',
                'contentType' => $contentType,
                'intro' => 'Click ' . $this->getDescriptionLink() . ' for a complete list of routes.',
                'route' => $handler['route'],
                'methods' => $documentation,
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
                $suffix = ' <span class="badge bg-danger">deprecated</span>';
            }
        } else {
            $label = 'here';
        }

        $url = '/?' . implode('&', $parameters);
        return '<a href="' . htmlspecialchars($url) . '">' . $label . '</a>' . $suffix;
    }

    /**
     * Initializes TSFE and sets $GLOBALS['TSFE'].
     */
    protected function initializeTSFE()
    {
        $typo3Branch = (new Typo3Version())->getBranch();
        $siteOrId = GeneralUtility::_GP('id');
        $siteLanguageOrType = (int)GeneralUtility::_GP('type');
        $locale = GeneralUtility::_GET('locale');
        if (!empty($locale)) {
            $locale = strtolower(substr($locale, 0, 2));
        }

        if (version_compare($typo3Branch, '10.4', '>=')) {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

            try {
                $siteOrId = $siteFinder->getSiteByIdentifier('-api');
            } catch (SiteNotFoundException $e) {
                $siteOrId = current($siteFinder->getAllSites());
            }

            $siteLanguageOrType = $siteOrId->getDefaultLanguage();
            if (!empty($locale)) {
                foreach ($siteOrId->getAllLanguages() as $siteLanguage) {
                    if ($siteLanguage->getTwoLetterIsoCode() === $locale) {
                        $siteLanguageOrType = $siteLanguage;
                        break;
                    }
                }
            }
        }
        $GLOBALS['TSFE'] = GeneralUtility::makeInstance(
            \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::class,
            $GLOBALS['TYPO3_CONF_VARS'],
            $siteOrId,
            $siteLanguageOrType
        );
        if (version_compare($typo3Branch, '10.4', '>=')) {
            $context = $GLOBALS['TSFE']->getContext();
        } else {
            $context = $GLOBALS['TSFE']->context;
        }
        $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class, $context);
        $GLOBALS['TSFE']->tmpl = GeneralUtility::makeInstance(TemplateService::class, $context);
        // Ensure FileReference and other mapping from Extbase are taken into account
        $GLOBALS['TSFE']->tmpl->setProcessExtensionStatics(true);
        $GLOBALS['TSFE']->tmpl->start([]);

        if (version_compare($typo3Branch, '10.4', '<') && !empty($locale)) {
            // Initialize language
            $language = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('sys_language')
                ->select(
                    ['uid'],
                    'sys_language',
                    [
                        'language_isocode' => $locale,
                    ]
                )
                ->fetch();
            if (!empty($language['uid'])) {
                $GLOBALS['TSFE']->config['config']['language'] = $locale;
                $GLOBALS['TSFE']->config['config']['sys_language_uid'] = (int)$language['uid'];
                $GLOBALS['TSFE']->config['config']['sys_language_mode'] = 'ignore';
            }
        }
        $GLOBALS['TSFE']->settingLanguage();
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
