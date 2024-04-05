<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
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
/** @var \Magento\Company\Model\Company $company */
/** @var CompanyInterface $company */
$company = $companyFactory->create()->load('company quote', 'company_name');

$order->getPayment()
    ->setMethod(\Magento\CompanyCredit\Model\CompanyCreditPaymentConfigProvider::METHOD_NAME)
    ->setAdditionalInformation('company_id', $company->getId())
    ->save();

$order->setSubtotal(20)
    ->setGrandTotal(20)
    ->setBaseSubtotal(20)
    ->setBaseGrandTotal(20)
    ->setCustomerId(1)
    ->setCustomerIsGuest(false)
    ->setCustomerEmail(null)
    ->save();

$orderService = \Magento\TestFramework\ObjectManager::getInstance()->create(
    \Magento\Sales\Api\InvoiceManagementInterface::class
);
$invoice = $orderService->prepareInvoice($order);
$invoice->register();
$order = $invoice->getOrder();
$order->setIsInProcess(false);
$transactionSave = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->create(\Magento\Framework\DB\Transaction::class);
$transactionSave->addObject($invoice)->addObject($order)->save();
