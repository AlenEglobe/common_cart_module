<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

$productRepository = $objectManager->create(\Magento\Catalog\Api\ProductRepositoryInterface::class);
/** @var \Magento\Catalog\Api\Data\ProductInterface[] $products */
$products = [];
for ($i = 1; $i <= 3; $i++) {
    $product = $objectManager->create(\Magento\Catalog\Model\Product::class);
    $product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
        ->setAttributeSetId(4)
        ->setStoreId(1)
        ->setWebsiteIds([1])
        ->setName('Simple product ' . $i)
        ->setSku('simple_product_' . $i)
        ->setPrice(10 + $i)
        ->setWeight(1)
        ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_qty_decimal' => 0, 'is_in_stock' => 1])
        ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
        ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
    $products[] = $productRepository->save($product);
}

$sharedCatalogManagement = $objectManager->get(\Magento\SharedCatalog\Api\SharedCatalogManagementInterface::class);
$sharedCatalog = $sharedCatalogManagement->getPublicCatalog();
/** @var \Magento\SharedCatalog\Api\ProductManagementInterface $productManagement */
$productManagement = $objectManager->get(\Magento\SharedCatalog\Api\ProductManagementInterface::class);
$productManagement->assignProducts($sharedCatalog->getId(), $products);
