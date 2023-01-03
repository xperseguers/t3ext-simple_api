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
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   2016-2023 Causal Sàrl
 * @license     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class AnchorViewHelper extends \TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper
{

    /**
     * Arguments initialization.
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('name', 'string', 'Header', true);
    }

    /**
     * Returns an anchor name.
     *
     * @return string Rendered string
     */
    public function render(): string
    {
        $content = str_replace('/', '-', strip_tags($this->arguments['name']));
        $content = trim($content, '-');

        return trim($content);
    }
}
