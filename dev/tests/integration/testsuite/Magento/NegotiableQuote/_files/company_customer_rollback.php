<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Helper\Bootstrap;

use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

/** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
$customerRepository = Bootstrap::getObjectManager()->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
try {
    $customer = $customerRepository->get('customercompany22@example.com');
    $customerRepository->delete($customer);
} catch (\Exception $e) {

}

Resolver::getInstance()->requireDataFixture(
    'Magento/NegotiableQuote/_files/company_with_customer_for_quote_rollback.php'
);
