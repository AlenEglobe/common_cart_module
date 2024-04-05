<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/shared_catalog.php');

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

$category = $objectManager->create(\Magento\Catalog\Model\Category::class);
$category->isObjectNew(true);
$category->setId(10)
    ->setCreatedAt('2014-06-23 09:50:07')
    ->setName('Category')
    ->setParentId(2)
    ->setPath('1/2/310')
    ->setLevel(2)
    ->setAvailableSortBy('name')
    ->setDefaultSortBy('name')
    ->setIsActive(true)
    ->setPosition(1)
    ->setAvailableSortBy(['position'])
    ->save();

/** @var $product \Magento\Catalog\Model\Product */
$product = $objectManager->create(\Magento\Catalog\Model\Product::class);
$product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
    ->setAttributeSetId(4)
    ->setStoreId(1)
    ->setWebsiteIds([1])
    ->setName('Name is \'name\'')
    ->setSku('Sku is \'sku\'')
    ->setPrice(10)
    ->setWeight(18)
    ->setStockData(['use_config_manage_stock' => 0])
    ->setCategoryIds([10])
    ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
    ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
    ->save();

$sharedCatalogCollection = $objectManager->create(
    \Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection::class
);
/** @var \Magento\SharedCatalog\Model\SharedCatalog $sharedCatalog */
$sharedCatalog = $sharedCatalogCollection->getLastItem();
$categoryManagement = $objectManager->get(\Magento\SharedCatalog\Api\CategoryManagementInterface::class);
$categoryManagement->assignCategories($sharedCatalog->getId(), [$category]);
$productManagement = $objectManager->get(\Magento\SharedCatalog\Api\ProductManagementInterface::class);
$productManagement->assignProducts($sharedCatalog->getId(), [$product]);
