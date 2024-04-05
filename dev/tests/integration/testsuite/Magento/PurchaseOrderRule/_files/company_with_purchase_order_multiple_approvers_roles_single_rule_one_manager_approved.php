<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Create a company, with purchase orders with a single applied rule which has a single role with multiple customers /
 * approvers within. One Approver approved order.
 */

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\Processor\ApprovalProcessorInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture(
    'Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_roles_single_rule.php'
);

$objectManager = Bootstrap::getObjectManager();

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
$levelOneCustomer = $customerRepository->get('veronica.costello@example.com');

/** @var PurchaseOrderRepositoryInterface $purchaseOrderRepository */
$purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);

/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);

$purchaseOrders = $purchaseOrderRepository->getList($searchCriteriaBuilder->create())->getItems();

$purchaseOrderToApprove = array_shift($purchaseOrders);

/** @var ApprovalProcessorInterface $purchaseOrderApprovalsProcessor */
$purchaseOrderApprovalsProcessor = $objectManager->create(ApprovalProcessorInterface::class, [
    'processors' => [
        [
            'processorClass' => Magento\PurchaseOrderRule\Model\Processor\RuleApproval::class,
            'priority' => 10,
        ]
    ]
]);

$purchaseOrderApprovalsProcessor->processApproval($purchaseOrderToApprove, (int)$levelOneCustomer->getId());
