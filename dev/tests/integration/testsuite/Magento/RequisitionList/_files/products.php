<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

$productRepository = $objectManager->create(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$stockRegistry = $objectManager->get(\Magento\CatalogInventory\Api\StockRegistryInterface::class);
$stockItemRepository = $objectManager->get(\Magento\CatalogInventory\Api\StockItemRepositoryInterface::class);
$stockRegistryStorage = $objectManager->get(\Magento\CatalogInventory\Model\StockRegistryStorage::class);

$productList = [
    'item 1' => [
        'qty' => 1,
        'is_qty_decimal' => false,
        'is_in_stock' => true,
        'backorders' => \Magento\CatalogInventory\Model\Stock::BACKORDERS_NO,
    ],
    'item 2' => [
        'qty' => 0,
        'is_qty_decimal' => false,
        'is_in_stock' => false,
        'backorders' => \Magento\CatalogInventory\Model\Stock::BACKORDERS_NO,
    ],
    'item 3' => [
        'qty' => 0,
        'is_qty_decimal' => false,
        'is_in_stock' => true,
        'backorders' => \Magento\CatalogInventory\Model\Stock::BACKORDERS_YES_NONOTIFY,
    ],
    'item 4' => [
        'qty' => 0,
        'is_qty_decimal' => false,
        'is_in_stock' => true,
        'backorders' => \Magento\CatalogInventory\Model\Stock::BACKORDERS_YES_NOTIFY,
    ],
    'item 5' => [
        'qty' => 4.5,
        'is_qty_decimal' => true,
        'is_in_stock' => true,
        'backorders' => \Magento\CatalogInventory\Model\Stock::BACKORDERS_NO,
    ],
];
$productIds = [];
foreach ($productList as $sku => $productData) {
    $product = $objectManager->create(\Magento\Catalog\Api\Data\ProductInterface::class);
    $product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
        ->setAttributeSetId(4)
        ->setName('Product ' . $sku)
        ->setSku($sku)
        ->setPrice(10)
        ->setWeight(1)
        ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
        ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
        ->setWebsiteIds([1]);
    $product = $productRepository->save($product);
    $productIds[] = $product->getId();

    $stockItem = $stockRegistry->getStockItem($product->getId());
    $stockItem->setProductId($product->getId());
    $stockItem->setUseConfigManageStock(true);
    $stockItem->setQty($productData['qty']);
    $stockItem->setIsQtyDecimal($productData['is_qty_decimal']);
    $stockItem->setIsInStock($productData['is_in_stock']);
    $stockItem->setUseConfigBackorders(false);
    $stockItem->setBackorders($productData['backorders']);
    $stockItemRepository->save($stockItem);
    $stockRegistryStorage->removeStockItem($product->getId());
    $stockRegistryStorage->removeStockStatus($product->getId());
}

$stockIndexerProcessor = $objectManager->create(\Magento\CatalogInventory\Model\Indexer\Stock\Processor::class);
$stockIndexerProcessor->reindexList($productIds, true);
