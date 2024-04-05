<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\SharedCatalog\Api\CompanyManagementInterface as SharedCatalogCompanyManagement;
use Magento\SharedCatalog\Api\ProductManagementInterface as SharedCatalogProductManagement;
use Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection as SharedCatalogCollection;
use Magento\SharedCatalog\Model\SharedCatalog;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$objectManager = Bootstrap::getObjectManager();

Resolver::getInstance()->requireDataFixture(
    'Magento/NegotiableQuote/_files/product_simple.php'
);

Resolver::getInstance()->requireDataFixture(
    'Magento/NegotiableQuote/_files/company_with_customer_for_quote.php'
);

Resolver::getInstance()->requireDataFixture(
    'Magento/SharedCatalog/_files/shared_catalog.php'
);

/** @var SharedCatalogCollection $sharedCatalogCollection */
$sharedCatalogCollection = $objectManager->create(SharedCatalogCollection::class);

/** @var SharedCatalog $sharedCatalog */
$sharedCatalog = $sharedCatalogCollection->getLastItem();

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->create(ProductRepositoryInterface::class);

$simpleProduct = $productRepository->get('simple');

// assign product to shared catalog
/** @var SharedCatalogProductManagement $sharedCatalogProductManagement */
$sharedCatalogProductManagement = $objectManager->create(SharedCatalogProductManagement::class);
$sharedCatalogProductManagement->assignProducts($sharedCatalog->getId(), [$simpleProduct]);

// assign company to shared catalog
/** @var SharedCatalogCompanyManagement $sharedCatalogCompanyManagement */
$sharedCatalogCompanyManagement = $objectManager->create(
    SharedCatalogCompanyManagement::class
);

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->create(
    CustomerRepositoryInterface::class
);

$companyId = $customerRepository
    ->get('email@companyquote.com')
    ->getExtensionAttributes()
    ->getCompanyAttributes()
    ->getCompanyId();

/** @var CompanyRepositoryInterface $companyRepository */
$companyRepository = $objectManager->create(
    CompanyRepositoryInterface::class
);

$company = $companyRepository->get($companyId);

$sharedCatalogCompanyManagement->assignCompanies($sharedCatalog->getId(), [$company]);

// now create the negotiable quote without a negotiated price from admin
Resolver::getInstance()->requireDataFixture(
    'Magento/NegotiableQuote/_files/negotiable_quote_without_negotiated_price.php'
);
