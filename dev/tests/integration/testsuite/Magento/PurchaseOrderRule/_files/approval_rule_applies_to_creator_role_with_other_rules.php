<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Create a rule which applies to the default user role with another rule that applies to the approver role. This should
 * result in only one rule triggering.
 */

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Model\Role;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\PurchaseOrderRule\Api\Data\RuleInterface;
use Magento\PurchaseOrderRule\Api\RuleRepositoryInterface;
use Magento\PurchaseOrderRule\Model\RuleFactory;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/PurchaseOrder/_files/purchase_orders.php');

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();
/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);
/** @var CompanyInterface $company */
$company = $companyFactory->create()->load('Magento', 'company_name');

/** @var RuleRepositoryInterface $ruleRepository */
$ruleRepository = $objectManager->get(RuleRepositoryInterface::class);

/** @var RuleFactory $ruleFactory */
$ruleFactory = $objectManager->get(RuleFactory::class);

/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);

/** @var RoleRepositoryInterface $roleRepository */
$roleRepository = $objectManager->get(RoleRepositoryInterface::class);

/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);

$roles = $roleRepository->getList(
    $searchCriteriaBuilder
        ->addFilter('company_id', $company->getId())
        ->addFilter('role_name', 'Default User')
        ->create()
)->getItems();

/** @var Role $defaultUserRole */
$defaultUserRole = reset($roles);

/** @var Role $role */
$approverRole = $objectManager->create(RoleInterface::class);
$approverRole->setCompanyId($company->getId());
$approverRole->setRoleName('Approver Role');
/** @var RoleRepositoryInterface $roleRepository */
$roleRepository = $objectManager->get(RoleRepositoryInterface::class);
$roleRepository->save($approverRole);

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
         "value" => "5",
         "is_value_processed" => false
      ]
   ]
];

// Create a rule for the company
$rule = $ruleFactory->create();
$dataObjectHelper->populateWithArray(
    $rule,
    [
        'name' => 'Integration Test Rule Name',
        'description' => 'Integration Test Rule Description',
        'is_active' => 1,
        'applies_to_all' => 0,
        'company_id' => $company->getId(),
        'conditions_serialized' => json_encode($conditions)
    ],
    RuleInterface::class
);
// Have to set independently as the populateDataWithArray attempts to use reflection on array
$rule->setApproverRoleIds([$approverRole->getId()]);
$rule->setAppliesToRoleIds([$defaultUserRole->getId()]);
$ruleRepository->save($rule);

// Create a rule for the company
$rule = $ruleFactory->create();
$dataObjectHelper->populateWithArray(
    $rule,
    [
        'name' => 'Integration Test Rule Name - Other Role',
        'description' => 'Integration Test Rule Description',
        'is_active' => 1,
        'applies_to_all' => 0,
        'company_id' => $company->getId(),
        'conditions_serialized' => json_encode($conditions)
    ],
    RuleInterface::class
);
// Have to set independently as the populateDataWithArray attempts to use reflection on array
$rule->setApproverRoleIds([$defaultUserRole->getId()]);
$rule->setAppliesToRoleIds([$approverRole->getId()]);
$ruleRepository->save($rule);
