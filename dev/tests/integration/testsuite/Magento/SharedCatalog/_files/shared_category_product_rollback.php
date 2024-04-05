<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

$registry = $objectManager->get(\Magento\Framework\Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

$sharedCatalogCollection = $objectManager->create(
    \Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection::class
);
/** @var \Magento\SharedCatalog\Model\SharedCatalog $sharedCatalog */
$sharedCatalog = $sharedCatalogCollection->getLastItem();
$productManagement = $objectManager->get(\Magento\SharedCatalog\Api\ProductManagementInterface::class);
$categoryManagement = $objectManager->get(\Magento\SharedCatalog\Api\CategoryManagementInterface::class);

$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
try {
    $product = $productRepository->get('Sku is \'sku\'', false, null, true);
    $productManagement->unassignProducts($sharedCatalog->getId(), [$product]);
    $productRepository->delete($product);
} catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
    //Nothing to delete
}

$categoryRepository = $objectManager->get(\Magento\Catalog\Api\CategoryRepositoryInterface::class);
try {
    $category = $categoryRepository->get(10);
    $categoryManagement->unassignCategories($sharedCatalog->getId(), [$category]);
    $categoryRepository->delete($category);
} catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
    //Nothing to delete
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/shared_catalog_rollback.php');
