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

namespace Causal\SimpleApi\Cache\Backend;

use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;

/**
 * Class Typo3DatabaseNoFlushBackend
 */
class Typo3DatabaseNoFlushBackend extends Typo3DatabaseBackend
{
    public function flush()
    {
        // We do not want the whole cache tables to be actually flushed!
    }
}
