<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\CompanyCredit\Api\CreditLimitRepositoryInterface;
use Magento\CompanyCredit\Model\CreditLimitManagement;
use Magento\CompanyCredit\Model\HistoryRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\User\Model\User;

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
    // Delete admins.
    $user1 = $objectManager->create(User::class);
    $user1 = $user1->loadByUserName('salesRep1');
    $user1->delete();

    $user2 = $objectManager->create(User::class);
    $user2 = $user2->loadByUserName('salesRep2');
    $user2->delete();

    // Delete the companies.
    /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
    $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
    $searchCriteriaBuilder->addFilter('company_name', ['Roga i koputa', 'Galera'], 'in');
    $searchCriteria = $searchCriteriaBuilder->create();
    $results = $companyRepository->getList($searchCriteria)->getItems();
    foreach ($results as $company) {
        /** @var CreditLimitManagement $creditLimitManagement */
        $creditLimitManagement = $objectManager->get(CreditLimitManagement::class);
        try {
            $creditLimit = $creditLimitManagement->getCreditByCompanyId($company->getId());
            /** @var SearchCriteriaBuilder $historySearchCriteriaBuilder */
            $historySearchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
            $historySearchCriteriaBuilder->addFilter('company_credit_id', $creditLimit->getId());
            $historySearchCriteria = $historySearchCriteriaBuilder->create();
            /** @var HistoryRepositoryInterface $historyRepository */
            $historyRepository = $objectManager->get(HistoryRepositoryInterface::class);
            $histories = $historyRepository->getList($historySearchCriteria)->getItems();
            foreach ($histories as $history) {
                $historyRepository->delete($history);
            }
            /** @var CreditLimitRepositoryInterface $creditLimitRepository */
            $creditLimitRepository = $objectManager->get(
                CreditLimitRepositoryInterface::class
            );
            $creditLimitRepository->delete($creditLimit);
        } catch (NoSuchEntityException $exception) {
            // No op when no credit limit
        }

        $companyRepository->delete($company);
    }

    // Delete the admin customers.
    $adminCustomer1 = $customerRepository->get('john.doe1@example.com');
    $adminCustomer2 = $customerRepository->get('sam.smith@example.com');
    $customerRepository->delete($adminCustomer1);
    $customerRepository->delete($adminCustomer2);
} catch (NoSuchEntityException $e) {
    // Db isolation is enabled
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
