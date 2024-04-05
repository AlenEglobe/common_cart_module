<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\Data\RoleInterfaceFactory;
use Magento\Company\Model\ResourceModel\Role as RoleResource;
use Magento\Framework\Api\DataObjectHelper;
use Magento\PurchaseOrderRule\Api\Data\RuleInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use Magento\PurchaseOrderRule\Api\RuleRepositoryInterface;
use Magento\PurchaseOrderRule\Model\RuleFactory;

Resolver::getInstance()->requireDataFixture('Magento/PurchaseOrderRule/_files/approval_rule.php');

$objectManager = Bootstrap::getObjectManager();
/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);
/** @var CompanyInterface $company */
$company = $companyFactory->create()->load('Magento', 'company_name');
/** @var RuleRepositoryInterface $ruleRepository */
$ruleRepository = $objectManager->get(RuleRepositoryInterface::class);
/** @var RuleFactory $ruleFactory */
$ruleFactory = $objectManager->get(RuleFactory::class);
/** @var RoleInterfaceFactory $ruleFactory */
$roleFactory = $objectManager->get(RoleInterfaceFactory::class);
/** @var RoleResource $roleResource */
$roleResource = $objectManager->get(RoleResource::class);
/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);

// Build the conditions array to be encoded
$conditions = [
    "type" => "Magento\\PurchaseOrderRule\\Model\\Rule\\Condition\\Combine",
    "attribute" => null,
    "operator" => null,
    "value" => "1",
    "is_value_processed" => null,
    "aggregator" => "all",
    "conditions" => [
        [
            "type" => "Magento\\PurchaseOrderRule\\Model\\Rule\\Condition\\Address",
            "attribute" => "grand_total",
            "operator" => ">",
            "value" => "8",
            "currency_code" => "USD",
            "is_value_processed" => false
        ]
    ]
];

// Create a rule for the company
$rule = $ruleFactory->create();
$dataObjectHelper->populateWithArray(
    $rule,
    [
        'name' => 'Secondary Approval Rule',
        'description' => 'Another Rule',
        'is_active' => 1,
        'applies_to_all' => 1,
        'company_id' => $company->getId(),
        'conditions_serialized' => json_encode($conditions)
    ],
    RuleInterface::class
);
/** @var RoleInterface $approverRole */
$approverRole = $roleFactory->create();
$roleResource->load($approverRole, 'Approver Role', 'role_name');
// Have to set independently as the populateDataWithArray attempts to use reflection on array
$rule->setApproverRoleIds([$approverRole->getId()]);
$ruleRepository->save($rule);
