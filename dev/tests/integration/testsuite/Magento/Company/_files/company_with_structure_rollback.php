<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Model\StructureRepository;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
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

try {
    // Delete the customers.
    $levelOneCustomer = $customerRepository->get('veronica.costello@example.com');
    $customerRepository->delete($levelOneCustomer);

    $levelTwoCustomer = $customerRepository->get('alex.smith@example.com');
    $customerRepository->delete($levelTwoCustomer);

    // Delete the company.
    /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
    $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
    $searchCriteriaBuilder->addFilter('company_name', 'Magento');
    $searchCriteria = $searchCriteriaBuilder->create();
    $results = $companyRepository->getList($searchCriteria)->getItems();
    foreach ($results as $company) {
        /** @var \Magento\CompanyCredit\Model\CreditLimitManagement $creditLimitManagement */
        $creditLimitManagement = $objectManager
            ->get(\Magento\CompanyCredit\Model\CreditLimitManagement::class);
        try {
            $creditLimit = $creditLimitManagement->getCreditByCompanyId($company->getId());
            /** @var SearchCriteriaBuilder $historySearchCriteriaBuilder */
            $historySearchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
            $historySearchCriteriaBuilder->addFilter('company_credit_id', $creditLimit->getId());
            $historySearchCriteria = $historySearchCriteriaBuilder->create();
            /** @var \Magento\CompanyCredit\Model\HistoryRepositoryInterface $historyRepository */
            $historyRepository = $objectManager->get(\Magento\CompanyCredit\Model\HistoryRepositoryInterface::class);
            $histories = $historyRepository->getList($historySearchCriteria)->getItems();
            foreach ($histories as $history) {
                $historyRepository->delete($history);
            }
            /** @var \Magento\CompanyCredit\Api\CreditLimitRepositoryInterface $creditLimitRepository */
            $creditLimitRepository = $objectManager->get(
                \Magento\CompanyCredit\Api\CreditLimitRepositoryInterface::class
            );
            $creditLimitRepository->delete($creditLimit);
        } catch (NoSuchEntityException $exception) {
            // No op when no credit limit
        }
        /** @var SearchCriteriaBuilder $companyStructureSearchCriteriaBuilder */
        $companyStructureSearchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
        $companyStructureSearchCriteriaBuilder->addFilter('parent_id', $company->getId());
        $companyStructureSearchCriteria = $companyStructureSearchCriteriaBuilder->create();
        /** @var StructureRepository $structureRepository */
        $structureRepository = $objectManager->get(StructureRepository::class);
        $companyStructures = $structureRepository->getList($companyStructureSearchCriteria)->getItems();
        foreach ($companyStructures as $companyStructure) {
            $structureRepository->delete($companyStructure);
        }
        $companyRepository->delete($company);
    }

    // Delete the admin customer.
    $adminCustomer = $customerRepository->get('john.doe@example.com');
    $customerRepository->delete($adminCustomer);
} catch (NoSuchEntityException $e) {
    // Db isolation is enabled
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
