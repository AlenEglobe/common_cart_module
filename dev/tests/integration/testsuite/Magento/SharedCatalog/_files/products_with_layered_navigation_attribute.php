<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Api\Data\ProductAttributeInterfaceFactory;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Model\Config;
use Magento\Eav\Setup\EavSetup;
use Magento\Indexer\Model\Indexer;
use Magento\Indexer\Model\Indexer\Collection;
use Magento\Msrp\Model\Product\Attribute\Source\Type as SourceType;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Helper\CacheCleaner;

$objectManager = Bootstrap::getObjectManager();

/** @var Config $eavConfig */
$eavConfig = $objectManager->get(Config::class);

/** @var ProductAttributeRepositoryInterface $attributeRepository */
$attributeRepository = $objectManager->get(ProductAttributeRepositoryInterface::class);
/** @var ProductAttributeInterfaceFactory $attributeFactory */
$attributeFactory = $objectManager->get(ProductAttributeInterfaceFactory::class);

/** @var $installer EavSetup */
$installer = $objectManager->get(EavSetup::class);
$attributeSetId = $installer->getAttributeSetId(Product::ENTITY, 'Default');
$groupId = $installer->getDefaultAttributeGroupId(Product::ENTITY, $attributeSetId);

/** @var WebsiteRepositoryInterface $websiteRepository */
$websiteRepository = $objectManager->get(WebsiteRepositoryInterface::class);
$baseWebsite = $websiteRepository->get('base');

$attributeModel = $attributeFactory->create();
$attributeModel->setData(
    [
        'attribute_code' => 'test_filtered_attr',
        'entity_type_id' => $installer->getEntityTypeId(Product::ENTITY),
        'is_global' => 1,
        'is_user_defined' => 1,
        'frontend_input' => 'select',
        'is_unique' => 0,
        'is_required' => 0,
        'is_searchable' => 1,
        'is_visible_in_advanced_search' => 1,
        'is_comparable' => 1,
        'is_filterable' => 1,
        'is_filterable_in_search' => 1,
        'is_used_for_promo_rules' => 0,
        'is_html_allowed_on_front' => 1,
        'is_visible_on_front' => 1,
        'used_in_product_listing' => 1,
        'used_for_sort_by' => 1,
        'frontend_label' => ['Test 2'],
        'backend_type' => 'int',
        'option' => [
            'value' => ['option_0' => ['Option 1'], 'option_1' => ['Option 2']],
            'order' => ['option_0' => 1, 'option_1' => 2],
        ],
        'default' => ['option_0']
    ]
);
$attribute = $attributeRepository->save($attributeModel);
$installer->addAttributeToGroup(
    Product::ENTITY,
    $attributeSetId,
    $groupId,
    $attribute->getId()
);

CacheCleaner::cleanAll();
$eavConfig->clear();

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
/** @var ProductInterfaceFactory $productInterfaceFactory */
$productInterfaceFactory = $objectManager->get(ProductInterfaceFactory::class);

$products = [];
$productsIds = [];
for ($i= 1; $i<=14; $i++) {
    /** @var Product $product */
    $product = $productInterfaceFactory->create();
    $product->setTypeId(Type::TYPE_SIMPLE)
        ->setAttributeSetId($product->getDefaultAttributeSetId())
        ->setName('Simple Product' . $i)
        ->setSku('simple' . $i)
        ->setTaxClassId('none')
        ->setDescription('description')
        ->setShortDescription('short description')
        ->setOptionsContainer('container1')
        ->setMsrpDisplayActualPriceType(SourceType::TYPE_IN_CART)
        ->setPrice(10)
        ->setWeight(1)
        ->setMetaTitle('meta title')
        ->setMetaKeyword('meta keyword')
        ->setMetaDescription('meta description')
        ->setVisibility(Visibility::VISIBILITY_BOTH)
        ->setStatus(Status::STATUS_ENABLED)
        ->setWebsiteIds([$baseWebsite->getId()])
        ->setCategoryIds([])
        ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_qty_decimal' => 0, 'is_in_stock' => 1])
        ->setSpecialPrice('5.99');
    $product->setData(
        'test_filtered_attr',
        $attribute->getSource()->getOptionId('Option 1')
    );
    $products[$i] = $productRepository->save($product);
    $productsIds[$products[$i]->getId()] = $i;
}

/** @var CategoryInterfaceFactory $categoryInterfaceFactory */
$categoryInterfaceFactory = $objectManager->get(CategoryInterfaceFactory::class);

$category = $categoryInterfaceFactory->create();
$category->isObjectNew(true);
$category->setId(333)
    ->setCreatedAt('2014-06-23 09:50:07')
    ->setName('Category 1')
    ->setParentId(2)
    ->setPath('1/2/333')
    ->setLevel(2)
    ->setAvailableSortBy(['position', 'name'])
    ->setDefaultSortBy('name')
    ->setIsActive(true)
    ->setPosition(1)
    ->setPostedProducts(
        $productsIds
    );
$category->save();

$sharedCatalogManagement = $objectManager->get(\Magento\SharedCatalog\Api\SharedCatalogManagementInterface::class);
$sharedCatalog = $sharedCatalogManagement->getPublicCatalog();
/** @var \Magento\SharedCatalog\Api\ProductManagementInterface $productManagement */
$productManagement = $objectManager->get(\Magento\SharedCatalog\Api\ProductManagementInterface::class);
$productManagement->assignProducts($sharedCatalog->getId(), $products);

/** @var Collection $indexerCollection */
$indexerCollection = $objectManager->get(Collection::class);
$indexerCollection->load();
/** @var Indexer $indexer */
foreach ($indexerCollection->getItems() as $indexer) {
    $indexer->reindexAll();
}
