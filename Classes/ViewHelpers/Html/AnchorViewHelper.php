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

namespace Causal\SimpleApi\ViewHelpers\Html;

/**
 * Anchor ViewHelper.
 *
 * @category    ViewHelpers
 * @package     simple_api
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   2016-2017 Causal Sàrl
 * @license     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class AnchorViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper
{

    /**
     * Returns a formatted range of dates.
     *
     * @param string $name Headers
     * @return string Rendered string
     */
    public function render($name)
    {
        $content = str_replace('/', '-', strip_tags($name));
        $content = trim($content, '-');

        return trim($content);
    }

}
