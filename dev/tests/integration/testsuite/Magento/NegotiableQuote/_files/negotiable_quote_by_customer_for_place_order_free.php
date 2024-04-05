<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\NegotiableQuote\Model\NegotiableQuoteConverter;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteItemManagementInterface;
use Magento\NegotiableQuote\Model\Quote\History;
use Magento\Payment\Model\Method\Free;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\PaymentInterfaceFactory;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMaskFactory;
use Magento\TestFramework\Helper\Bootstrap;

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = Bootstrap::getObjectManager()->create(CustomerRepositoryInterface::class);
$quoteManager = Bootstrap::getObjectManager()->create(CartManagementInterface::class);
$quoteRepository = Bootstrap::getObjectManager()->create(CartRepositoryInterface::class);
$cartItemRepository = Bootstrap::getObjectManager()->create(CartItemRepositoryInterface::class);
$quoteItemManagement = Bootstrap::getObjectManager()->create(NegotiableQuoteItemManagementInterface::class);
$quoteHistory = Bootstrap::getObjectManager()->create(History::class);
$paymentFactory = Bootstrap::getObjectManager()->get(PaymentInterfaceFactory::class);

$customer = $customerRepository->get('customercompany22@example.com');
$quoteId = $quoteManager->createEmptyCartForCustomer($customer->getId());
$quote = $quoteRepository->get($quoteId);
/** @var CartItemInterface $item */
$item = Bootstrap::getObjectManager()->create(CartItemInterface::class);
$item->setQuoteId($quoteId);
$item->setSku('simple');
$item->setQty(1);
$cartItemRepository->save($item);

$quoteConverter =Bootstrap::getObjectManager()->create(
    NegotiableQuoteConverter::class
);
/** @var NegotiableQuoteInterface $negotiableQuote */
$negotiableQuote = Bootstrap::getObjectManager()->create(
    NegotiableQuoteInterface::class
);
$negotiableQuote->setQuoteId($quoteId);
$negotiableQuote->setQuoteName('quote_customer_send');
$negotiableQuote->setCreatorId($customer->getId());
$negotiableQuote->setCreatorType(UserContextInterface::USER_TYPE_CUSTOMER);
$negotiableQuote->setIsRegularQuote(true);
$negotiableQuote->setNegotiatedPriceType(
    NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT
);
$negotiableQuote->setNegotiatedPriceValue(100);
$negotiableQuote->setStatus(NegotiableQuoteInterface::STATUS_CREATED);
$quote->getExtensionAttributes()->setNegotiableQuote($negotiableQuote);
$negotiableQuote->setSnapshot(json_encode($quoteConverter->quoteToArray($quote)));
$quoteRepository->save($quote);
$quoteItemManagement->updateQuoteItemsCustomPrices($quoteId);

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
$billingAddressManagement->assign($quoteId, $billingAddress, true);

$shippingMethodManagement = Bootstrap::getObjectManager()->create(
    \Magento\Quote\Model\ShippingMethodManagement::class
);
$shippingMethodManagement->set($quoteId, 'freeshipping', 'freeshipping');


$payment = $paymentFactory->create([
    'data' => [
        PaymentInterface::KEY_METHOD => Free::PAYMENT_METHOD_FREE_CODE
    ]
]);

$paymentMethodManagement = Bootstrap::getObjectManager()->get(
    \Magento\Quote\Api\PaymentMethodManagementInterface::class
);
$paymentMethodManagement->set($quoteId, $payment);

$quoteItemManagement->updateQuoteItemsCustomPrices($quoteId);

$quote = $quoteRepository->get($quoteId);
$negotiableQuote = $quote->getExtensionAttributes()->getNegotiableQuote();
$negotiableQuote->setStatus(NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN);
$negotiableQuote->setSnapshot(json_encode($quoteConverter->quoteToArray($quote)));
$quoteRepository->save($quote);

$quoteHistory->createLog($quoteId);

$quoteIdMask = Bootstrap::getObjectManager()->create(QuoteIdMask::class);
$quoteIdMask->setQuoteId($quoteId);
$quoteIdMask->setMaskedId('nq_customer_mask');
$quoteIdMask->setDataChanges(true);

/** @var QuoteIdMaskResource $maskedIdResource */
$maskedIdResource = Bootstrap::getObjectManager()->create(QuoteIdMaskFactory::class)->create();
$maskedIdResource->save($quoteIdMask);
