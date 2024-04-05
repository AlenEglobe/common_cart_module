<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture(
    'Magento/Reward/_files/customer_quote_with_reward_points_rollback.php'
);
Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company_rollback.php');
