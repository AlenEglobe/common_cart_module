<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture(
    'Magento/GiftCardAccount/_files/giftcardaccount_rollback.php'
);

Resolver::getInstance()->requireDataFixture(
    'Magento/Checkout/_files/quote_with_virtual_product_and_address_rollback.php'
);

Resolver::getInstance()->requireDataFixture(
    'Magento/Company/_files/company_rollback.php'
);
