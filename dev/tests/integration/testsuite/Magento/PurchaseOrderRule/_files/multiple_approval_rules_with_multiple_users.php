<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
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
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;

Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company_with_structure.php');

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();
/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);
/** @var CustomerInterfaceFactory $customerFactory */
$customerFactory = $objectManager->get(CustomerInterfaceFactory::class);
/** @var CompanyInterface $company */
$company = $companyFactory->create()->load('company@example.com', 'company_email');
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
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);

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
         "currency_code" => "USD",
         "is_value_processed" => false
      ]
   ]
];

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
            'name' => 'Integration Test Rule Name ' . $key,
            'description' => 'Integration Test Rule Description ' . $key,
            'is_active' => 1,
            'applies_to_all' => 1,
            'company_id' => $company->getId(),
            'created_by' => $companyUser->getId(),
            'conditions_serialized' => json_encode($conditions)
        ],
        RuleInterface::class
    );

    // Have to set independently as the populateDataWithArray attempts to use reflection on array
    $rule->setApproverRoleIds([$approverRole->getId()]);
    $ruleRepository->save($rule);
}
