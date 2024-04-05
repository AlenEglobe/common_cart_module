<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\QuickOrder\Model\CatalogPermissions;

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;

class PermissionsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var Permissions
     */
    private $permissionsModel;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->permissionsModel = $this->objectManager->create(Permissions::class);
    }

    /**
     * Test that when using QuickOrder's predictive search on the storefront and
     * catalog/magento_catalogpermissions/enabled is on, then a product belonging to a category is unable to be viewed
     * by default and a product not belonging to any category is able to be viewed by default
     *
     * Given a simple product A assigned to a category and a simple product B which is not assigned to a category
     * And Catalog > Category Permissions > Enable is set to Yes in current store
     * When a new storefront product collection is filtered by simple product A's id and a Permissions model used in
     * QuickOrder predictive search
     * Then the storefront product collection returns an empty result set
     * When a new storefront product collection is filtered by simple product B's id and a Permissions model used in
     * QuickOrder predictive search
     * Then the storefront product collection returns a nonempty result set
     *
     * @magentoConfigFixture current_store catalog/magento_catalogpermissions/enabled 1
     * @magentoDataFixture Magento/Catalog/_files/category_product.php
     * @magentoDataFixture Magento/Catalog/_files/second_product_simple.php
     */
    public function testCategoryPermissionsFilterOutProductsBelongingToACategoryByDefaultForQuickOrderPredictiveSearch()
    {
        $productCollection = $this->objectManager->create(ProductCollection::class);
        $productCollection->addIdFilter(333);
        $this->permissionsModel->applyPermissionsToProductCollection($productCollection);
        $this->assertEmpty($productCollection->getItems());

        $productCollection = $this->objectManager->create(ProductCollection::class);
        $productCollection->addIdFilter(6);
        $this->permissionsModel->applyPermissionsToProductCollection($productCollection);
        $this->assertNotEmpty($productCollection->getItems());
    }
}
