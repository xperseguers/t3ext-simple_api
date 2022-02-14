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

namespace Causal\SimpleApi\Tests\Unit\Controller;

/**
 * Test case for class \Causal\SimpleApi\Controller\ApiController.
 *
 * @category    Unit/Controller
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   2012-2022 Causal Sàrl
 * @license     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class ApiControllerTest extends \TYPO3\CMS\Extbase\Tests\Unit\BaseTestCase
{

    /** @var array */
    protected $origApiHandlers;

    /** @var \Causal\SimpleApi\Controller\ApiController */
    protected $fixture;

    public function setUp()
    {
        $this->origApiHandlers = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['simple_api']['apiHandlers'];
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['simple_api']['apiHandlers'] = [];
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['simple_api']['apiHandlers'][] = [
            'route' => '/members',
            'class' => '__class_1__',
        ];
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['simple_api']['apiHandlers'][] = [
            'route' => '/membership',
            'class' => '__class_2__',
        ];

        $proxyClass = $this->buildAccessibleProxy(\Causal\SimpleApi\Controller\ApiController::class);
        $this->fixture = new $proxyClass();
    }

    public function tearDown()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['simple_api']['apiHandlers'] = $this->origApiHandlers;
        unset($this->origApiHandlers);
        unset($this->fixture);
        unset($GLOBALS['_ENV']['PHP_UNIT']);
    }

    /**
     * @test
     */
    public function nonExistingRouteResolvesToNullHandler()
    {
        $actual = $this->fixture->_call('decodeHandler', '/non-existing-route');
        self::assertNull($actual);
    }

    /**
     * @test
     */
    public function routeWithPartialMatchResolvesToNullHandler()
    {
        $actual = $this->fixture->_call('decodeHandler', '/member');
        self::assertNull($actual);
    }

    /**
     * @test
     */
    public function routeWithMatchOnShorterHandlerResolvesToTheCorrectHandler()
    {
        $actual = $this->fixture->_call('decodeHandler', '/membership');
        self::assertEquals('__class_2__', $actual['class']);
    }

    /**
     * @test
     */
    public function subrouteIsRoutedToHandler()
    {
        $actual = $this->fixture->_call('decodeHandler', '/members/34');
        self::assertEquals('__class_1__', $actual['class']);
    }

    /**
     * @test
     */
    public function routeWithParametersIsRoutedToHandler()
    {
        $actual = $this->fixture->_call('decodeHandler', '/members?foo=bar');
        self::assertEquals('__class_1__', $actual['class']);
    }
}
