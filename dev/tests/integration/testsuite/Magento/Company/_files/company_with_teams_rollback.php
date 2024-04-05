<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Company\Api\CompanyManagementInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Registry;
use Magento\Framework\Exception\NoSuchEntityException;

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/**
 * Load the company administrator
 */
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
/** @var CustomerInterface $customer */
$customer = $customerRepository->get('customer@example.com');

/**
 * Load the company
 */
/** @var CompanyManagementInterface $companyManagement */
$companyManagement = $objectManager->get(CompanyManagementInterface::class);
/** @var CompanyInterface $company */
$company = $companyManagement->getByCustomerId($customer->getId());

try {
    // Delete the customers
    $customerOne = $customerRepository->get('veronica.tailor@example.com');
    $customerRepository->delete($customerOne);

    $customerTwo = $customerRepository->get('alex.tailor@example.com');
    $customerRepository->delete($customerTwo);

    /**
     * Delete the company
     */
    /** @var CompanyRepositoryInterface $companyRepository */
    $companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
    $companyRepository->delete($company);

    /**
     * Delete the company administrator
     */
    $customerRepository->delete($customer);

    /**
     * Company structure and Company team data are removed by cascade (using company id as reference)
     */
} catch (NoSuchEntityException $e) {
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
