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

/**
 * eID controller.
 *
 * @category    Controller
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   2018-2021 Causal Sàrl
 * @license     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class EidController
{

    /**
     * Invokes the API handler and dispatches the request.
     */
    public function start()
    {
        /** @var ApiController $output */
        $output = GeneralUtility::makeInstance(ApiController::class);

        try {
            $ret = $output->dispatch();
        } catch (\Causal\SimpleApi\Exception\JsonMessageException $e) {
            header($e::HTTP_STATUS);
            $contentType = 'application/json';
            $payload = json_encode($e->getData());
            header('Content-Length: ' . strlen($payload));
            header('Content-Type: ' . $contentType);
            echo $payload;
            exit;
        } catch (\Causal\SimpleApi\Exception\AbstractException $e) {
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
        exit;
    }
}

$typo3Branch = class_exists(\TYPO3\CMS\Core\Information\Typo3Version::class)
    ? (new \TYPO3\CMS\Core\Information\Typo3Version())->getBranch()
    : TYPO3_branch;
if (version_compare($typo3Branch, '9.5', '<')) {
    $controller = new EidController();
    $controller->start();
}
