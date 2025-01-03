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

namespace Causal\SimpleApi\Middleware;

use Causal\SimpleApi\Controller\AbstractHandler;
use Causal\SimpleApi\Event\InitializeParametersEvent;
use Causal\SimpleApi\Exception;
use Causal\SimpleApi\Exception\AbstractException;
use Causal\SimpleApi\Exception\JsonMessageException;
use Causal\SimpleApi\Service\HandlerService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;
use TYPO3\CMS\Fluid\View\TemplateView;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class ApiMiddleware
 *
 * @category    Middleware
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   2022-2025 Causal Sàrl
 * @license     https://www.gnu.org/licenses/lgpl-3.0.html GNU Lesser General Public License, version 3 or later
 */
class ApiMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var array
     */
    protected $settings;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->settings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('simple_api');

        if (!empty($this->settings['siteIdentifier'])) {
            $tempPath = Environment::getVarPath() . '/transient/';
            if (!is_dir($tempPath)) {
                GeneralUtility::mkdir_deep($tempPath);
            }
            $cacheFile = $tempPath . $this->settings['siteIdentifier'] . '.url';
            $baseUri = null;

            if (is_readable($cacheFile)) {
                $baseUri = file_get_contents($cacheFile);
            }
            if (empty($baseUri)) {
                $baseUri = (string)$this->getSite()->getBase();
                GeneralUtility::writeFile($cacheFile, $baseUri);
            }

            $requestUri = $request->getUri();
            if (str_starts_with((string)$requestUri, $baseUri)) {
                $schemeHost = $requestUri->getScheme() . '://' . $requestUri->getAuthority();
                $apiPrefix = substr($baseUri, strlen($schemeHost));
                $apiRequest = $request
                    ->withAttribute('site', $this->getSite())
                    ->withUri($requestUri->withPath(substr($requestUri->getPath(), strlen($apiPrefix))));

                try {
                    $ret = $this->handle($apiRequest, $request);
                } catch (PageNotFoundException $e) {
                    $ret = $this->getXmlResponse($e->getMessage(), 404);
                } catch (JsonMessageException $e) {
                    $ret = new JsonResponse($e->getData(), $e::HTTP_STATUS_CODE);
                } catch (AbstractException $e) {
                    $ret = $this->getXmlResponse($e->getMessage(), $e::HTTP_STATUS_CODE, $e->getCode());
                } catch (\Exception $e) {
                    $ret = $this->getXmlResponse($e->getMessage(), 500, $e->getCode());
                }

                // CORS
                $allowedOrigins = trim($this->settings['cors.']['allowOrigins'] ?? '');
                if (!empty($allowedOrigins)) {
                    if ($allowedOrigins === '*') {
                        $ret = $ret
                            ->withHeader('Access-Control-Allow-Origin', '*')
                            ->withHeader('Access-Control-Allow-Credentials', 'true');
                    } else {
                        $origin = $request->getHeader('origin');
                        if (GeneralUtility::inList($allowedOrigins, $origin[0] ?? '')) {
                            $ret = $ret
                                ->withHeader('Access-Control-Allow-Origin', $origin)
                                ->withHeader('Access-Control-Allow-Credentials', 'true');
                        }
                    }
                }

                return $ret;
            }
        }

        return $handler->handle($request);
    }

    /**
     * @return Site
     * @throws \TYPO3\CMS\Core\Exception\SiteNotFoundException
     */
    protected function getSite(): Site
    {
        static $site = null;
        if ($site === null) {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByIdentifier($this->settings['siteIdentifier']);
        }
        return $site;
    }

    protected function handle(ServerRequestInterface $request, ServerRequestInterface $origRequest): ResponseInterface
    {
        $start = microtime(true);
        $maxAge = 86400;    // 86400 = 1 day of caching by default

        $path = '/' . ltrim($request->getUri()->getPath(), '/');
        $apiHandler = HandlerService::decodeHandler($path);
        $this->logger->debug('handle()', ['route' => $request->getUri()->getPath(), 'handler' => $apiHandler]);

        if ($apiHandler === null) {
            if (rtrim($path, '/') === '') {
                return $this->usage($origRequest);
            }
            throw new PageNotFoundException('Action not found.');
        }

        /** @var AbstractHandler $hookObj */
        // TODO: Switch to GeneralUtility::makeInstance() once there are no more "inject*" method in use
        //$hookObj = GeneralUtility::makeInstance($apiHandler['class']);
        $hookObj = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class)->get($apiHandler['class']);
        if (!($hookObj instanceof AbstractHandler)) {
            throw new \RuntimeException('Handler for route ' . $apiHandler['route'] . ' does not implement \\Causal\\SimpleApi\\Controller\\AbstractHandler', 1646921270);
        }

        if (!GeneralUtility::inList($apiHandler['methods'], $request->getMethod())) {
            throw new Exception\MethodNotAllowedException('This request does not support HTTP method ' . $request->getMethod(), 1646921278);
        }

        $parameters = $this->initializeParameters($request, $apiHandler, $maxAge);

        // If access is restricted and the user is either not authenticated or is a demo user, then access is forbidden
        // Access to restricted data with a demo user should not be handled by marking the api handler as restricted but
        // explicitly checking for the "demo" flag and handling it accordingly.
        if (($apiHandler['restricted'] ?? false) && (!$parameters['_authenticated'] || $parameters['_demo'])) {
            $this->logger->notice('Access denied');
            throw new Exception\ForbiddenException('Access to this API is restricted to authenticated users.', 1646923305);
        }

        $this->initializeTSFE($request);

        // Invoke the API handler
        [$route, $subroute] = $this->decodeRouteAndSubroute($request, $apiHandler);
        ExtensionManagementUtility::loadExtTables();
        $hookObj->initialize();
        $data = $hookObj->handle(
            $route,
            $subroute,
            $parameters
        );

        $duration = 1000 * (microtime(true) - $start);
        $this->logger->debug(round($duration, 2) . ' (ms) ' . $request->getMethod() . ' ' . $request->getUri()->getPath());

        if ($data === null) {
            throw new PageNotFoundException('Action not found.');
        }

        return new JsonResponse($data, 200, [
            'Cache-Control: max-age=' . $maxAge,
        ]);
    }

    /**
     * Initializes the parameters to be passed to the actual API handler.
     *
     * @param ServerRequestInterface $request
     * @param array $apiHandler
     * @param int $maxAge
     * @return array
     */
    protected function initializeParameters(ServerRequestInterface $request, array $apiHandler, int &$maxAge = 0): array
    {
        $parameters = $request->getQueryParams();

        if (in_array($request->getMethod(), ['POST', 'PUT'], true)) {
            /**
             * $request->getParsedBody() only works for 'PUT', 'PATCH' and 'DELETE'
             * and only for "multipart/form-data" parameters (not "application/json")
             * @see \TYPO3\CMS\Core\Http\ServerRequestFactory::fromGlobals()
             */
            $body = file_get_contents('php://input');
            if ($apiHandler['contentType'] ?? '' === 'application/json') {
                $data = json_decode((string)$body, true);
                if (is_array($data)) {
                    $parameters = array_merge($parameters, $data);
                }
            } else {
                $parameters = (array)$body;
            }
        }

        $parameters['_request'] = $request;

        // Request is by default NOT authenticated
        $parameters['_authenticated'] = false;
        $GLOBALS['SIMPLE_API']['authenticated'] = false;
        $parameters['_demo'] = false;

        // Deprecated parameters (should rely on '_request' instead)
        $parameters['_method'] = $request->getMethod();
        $parameters['_userAgent'] = $request->getServerParams()['HTTP_USER_AGENT'] ?? '';

        $accessToken = $request->getServerParams()['HTTP_X_AUTHORIZATION'] ?? '';
        if (!empty($accessToken)) {
            // No caching by default for authenticated requests
            $maxAge = 0;

            $authenticationHandler = HandlerService::decodeHandler('/authenticate');
            if ($authenticationHandler !== null) {
                /** @var AbstractHandler $authenticationObj */
                $authenticationObj = GeneralUtility::makeInstance($authenticationHandler['class']);
                if (!($authenticationObj instanceof AbstractHandler)) {
                    throw new \RuntimeException('Handler for route ' . $authenticationObj['route'] . ' does not implement \\Causal\\SimpleApi\\Controller\\AbstractHandler', 1646922849);
                }
                $authenticationObj->initialize();
                $authenticationData = $authenticationObj->handle('/authenticate', $accessToken, $parameters);
                if ($authenticationData['success']) {
                    $this->logger->debug('Successful authentication');
                    $parameters['_authenticated'] = true;
                    $GLOBALS['SIMPLE_API']['authenticated'] = true;
                    foreach ($authenticationData as $key => $value) {
                        if ($key !== 'success') {
                            $GLOBALS['SIMPLE_API'][$key] = $value;
                            $parameters['_' . $key] = $value;
                        }
                    }
                } else {
                    $this->logger->notice('Invalid authentication', ['token' => $accessToken]);
                }
            }
        }

        $eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);
        $event = new InitializeParametersEvent($parameters);
        $eventDispatcher->dispatch($event);

        return $event->getParameters();
    }

    /**
     * Initializes $GLOBALS['TSFE'].
     */
    protected function initializeTSFE(ServerRequestInterface $request): void
    {
        /** @var Site $site */
        $site = $request->getAttribute('site');

        $locale = $request->getQueryParams()['locale'] ?? null;
        if (!empty($locale)) {
            $locale = strtolower(substr($locale, 0, 2));
        }

        $language = $site->getDefaultLanguage();
        if (!empty($locale)) {
            foreach ($site->getAllLanguages() as $siteLanguage) {
                if ($siteLanguage->getTwoLetterIsoCode() === $locale) {
                    $language = $siteLanguage;
                    break;
                }
            }
        }

        $pageArgument = GeneralUtility::makeInstance(PageArguments::class, $site->getRootPageId(), '0', []);
        $GLOBALS['TSFE'] = $typoScriptFrontendController = GeneralUtility::makeInstance(
            TypoScriptFrontendController::class,
            new Context(),
            $site,
            $language,
            $pageArgument,
            new FrontendUserAuthentication()
        );

        $context = $typoScriptFrontendController->getContext();
        $typoScriptFrontendController->sys_page = GeneralUtility::makeInstance(PageRepository::class, $context);
        $typoScriptFrontendController->tmpl = GeneralUtility::makeInstance(TemplateService::class, $context);
        // Ensure FileReference and other mapping from Extbase are taken into account
        $typoScriptFrontendController->tmpl->setProcessExtensionStatics(true);
        $typoScriptFrontendController->tmpl->start([]);

        // Needed by Extbase's TranslationViewHelper and LocalizationUtility:
        $GLOBALS['TYPO3_REQUEST'] = $request->withAttribute('language', $language);
        $GLOBALS['TSFE']->page = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('pages')
            ->select(
                ['*'],
                'pages',
                [
                    'uid' => $site->getRootPageId(),
                ]
            )
            ->fetchAssociative();
        $reflectionMethod = new \ReflectionMethod(TypoScriptFrontendController::class, 'settingLanguage');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($typoScriptFrontendController, $request);
        Locales::setSystemLocaleFromSiteLanguage($language);
    }

    protected function decodeRouteAndSubroute(ServerRequestInterface $request, array $apiHandler): array
    {
        $path = '/' . ltrim($request->getUri()->getPath(), '/');
        if ($apiHandler['hasPattern'] ?? false) {
            list($baseRoute, ) = explode('/', ltrim($apiHandler['route'], '/'), 2);
            $baseRoute = '/' . $baseRoute;
            $subroute = substr($path, strlen($baseRoute) + 1);
            $route = $baseRoute;
        } else {
            $subroute = ltrim((string)substr($path, strlen($apiHandler['route'])), '/');
            $route = $apiHandler['route'];
        }

        return [$route, urldecode($subroute ?: '')];
    }

    protected function usage(ServerRequestInterface $request): ResponseInterface
    {
        /** @var ControllerContext $controllerContext */
        $controllerContext = GeneralUtility::makeInstance(ControllerContext::class);
        /** @var \TYPO3\CMS\Extbase\Mvc\Request $extbaseRequest */
        $extbaseRequest = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Mvc\Request::class);
        $extbaseRequest->setControllerExtensionName('simple_api');
        $extbaseRequest->setControllerName('Api');
        $extbaseRequest->setControllerActionName('usage');
        $extbaseRequest->setFormat('html');
        $controllerContext->setRequest($extbaseRequest);

        /** @var TemplateView $view */
        $view = GeneralUtility::makeInstance(TemplateView::class);
        $view->setControllerContext($controllerContext);

        // Set the paths to the template resources
        $privatePath = 'EXT:simple_api/Resources/Private/';
        $view->setLayoutRootPaths([$privatePath . 'Layouts']);
        $view->setTemplateRootPaths([$privatePath . 'Templates']);
        $view->setPartialRootPaths([$privatePath . 'Partials']);

        $view->assign('settings', $this->settings);

        $apiHandlers = [];
        foreach (HandlerService::getAvailableApiHandlers() as $apiHandler) {
            $apiHandlers[$apiHandler['route']] = $apiHandler;
        }
        ksort($apiHandlers);

        $handler = null;
        $route = $request->getQueryParams()['op'] ?? '';
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
                $routes[] = $this->getDescriptionLink($request, $apiHandler);
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

            $view->assignMultiple([
                'host' => $request->getUri()->getHost(),
                'baseUrl' => rtrim($request->getUri()->getPath(), '/'),
                'querySeparator' => '?',
                'contentType' => $contentType,
                'intro' => 'Click ' . $this->getDescriptionLink($request) . ' for a complete list of routes.',
                'route' => $handler['route'],
                'methods' => $documentation,
                'deprecated' => $handler['deprecated'] ?? false,
            ]);
        }

        $html = $view->render();
        return new HtmlResponse($html);
    }

    /**
     * Returns a description link.
     *
     * @param ServerRequestInterface $request
     * @param array $apiHandler
     * @return string
     */
    protected function getDescriptionLink(ServerRequestInterface $request, array $apiHandler = [])
    {
        $parameters = '';
        $suffix = '';
        if (!empty($apiHandler)) {
            $parameters = 'op=' . $apiHandler['route'];
            $label = $apiHandler['route'];
            if (!empty($apiHandler['deprecated'])) {
                $suffix = ' <span class="badge bg-danger">deprecated</span>';
            }
        } else {
            $label = 'here';
        }

        $uri = (string)$request->getUri()->withQuery($parameters);
        return '<a href="' . htmlspecialchars($uri) . '">' . $label . '</a>' . $suffix;
    }

    /**
     * @param string $message
     * @param int $httpCode
     * @param int $errorCode
     * @return ResponseInterface
     */
    protected function getXmlResponse(string $message, int $httpCode, int $errorCode = 0): ResponseInterface
    {
        $ret = (new Response())
            ->withHeader('Content-Type', 'application/xml; charset=utf-8')
            ->withStatus($httpCode);
        $xml = '<Error>';
        if ($errorCode) {
            $xml .= '<Code>' . $errorCode . '</Code>';
        }
        $xml .= '<Message>' . htmlspecialchars($message) . '</Message>';
        $xml .= '</Error>';

        $ret->getBody()->write($xml);
        return $ret;
    }
}
