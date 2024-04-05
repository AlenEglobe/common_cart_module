<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMaskFactory;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/NegotiableQuote/_files/cart_with_item_for_customer.php');

$objectManager = Bootstrap::getObjectManager();
/** @var CartRepositoryInterface $quoteRepository */
$quoteRepository = $objectManager->get(CartRepositoryInterface::class);
/** @var CartManagementInterface $quoteManagement */
$quoteManagement = $objectManager->get(CartManagementInterface::class);
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
$customer = $customerRepository->get('customercompany22@example.com');

$quote = $quoteRepository->getActiveForCustomer($customer->getId());

$addressData = [
    'region' => 'CA',
    'postcode' => '11111',
    'lastname' => 'lastname',
    'firstname' => 'firstname',
    'street' => 'street',
    'city' => 'Los Angeles',
    'email' => 'customercompany22@example.com',
    'telephone' => '11111111',
    'country_id' => 'US'
];
/** @var Magento\Quote\Api\Data\AddressInterface $billingAddress */
$billingAddress = Bootstrap::getObjectManager()->create(
    Magento\Quote\Api\Data\AddressInterface::class,
    ['data' => $addressData]
);
$billingAddress->setCustomerAddressId(null);
$billingAddressManagement = Bootstrap::getObjectManager()->create(
    Magento\Quote\Api\BillingAddressManagementInterface::class
);
$billingAddressManagement->assign($quote->getId(), $billingAddress, true);

$quote->getShippingAddress()->setShippingMethod('flatrate_flatrate');
$quote->getShippingAddress()->setCollectShippingRates(true);
$quote->getShippingAddress()->collectShippingRates();
$quote->getPayment()->setMethod('checkmo');


$quoteIdMask = Bootstrap::getObjectManager()->create(QuoteIdMask::class);
$quoteIdMask->setQuoteId($quote->getId());
$quoteIdMask->setMaskedId('quote_mask');
$quoteIdMask->setDataChanges(true);

/** @var QuoteIdMaskResource $maskedIdResource */
$maskedIdResource = Bootstrap::getObjectManager()->create(QuoteIdMaskFactory::class)->create();
$maskedIdResource->save($quoteIdMask);
