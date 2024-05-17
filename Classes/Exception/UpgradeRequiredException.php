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

namespace Causal\SimpleApi\Exception;

use TYPO3\CMS\Core\Utility\HttpUtility;

/**
 * HTTP/1.1 426 Upgrade Required.
 *
 * @category    Exception
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   2019-2024 Causal Sàrl
 * @license     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class UpgradeRequiredException extends AbstractException
{
    public const HTTP_STATUS = HttpUtility::HTTP_STATUS_426;
    public const HTTP_STATUS_CODE = 426;
}
