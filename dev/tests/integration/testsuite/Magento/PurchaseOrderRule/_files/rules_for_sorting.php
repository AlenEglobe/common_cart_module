<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Model\Company;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\PurchaseOrderRule\Api\Data\RuleInterface;
use Magento\PurchaseOrderRule\Api\RuleRepositoryInterface;
use Magento\PurchaseOrderRule\Model\RuleFactory;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company_with_structure.php');

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

/** @var RoleRepositoryInterface $roleRepository */
$roleRepository = $objectManager->get(RoleRepositoryInterface::class);

/** @var CustomerRepository $customerRepository */
$customerRepository = $objectManager->get(CustomerRepository::class);

/** @var CustomerInterfaceFactory $customerFactory */
$customerFactory = $objectManager->get(CustomerInterfaceFactory::class);

$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
$results = $companyRepository->getList(
    $searchCriteriaBuilder->addFilter('company_name', 'Magento')->create()
)->getItems();
/* @var Company $company */
$company = reset($results);

/** @var RoleInterface $approver */
$approver = $objectManager->create(RoleInterface::class);
$approver->setCompanyId($company->getId());
$approver->setRoleName('Approver');
/** @var RoleRepositoryInterface $roleRepository */
$roleRepository = $objectManager->get(RoleRepositoryInterface::class);
$roleRepository->save($approver);

// Build the conditions arrays to be encoded
$orderTotalRuleConditions = [
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

$shippingCostRuleConditions = [
    "type" => "Magento\\PurchaseOrderRule\\Model\\Rule\\Condition\\Combine",
    "attribute" => null,
    "operator" => null,
    "value" => "1",
    "is_value_processed" => null,
    "aggregator" => "all",
    "conditions" => [
        [
            "type" => "Magento\\PurchaseOrderRule\\Model\\Rule\\Condition\\Address",
            "attribute" => "shipping_incl_tax",
            "operator" => "<=",
            "value" => "10",
            "currency_code" => "USD",
            "is_value_processed" => false
        ]
    ]
];

$numberOfSkusConditions = [
    "type" => "Magento\\PurchaseOrderRule\\Model\\Rule\\Condition\\Combine",
    "attribute" => null,
    "operator" => null,
    "value" => "1",
    "is_value_processed" => null,
    "aggregator" => "all",
    "conditions" => [
        [
            "type" => "Magento\\PurchaseOrderRule\\Model\\Rule\\Condition\\NumberOfSkus",
            "attribute" => "number_of_skus",
            "operator" => "<",
            "value" => "5",
            "is_value_processed" => false
        ]
    ]
];

$conditions = [
    $orderTotalRuleConditions,
    $orderTotalRuleConditions,
    $orderTotalRuleConditions,
    $shippingCostRuleConditions,
    $shippingCostRuleConditions,
    $shippingCostRuleConditions,
    $numberOfSkusConditions,
    $numberOfSkusConditions,
    $numberOfSkusConditions,
    $numberOfSkusConditions
];

$isActive = [ 0, 0, 1, 1, 1, 1, 1, 1, 1, 1 ];

// User list to create
$companyUsersToCreate = [
    [
        'firstname' => 'BeforeTest',
        'lastname' => 'Smith',
        'email' => 'before.test.smith@example.com',
    ],
    [
        'firstname' => 'Test',
        'lastname' => 'Smith',
        'email' => 'test.smith@example.com',
    ],
    [
        'firstname' => 'Tests',
        'lastname' => 'Smith',
        'email' => 'tests.smith@example.com',
    ],
    [
        'firstname' => 'test',
        'lastname' => 'Smith',
        'email' => 'test.lower.case.smith@example.com',
    ],
    [
        'firstname' => '1Alex',
        'lastname' => 'Smith',
        'email' => '1alex.smith@example.com',
    ],
    [
        'firstname' => '2Alex',
        'lastname' => 'Smith',
        'email' => '2alex.smith@example.com',
    ],
    [
        'firstname' => '10Alex',
        'lastname' => 'Smith',
        'email' => '10alex.smith@example.com',
    ],
    [
        'firstname' => '1',
        'lastname' => ' ',
        'email' => '1@example.com',
    ],
    [
        'firstname' => '2',
        'lastname' => ' ',
        'email' => '2@example.com',
    ],
    [
        'firstname' => '10',
        'lastname' => ' ',
        'email' => '10@example.com',
    ],
];

// Create users from list above
foreach ($companyUsersToCreate as $companyUserToCreate) {
    $customer = $customerFactory->create();
    $dataObjectHelper->populateWithArray(
        $customer,
        [
            'firstname' => $companyUserToCreate['firstname'],
            'lastname' => $companyUserToCreate['lastname'],
            'email' => $companyUserToCreate['email'],
            'website_id' => 1,
            'extension_attributes' => [
                'company_attributes' => [
                    'company_id' => $company->getId(),
                    'status' => 1,
                    'job_title' => 'Sales Rep'
                ]
            ]
        ],
        CustomerInterface::class
    );
    $customerRepository->save($customer, 'password');
}

// Create rules from users created above
foreach ($companyUsersToCreate as $key => $companyUserToCreate) {
    $companyUser = $customerRepository->get($companyUserToCreate['email']);
    $rule = $ruleFactory->create();
    $dataObjectHelper->populateWithArray(
        $rule,
        [
            'name' => $companyUserToCreate['firstname'] . ' ' . $companyUserToCreate['lastname'] .
                ' Integration Test Rule Name ' . $key,
            'description' => 'Integration Test Rule Description ' . $key,
            'is_active' => $isActive[$key],
            'applies_to_all' => 1,
            'company_id' => $company->getId(),
            'created_by' => $companyUser->getId(),
            'conditions_serialized' => json_encode($conditions[$key])
        ],
        RuleInterface::class
    );

    // Have to set independently as the populateDataWithArray attempts to use reflection on array
    $rule->setApproverRoleIds([$approver->getId()]);
    $ruleRepository->save($rule);
}
