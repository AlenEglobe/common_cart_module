<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Company\Model\Company;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$objectManager = Bootstrap::getObjectManager();
/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);
Resolver::getInstance()->requireDataFixture('Magento/Sales/_files/order_with_customer.php');
/** @var OrderInterface $order */
$order = Bootstrap::getObjectManager()->create(OrderInterface::class)->load('100000001', 'increment_id');
Resolver::getInstance()->requireDataFixture('Magento/CompanyCredit/_files/company_with_credit_limit.php');
/** @var Company $company */
$company = $companyFactory->create()->load('company quote', 'company_name');

$order->getPayment()
    ->setMethod(\Magento\CompanyCredit\Model\CompanyCreditPaymentConfigProvider::METHOD_NAME)
    ->setAdditionalInformation('company_id', $company->getId())
    ->save();

$order->setState(\Magento\Sales\Model\Order::STATE_NEW)
    ->setStatus($order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_NEW))
    ->save();
