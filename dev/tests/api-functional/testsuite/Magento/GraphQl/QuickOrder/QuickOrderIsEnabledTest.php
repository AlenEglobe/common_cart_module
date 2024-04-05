<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\QuickOrder;

use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test quick order enabled query
 */
class QuickOrderIsEnabledTest extends GraphQlAbstract
{
    private const QUERY =  <<<QRY
{
    storeConfig {
       quickorder_active
    }
}
QRY;

    #[
        Config('btob/website_configuration/quickorder_active', 1),
    ]
    public function testQuickOrderIsEnabled()
    {
        $this->assertEquals(
            [
                'storeConfig' => [
                    'quickorder_active' => true
                ]
            ],
            $this->graphQlQuery(
                self::QUERY
            )
        );
    }

    #[
        Config('btob/website_configuration/quickorder_active', 0),
    ]
    public function testQuickOrderIsDisabled()
    {
        $this->assertEquals(
            [
                'storeConfig' => [
                    'quickorder_active' => false
                ]
            ],
            $this->graphQlQuery(
                self::QUERY
            )
        );
    }
}
