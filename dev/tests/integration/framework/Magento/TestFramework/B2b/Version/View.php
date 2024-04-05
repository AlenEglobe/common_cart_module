<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\TestFramework\B2b\Version;

use Magento\TestFramework\Core\Version\View as CoreView;

/**
 * Class for magento version flag.
 */
class View extends CoreView
{
    /**
     * @inheritdoc
     */
    public function isVersionUpdated(): bool
    {
        return true;
    }
}
