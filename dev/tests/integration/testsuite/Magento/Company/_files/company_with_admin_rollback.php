<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
/** @var CompanyRepositoryInterface $companyRepository */
$companyRepository = $objectManager->get(CompanyRepositoryInterface::class);

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

// Delete company by email
/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
/** @var SearchCriteriaInterface $searchCriteria */
$searchCriteria = $searchCriteriaBuilder
    ->addFilter('company_email', 'support@example.com')
    ->create();
$companyList = $companyRepository->getList($searchCriteria);
foreach ($companyList->getItems() as $item) {
    $companyRepository->deleteById($item->getId());
}

// Delete customer
try {
    $customer = $customerRepository->get('company-admin@example.com', 1);
    $customerRepository->delete($customer);
} catch (NoSuchEntityException $e) {
    // isolation on
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
