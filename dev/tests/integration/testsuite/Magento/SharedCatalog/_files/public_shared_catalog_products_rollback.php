<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\SharedCatalog\Api\Data\ProductItemInterface;
use Magento\SharedCatalog\Api\ProductItemRepositoryInterface;

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

$registry = $objectManager->get(\Magento\Framework\Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

$skus = ['simple_product_1', 'simple_product_2', 'simple_product_3'];
$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
$searchCriteriaBuilder->addFilter(ProductItemInterface::SKU, $skus, 'in');
$searchCriteria = $searchCriteriaBuilder->create();
$sharedCatalogProductItemRepository = $objectManager->get(ProductItemRepositoryInterface::class);
$productItems = $sharedCatalogProductItemRepository->getList($searchCriteria)->getItems();
$sharedCatalogProductItemRepository->deleteItems($productItems);

$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
for ($i = 1; $i <= 3; $i++) {
    try {
        $product = $productRepository->get('simple_product_' . $i, false, null, true);
        $productRepository->delete($product);
    } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
        //Nothing to delete
    }
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
