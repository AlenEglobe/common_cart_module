<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/public_shared_catalog_products.php');

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
$sharedCatalogManagement = $objectManager->get(\Magento\SharedCatalog\Api\SharedCatalogManagementInterface::class);
$sharedCatalog = $sharedCatalogManagement->getPublicCatalog();
$productRepository = $objectManager->create(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$product1 = $productRepository->get('simple_product_1');
$product2 = $productRepository->get('simple_product_2');
$product3 = $productRepository->get('simple_product_3');

$tierPrices = [];
$tierPrices[$product1->getId()] = [
    [
        'qty' => 1,
        'website_id' => 0,
        'value' => 9,
    ],
    [
        'qty' => 5,
        'website_id' => 0,
        'value' => 8,
    ],
];
$tierPrices[$product2->getId()] = [
    [
        'qty' => 1,
        'website_id' => 0,
        'value' => 10,
    ],
];
$tierPrices[$product3->getId()] = [
    [
        'qty' => 0,
        'website_id' => 1,
        'value' => 11,
    ],
];
$priceManagement = $objectManager->get(\Magento\SharedCatalog\Api\PriceManagementInterface::class);
$priceManagement->saveProductTierPrices($sharedCatalog, $tierPrices);
