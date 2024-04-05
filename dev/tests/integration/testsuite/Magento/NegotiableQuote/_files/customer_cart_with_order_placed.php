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
/** @var AddressInterface $quoteShippingAddress */
$quoteShippingAddress = $objectManager->get(AddressInterfaceFactory::class)->create();
/** @var AddressRepositoryInterface $addressRepository */
$addressRepository = $objectManager->get(AddressRepositoryInterface::class);
$quoteShippingAddress->importCustomerAddressData($addressRepository->getById(2));
$quote = $quoteRepository->getActiveForCustomer($customer->getId());
$quote->setReservedOrderId('33333');
$quote->setBillingAddress($quoteShippingAddress);
$quote->setShippingAddress($quoteShippingAddress);
$quote->getShippingAddress()->setShippingMethod('flatrate_flatrate');
$quote->getShippingAddress()->setCollectShippingRates(true);
$quote->getShippingAddress()->collectShippingRates();
$quote->getPayment()->setMethod('checkmo');
$quoteManagement->placeOrder($quote->getId());
