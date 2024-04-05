<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\TestFramework\Workaround\Override\Fixture\Resolver as FixtureResolver;

FixtureResolver::getInstance()->requireDataFixture('Magento/Sales/_files/order_rollback.php');
FixtureResolver::getInstance()->requireDataFixture('Magento/Company/_files/company_rollback.php');
