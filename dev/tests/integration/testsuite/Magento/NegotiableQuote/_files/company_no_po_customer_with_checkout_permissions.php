<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()
    ->requireDataFixture('Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php');

/** @var $repository \Magento\Customer\Api\CustomerRepositoryInterface */
$objectManager = Bootstrap::getObjectManager();

/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);
/** @var CompanyInterface $company */
$company = $companyFactory->create()->load('email@companyquote.com', 'company_email');
$company->getExtensionAttributes()->setIsPurchaseOrderEnabled(false);

$companyRepository = Bootstrap::getObjectManager()->create(CompanyRepositoryInterface::class);
$companyRepository->save($company);
