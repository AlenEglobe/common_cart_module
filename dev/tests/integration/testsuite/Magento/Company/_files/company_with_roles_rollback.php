<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\CompanyManagementInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Registry;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var $configWriter WriterInterface */
$configWriter = $objectManager->get(WriterInterface::class);

$path = 'btob/website_configuration/company_active';
$configWriter->save($path, 0, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);

$cacheTypeList = $objectManager->get(TypeListInterface::class);
$cacheTypeList->cleanType('config');

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/**
 * Load the customer
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

/**
 * Delete the company
 */
/** @var CompanyRepositoryInterface $companyRepository */
$companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
$companyRepository->delete($company);

/**
 * Delete the customer
 */
$customerRepository->delete($customer);

/**
 * Company roles data are removed by cascade (company id reference)
 */

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
