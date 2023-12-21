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

namespace Causal\SimpleApi\ViewHelpers\Format;

/**
 * ViewHelper to output a content as JSON.
 *
 * @category    ViewHelpers
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   2016-2024 Causal Sàrl
 * @license     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class JsonViewHelper extends \TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper
{

    /**
     * Arguments initialization.
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('data', 'array', 'Data to encode as JSON', true);
    }

    /**
     * Renders the content as json.
     *
     * @return string
     */
    public function render(): string
    {
        $ret = json_encode($this->arguments['data'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return $ret;
    }
}
