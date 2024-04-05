<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogPermissions\Model\Permission;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\SharedCatalog\Api\CategoryManagementInterface;
use Magento\SharedCatalog\Api\ProductManagementInterface;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\SharedCatalog\Api\SharedCatalogRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/ConfigurableProduct/_files/product_configurable.php');

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();
/** @var CategoryFactory $categoryFactory */
$categoryFactory = $objectManager->create(CategoryFactory::class);
/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->create(ProductRepositoryInterface::class);
/** @var SharedCatalogRepositoryInterface $sharedCatalogRepository */
$sharedCatalogRepository = $objectManager->create(SharedCatalogRepositoryInterface::class);

for ($i = 0; $i < 3; $i++) {
    //Create category
    $category = $categoryFactory->create();
    $category->isObjectNew(true);
    $category->setId($i + 3)
        ->setCreatedAt('2014-06-23 09:50:07')
        ->setName('Catalog for company ' . $i)
        ->setParentId(2)
        ->setPath('1/2/' . ($i + 3))
        ->setLevel(2)
        ->setAvailableSortBy('name')
        ->setDefaultSortBy('name')
        ->setIsActive(true)
        ->setPosition(1)
        ->setAvailableSortBy(['position'])
        ->setIsAnchor(1)
        ->save();

    //Get shared catalog
    $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
    $searchCriteria = $searchCriteriaBuilder->addFilter('name', 'Company ' . $i . ' shared catalog')->create();
    $items = $sharedCatalogRepository->getList($searchCriteria)->getItems();
    /** @var SharedCatalogInterface $sharedCatalog */
    $sharedCatalog = reset($items);

    //Assign category to the matched shared catalog
    $categoryManagement = $objectManager->create(CategoryManagementInterface::class);
    $categoryManagement->assignCategories($sharedCatalog->getId(), [$category]);

    /** @var Product $configurableProduct */
    $configurableProduct = $productRepository->get('configurable');
    $configurableProduct->setCategoryIds([$i + 3]);
    $productRepository->save($configurableProduct);
    /** @var Product $configurableProductVariant1 */
    $configurableProductVariant1 = $productRepository->get('simple_10');
    $configurableProductVariant1->setVisibility(Visibility::VISIBILITY_BOTH);
    $configurableProductVariant1->setCategoryIds([$i + 3]);
    $productRepository->save($configurableProductVariant1);
    /** @var Product $configurableProductVariant2 */
    $configurableProductVariant2 = $productRepository->get('simple_20');
    $configurableProductVariant2->setVisibility(Visibility::VISIBILITY_BOTH);
    $productRepository->save($configurableProductVariant2);

    // Assign configurable product to the matched shared catalog
    $productManagement = $objectManager->create(ProductManagementInterface::class);
    $productManagement->assignProducts(
        $sharedCatalog->getId(),
        [
            $configurableProduct,
            $configurableProductVariant1,
            $configurableProductVariant2
        ]
    );

    //Create products and assign them to the category
    for ($j = 0; $j < 3; $j++) {
        $productFactory = $objectManager->create(ProductFactory::class);
        /** @var Product $product */
        $product = $productFactory->create();
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setId($i + $j * 7 + 100)
            ->setAttributeSetId(4)
            ->setStoreId(1)
            ->setWebsiteIds([1])
            ->setName('Product ' . $i . $j)
            ->setSku('product_' . $i . $j)
            ->setPrice($i + $j * 7 + 100)
            ->setSpecialPrice($i + $j * 7 + 100 - 10)
            ->setQty(100)
            ->setWeight(18)
            ->setStockData(['use_config_manage_stock' => 0])
            ->setCategoryIds([$i + 3])
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED);

        $productRepository->save($product);

        //Assign product to the matched shared catalog
        $productManagement = $objectManager->create(ProductManagementInterface::class);
        $productManagement->assignProducts($sharedCatalog->getId(), [$product]);
    }

    /**
     * Set allow permissions for the current group
     *
     * Category 0: Allow all
     * Category 1: Allow browse category and display product prices, but does not allow add to cart
     * Category 2: Allow browse category, but does not allow display product prices and add to cart
     * /

    /** @var Permission $permission */
    $permission = $objectManager->create(Permission::class);
    $permission->setWebsiteId(1)
        ->setCategoryId($i + 3)
        ->setCustomerGroupId($sharedCatalog->getCustomerGroupId())
        ->setGrantCatalogCategoryView(Permission::PERMISSION_ALLOW)
        ->setGrantCatalogProductPrice($i == 2 ? Permission::PERMISSION_DENY : Permission::PERMISSION_ALLOW)
        ->setGrantCheckoutItems($i == 1 ? Permission::PERMISSION_DENY : Permission::PERMISSION_ALLOW)
        ->save();

    /**
     *
     * Always deny everything for all other groups
     * /

    /** @var Permission $permission */
    $permission = $objectManager->create(Permission::class);
    $permission->setWebsiteId(1)
        ->setCategoryId($i + 3)
        ->setCustomerGroupId(null)
        ->setGrantCatalogCategoryView(Permission::PERMISSION_DENY)
        ->setGrantCatalogProductPrice(Permission::PERMISSION_DENY)
        ->setGrantCheckoutItems(Permission::PERMISSION_DENY)
        ->save();
}
