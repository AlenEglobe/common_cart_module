<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\Quote\Api\CouponManagementInterface;
use Magento\TestFramework\Helper\Bootstrap;

/** @var CouponManagementInterface $couponManagement */
$couponManagement = Bootstrap::getObjectManager()->get(CouponManagementInterface::class);
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = Bootstrap::getObjectManager()->get(CustomerRepositoryInterface::class);
/** @var NegotiableQuoteRepositoryInterface $negotiableQuoteRepository */
$negotiableQuoteRepository = Bootstrap::getObjectManager()->get(NegotiableQuoteRepositoryInterface::class);

$customer = $customerRepository->get('email@companyquote.com');
$quotes = $negotiableQuoteRepository->getListByCustomerId($customer->getId());
$negotiableQuoteId = array_key_last($quotes);
$negotiableQuote = $negotiableQuoteRepository->getById($negotiableQuoteId);
$couponManagement->set($negotiableQuote->getQuoteId(), 'test_coupon');
