<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Company\Api\Data\CompanyOrderInterfaceFactory;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company_with_structure.php');
Resolver::getInstance()->requireDataFixture('Magento/Sales/_files/order_list.php');

$objectManager = Bootstrap::getObjectManager();
/** @var CustomerRegistry $customerRegistry */
$customerRegistry = $objectManager->create(CustomerRegistry::class);
$adminCustomer = $customerRegistry->retrieveByEmail('john.doe@example.com');
$levelOneCustomer = $customerRegistry->retrieveByEmail('veronica.costello@example.com');
$levelTwoCustomer = $customerRegistry->retrieveByEmail('alex.smith@example.com');
/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);
/** @var CompanyInterface $company */
$company = $companyFactory->create()->load('Magento', 'company_name');

$orderIncrementIdToCompanyUserMap = [
    '100000001' => $adminCustomer,
    '100000002' => $levelOneCustomer,
    '100000003' => $levelTwoCustomer,
];

$orderRepository = $objectManager->get(OrderRepositoryInterface::class);
$companyOrderFactory = $objectManager->get(CompanyOrderInterfaceFactory::class);
$orderFactory = $objectManager->get(OrderInterfaceFactory::class);

// assign orders to company user based on $orderIncrementIdToCompanyUserMap
foreach ($orderIncrementIdToCompanyUserMap as $orderIncrementId => $companyUserToAssignOrderTo) {
    $order = $orderFactory->create()
        ->loadByIncrementId($orderIncrementId)
        ->setCustomerId($companyUserToAssignOrderTo->getId())
        ->setCustomerEmail($companyUserToAssignOrderTo->getEmail())
        ->setCustomerIsGuest(false);
    $orderRepository->save($order);

    /** @var \Magento\Company\Api\Data\CompanyOrderInterface $companyOrder */
    $companyOrder = $companyOrderFactory->create()
        ->setCompanyId($company->getId())
        ->setCompanyName($company->getCompanyName())
        ->setOrderId($order->getId())
        ->save();
}
