<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
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
         "value" => "10000000",
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
        'name' => 'Integration Test Rule Name',
        'description' => 'Integration Test Rule Description',
        'is_active' => 1,
        'company_id' => $company->getId(),
        'conditions_serialized' => json_encode($conditions)
    ],
    RuleInterface::class
);
// Have to set independently as the populateDataWithArray attempts to use reflection on array
$rule->setApproverRoleIds([$defaultUserRole->getId()]);
$ruleRepository->save($rule);
