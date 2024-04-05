<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\SharedCatalog\Model\ResourceModel;

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;

/**
 * Category tree test to check category tree building with enabled shared catalog.
 *
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class CategoryTreeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var CategoryTree
     */
    private $categoryTree;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->categoryTree = $this->objectManager->create(CategoryTree::class);
    }

    /**
     * @magentoDataFixture Magento/SharedCatalog/_files/shared_category_product.php
     */
    public function testGetCategoryCollection()
    {
        $categoryRepository = $this->objectManager->get(CategoryRepositoryInterface::class);
        $category = $categoryRepository->get(10);
        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $product = $productRepository->get('Sku is \'sku\'');

        $categoryCollection = $this->categoryTree->getCategoryCollection($category->getId(), [$product->getSku()]);
        $categoryCollection->load();
        $this->assertEquals(1, $categoryCollection->count());

        $category = $categoryCollection->getFirstItem();
        $this->assertEquals(1, $category->getData('product_count'));
    }
}
