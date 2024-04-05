<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\DataObjectHelper;
use Magento\OfflinePayments\Model\Checkmo;
use Magento\Quote\Api\BillingAddressManagementInterface;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\PaymentInterfaceFactory;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMaskFactory;
use Magento\Quote\Model\ShippingAddressManagementInterface;
use Magento\TestFramework\Helper\Bootstrap;

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = Bootstrap::getObjectManager()->create(CustomerRepositoryInterface::class);
$quoteManager = Bootstrap::getObjectManager()->create(CartManagementInterface::class);
$cartItemRepository = Bootstrap::getObjectManager()->create(CartItemRepositoryInterface::class);
$customer = $customerRepository->get('customercompany22@example.com');
$quoteId = $quoteManager->createEmptyCartForCustomer($customer->getId());
/** @var CartItemInterface $item */
$item = Bootstrap::getObjectManager()->create(CartItemInterface::class);
$item->setQuoteId($quoteId);
$item->setSku('simple');
$item->setQty(1);
$cartItemRepository->save($item);

/** @var CartRepositoryInterface $quoteRepository */
$quoteRepository = Bootstrap::getObjectManager()->create(CartRepositoryInterface::class);
$quote = $quoteRepository->get($quoteId);
$quote->setIsActive(true);
$quoteResource = Bootstrap::getObjectManager()->create(QuoteResource::class);

$quoteResource->save($quote);

$quoteIdMask = Bootstrap::getObjectManager()->create(QuoteIdMask::class);
$quoteIdMask->setQuoteId($quote->getId());
$quoteIdMask->setMaskedId('cart_checkout_customer_mask');
$quoteIdMask->setDataChanges(true);

/** @var QuoteIdMaskResource $maskedIdResource */
$maskedIdResource = Bootstrap::getObjectManager()->create(QuoteIdMaskFactory::class)->create();
$maskedIdResource->save($quoteIdMask);

/** @var AddressInterfaceFactory $quoteAddressFactory */
$quoteAddressFactory = Bootstrap::getObjectManager()->get(AddressInterfaceFactory::class);
/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = Bootstrap::getObjectManager()->get(DataObjectHelper::class);
/** @var ShippingAddressManagementInterface $shippingAddressManagement */
$shippingAddressManagement = Bootstrap::getObjectManager()->get(ShippingAddressManagementInterface::class);
/** @var BillingAddressManagementInterface $billingAddressManagement */
$billingAddressManagement = Bootstrap::getObjectManager()->get(BillingAddressManagementInterface::class);
/** @var ShippingInformationInterfaceFactory $shippingInformationFactory */
$shippingInformationFactory = Bootstrap::getObjectManager()->get(ShippingInformationInterfaceFactory::class);
/** @var ShippingInformationManagementInterface $shippingInformationManagement */
$shippingInformationManagement = Bootstrap::getObjectManager()->get(ShippingInformationManagementInterface::class);
/** @var PaymentInterfaceFactory $paymentFactory */
$paymentFactory = Bootstrap::getObjectManager()->get(PaymentInterfaceFactory::class);
/** @var PaymentMethodManagementInterface $paymentMethodManagement */
$paymentMethodManagement = Bootstrap::getObjectManager()->get(PaymentMethodManagementInterface::class);

$quoteAddressData = [
    AddressInterface::KEY_TELEPHONE => 3468676,
    AddressInterface::KEY_POSTCODE => '75477',
    AddressInterface::KEY_COUNTRY_ID => 'US',
    AddressInterface::KEY_CITY => 'CityM',
    AddressInterface::KEY_COMPANY => 'CompanyName',
    AddressInterface::KEY_STREET => 'Green str, 67',
    AddressInterface::KEY_LASTNAME => 'Smith',
    AddressInterface::KEY_FIRSTNAME => 'John',
    AddressInterface::KEY_REGION_ID => 1,
];
$quoteAddress = $quoteAddressFactory->create();
$dataObjectHelper->populateWithArray($quoteAddress, $quoteAddressData, AddressInterfaceFactory::class);
$shippingAddressManagement->assign($quote->getId(), $quoteAddress);
$billingAddressManagement->assign($quote->getId(), $quoteAddress);
$shippingInformation = $shippingInformationFactory->create([
    'data' => [
        ShippingInformationInterface::SHIPPING_ADDRESS => $quoteAddress,
        ShippingInformationInterface::SHIPPING_CARRIER_CODE => 'flatrate',
        ShippingInformationInterface::SHIPPING_METHOD_CODE => 'flatrate',
    ],
]);
$shippingInformationManagement->saveAddressInformation($quote->getId(), $shippingInformation);
$payment = $paymentFactory->create([
    'data' => [
        PaymentInterface::KEY_METHOD => Checkmo::PAYMENT_METHOD_CHECKMO_CODE,
    ]
]);
$paymentMethodManagement->set($quote->getId(), $payment);
