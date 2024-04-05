<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Sales\Api\Data\OrderAddressInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Api\Data\OrderPaymentInterfaceFactory;
use Magento\Sales\Api\Data\OrderItemInterfaceFactory;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Catalog/_files/products_new.php');
$addressData = include __DIR__ . '/address_data.php';

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

$customer1 = $objectManager->create(\Magento\Customer\Model\Customer::class);
$customer1->setWebsiteId(1)
    ->setEmail('customer1@example.com')
    ->setPassword('password')
    ->setGroupId(1)
    ->setStoreId(1)
    ->setIsActive(1)
    ->setFirstname('John')
    ->setLastname('Smith')
    ->setDefaultBilling(1)
    ->setDefaultShipping(1);
$customer1->isObjectNew(true);
$customer1->save();

$customer2 = $objectManager->create(\Magento\Customer\Model\Customer::class);
$customer2->setWebsiteId(1)
    ->setEmail('customer2@example.com')
    ->setPassword('password')
    ->setGroupId(1)
    ->setStoreId(1)
    ->setIsActive(1)
    ->setFirstname('Jane')
    ->setLastname('Smith')
    ->setDefaultBilling(1)
    ->setDefaultShipping(1);
$customer2->isObjectNew(true);
$customer2->save();

/** @var \Magento\Sales\Model\Order $orderForCustomer1 */
$orderForCustomer1 = $objectManager->create(\Magento\Sales\Model\Order::class);

$billingAddress = $objectManager->create(\Magento\Sales\Model\Order\Address::class, ['data' => $addressData]);
$billingAddress->setAddressType('billing');

$shippingAddress = clone $billingAddress;
$shippingAddress->setId(null)->setAddressType('shipping');

$orderForCustomer1->setIncrementId(
    '100000001'
)->setState(
    \Magento\Sales\Model\Order::STATE_PROCESSING
)->setStatus(
    $orderForCustomer1->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
)->setSubtotal(
    100
)->setGrandTotal(
    100
)->setBaseSubtotal(
    100
)->setBaseGrandTotal(
    100
)->setCustomerIsGuest(
    true
)->setCustomerId(
    $customer1->getId()
)->setCustomerEmail(
    $customer1->getEmail()
)->setBillingAddress(
    $billingAddress
)->setShippingAddress(
    $shippingAddress
)->setStoreId(
    $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class)->getStore()->getId()
)->setPayment(
    $objectManager->create(\Magento\Sales\Api\Data\OrderPaymentInterface::class)->setMethod('free')
);
$orderForCustomer1->isObjectNew(true);
$orderForCustomer1->save();

/** @var \Magento\Sales\Model\Order $secondOrderForCustomer1 */
$secondOrderForCustomer1 = $objectManager->create(\Magento\Sales\Model\Order::class);

$billingAddress = $objectManager->create(\Magento\Sales\Model\Order\Address::class, ['data' => $addressData]);
$billingAddress->setAddressType('billing');

$shippingAddress = clone $billingAddress;
$shippingAddress->setId(null)->setAddressType('shipping');

$secondOrderForCustomer1->setIncrementId(
    '100000011'
)->setState(
    \Magento\Sales\Model\Order::STATE_PROCESSING
)->setStatus(
    $orderForCustomer1->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
)->setSubtotal(
    100
)->setGrandTotal(
    100
)->setBaseSubtotal(
    100
)->setBaseGrandTotal(
    100
)->setCustomerIsGuest(
    true
)->setCustomerId(
    $customer1->getId()
)->setCustomerEmail(
    $customer1->getEmail()
)->setBillingAddress(
    $billingAddress
)->setShippingAddress(
    $shippingAddress
)->setStoreId(
    $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class)->getStore()->getId()
)->setPayment(
    $objectManager->create(\Magento\Sales\Api\Data\OrderPaymentInterface::class)->setMethod('free')
);
$secondOrderForCustomer1->isObjectNew(true);
$secondOrderForCustomer1->save();

/** @var \Magento\Sales\Model\Order $orderForCustomer2 */
$orderForCustomer2 = $objectManager->create(\Magento\Sales\Model\Order::class);

$billingAddress = $objectManager->create(\Magento\Sales\Model\Order\Address::class, ['data' => $addressData]);
$billingAddress->setAddressType('billing');

$shippingAddress = clone $billingAddress;
$shippingAddress->setId(null)->setAddressType('shipping');

$orderForCustomer2->setIncrementId(
    '100000002'
)->setState(
    \Magento\Sales\Model\Order::STATE_PROCESSING
)->setStatus(
    $orderForCustomer2->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
)->setSubtotal(
    100
)->setGrandTotal(
    100
)->setBaseSubtotal(
    100
)->setBaseGrandTotal(
    100
)->setCustomerIsGuest(
    false
)->setCustomerId(
    $customer2->getId()
)->setCustomerEmail(
    $customer2->getEmail()
)->setBillingAddress(
    $billingAddress
)->setShippingAddress(
    $shippingAddress
)->setStoreId(
    $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class)->getStore()->getId()
)->setPayment(
    $objectManager->create(\Magento\Sales\Api\Data\OrderPaymentInterface::class)->setMethod('free')
);

$orderForCustomer2->isObjectNew(true);
$orderForCustomer2->save();
