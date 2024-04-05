<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Model\Company;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\PurchaseOrderRule\Api\Data\RuleInterface;
use Magento\PurchaseOrderRule\Api\RuleRepositoryInterface;
use Magento\PurchaseOrderRule\Model\RuleFactory;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var RuleRepositoryInterface $ruleRepository */
$ruleRepository = $objectManager->get(RuleRepositoryInterface::class);

/** @var RuleFactory $ruleFactory */
$ruleFactory = $objectManager->get(RuleFactory::class);

/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);

/** @var CompanyRepositoryInterface $companyRepository */
$companyRepository = $objectManager->get(CompanyRepositoryInterface::class);

$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
$results = $companyRepository->getList(
    $searchCriteriaBuilder->addFilter('company_name', 'Magento')->create()
)->getItems();
/* @var Company $company */
$company = reset($results);

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
        'applies_to_all' => 1,
        'company_id' => $company->getId(),
        'conditions_serialized' => json_encode($conditions)
    ],
    RuleInterface::class
);
// Have to set independently as the populateDataWithArray attempts to use reflection on array
$rule->setAdminApprovalRequired(true);
$ruleRepository->save($rule);
