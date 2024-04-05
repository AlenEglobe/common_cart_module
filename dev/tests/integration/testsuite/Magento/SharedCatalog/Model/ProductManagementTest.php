<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\SharedCatalog\Api\CategoryManagementInterface;
use Magento\SharedCatalog\Api\Data\ProductItemInterface;
use Magento\SharedCatalog\Api\ProductItemRepositoryInterface;
use Magento\SharedCatalog\Api\ProductManagementInterface;
use Magento\SharedCatalog\Api\SharedCatalogManagementInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Test of managing products assigned to shared catalog.
 */
class ProductManagementTest extends TestCase
{
    /**
     * @var ProductManagementInterface
     */
    private $productManagement;

    /**
     * @var ProductInterfaceFactory
     */
    private $productFactory;

    /**
     * @var SharedCatalogManagementInterface
     */
    private $sharedCatalogManagement;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $objectManager = Bootstrap::getObjectManager();
        $this->productManagement = $objectManager->get(ProductManagementInterface::class);
        $this->productFactory = $objectManager->get(ProductInterfaceFactory::class);
        $this->sharedCatalogManagement = $objectManager->get(SharedCatalogManagementInterface::class);
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/category_product.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture btob/website_configuration/sharedcatalog_active 1
     */
    public function testAssignProductsWithUnassignedCategory(): void
    {
        $productSku = 'simple333';
        $categoryId = 333;
        $sharedCatalog = $this->sharedCatalogManagement->getPublicCatalog();

        $this->productManagement->assignProducts(
            $sharedCatalog->getId(),
            $this->getProductInstances([$productSku])
        );

        $categoryManagement = Bootstrap::getObjectManager()->get(CategoryManagementInterface::class);
        $sharedCatalogCategories = $categoryManagement->getCategories($sharedCatalog->getId());
        $this->assertContains($categoryId, $sharedCatalogCategories);
    }

    /**
     * Check that shared catalog records are created without duplicates.
     *
     * @magentoDataFixture Magento/SharedCatalog/_files/public_shared_catalog_products.php
     * @magentoDataFixture Magento/Catalog/_files/second_product_simple.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture btob/website_configuration/sharedcatalog_active 1
     */
    public function testAssignProductsWithSameSku(): void
    {
        $customerGroupId = $this->sharedCatalogManagement->getPublicCatalog()
            ->getCustomerGroupId();

        $productsBeforeAssign = $this->productManagement->getProducts($customerGroupId);
        $productSku = current($productsBeforeAssign);

        $this->productManagement->assignProducts(
            $customerGroupId,
            $this->getProductInstances([$productSku, $productSku, 'simple2'])
        );
        $productsAfterAssign = $this->productManagement->getProducts($customerGroupId);
        $assignedProducts = array_diff($productsAfterAssign, $productsBeforeAssign);

        $this->assertEquals(['simple2'], array_values($assignedProducts));
        $this->assertEquals(1, array_count_values($productsAfterAssign)[$productSku]);
    }

    /**
     * Check that only required shared catalog records are removed.
     *
     * @magentoDataFixture Magento/SharedCatalog/_files/public_shared_catalog_products.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture btob/website_configuration/sharedcatalog_active 1
     */
    public function testUnassignProductsWithSameSku(): void
    {
        $customerGroupId = $this->sharedCatalogManagement->getPublicCatalog()
            ->getCustomerGroupId();

        $productsBeforeUnassign = $this->productManagement->getProducts($customerGroupId);
        $productSku = current($productsBeforeUnassign);

        $this->productManagement->unassignProducts(
            $customerGroupId,
            $this->getProductInstances([$productSku, $productSku])
        );
        $productsAfterUnassign = $this->productManagement->getProducts($customerGroupId);
        $actualResult = array_diff($productsBeforeUnassign, $productsAfterUnassign);

        $this->assertEquals([$productSku], $actualResult);
    }

    /**
     * Check that providing product with non-existent SKU throws exception.
     *
     * @magentoDataFixture Magento/SharedCatalog/_files/public_shared_catalog_products.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture btob/website_configuration/sharedcatalog_active 1
     */
    public function testUnassignProductWithNonExistentSku(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Requested product doesn\'t exist: non_existent_product_sku.');

        $customerGroupId = $this->sharedCatalogManagement->getPublicCatalog()
            ->getCustomerGroupId();

        $products = $this->productManagement->getProducts($customerGroupId);
        $productSku = current($products);

        $this->productManagement->unassignProducts(
            $customerGroupId,
            $this->getProductInstances([$productSku, $productSku, 'non_existent_product_sku'])
        );
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/category_product.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture btob/website_configuration/sharedcatalog_active 1
     */
    public function testProductShouldBeAssignedToGeneralAndNotLoggedInCustomerGroupsInPublicSharedCatalog(): void
    {
        $productSku = 'simple333';
        $sharedCatalog = $this->sharedCatalogManagement->getPublicCatalog();
        $this->productManagement->assignProducts(
            $sharedCatalog->getId(),
            $this->getProductInstances([$productSku])
        );
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = Bootstrap::getObjectManager()->get(SearchCriteriaBuilder::class);
        /** @var ProductItemRepositoryInterface $sharedCatalogProductItemRepository */
        $sharedCatalogProductItemRepository = Bootstrap::getObjectManager()->get(ProductItemRepositoryInterface::class);
        $searchCriteriaBuilder->addFilter(ProductItemInterface::SKU, [$productSku], 'in');
        $searchCriteria = $searchCriteriaBuilder->create();
        $items = $sharedCatalogProductItemRepository->getList($searchCriteria)->getItems();
        $customerGroupIds = [];
        foreach ($items as $item) {
            $customerGroupIds[] = $item->getCustomerGroupId();
        }
        $this->assertEqualsCanonicalizing(
            [GroupInterface::NOT_LOGGED_IN_ID, $sharedCatalog->getCustomerGroupId()],
            $customerGroupIds
        );
    }

    /**
     * Retrieve product instances for shared catalog actions.
     *
     * @param array $skus
     * @return ProductInterface[]
     */
    private function getProductInstances(array $skus): array
    {
        $products = [];
        foreach ($skus as $sku) {
            $products[] = $this->productFactory->create()
                ->setSku($sku);
        }

        return $products;
    }
}
