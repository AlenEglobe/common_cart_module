<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Company\Test\Fixture\CustomerGroup;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\SharedCatalog\Api\SharedCatalogManagementInterface;
use Magento\SharedCatalog\Test\Fixture\AssignProducts;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation disabled
 */
class ProductCollectionTest extends TestCase
{

    /**
     * @return void
     */
    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/sharedcatalog_active', 1),
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(
            AssignProducts::class,
            [
                'product_ids' => [
                    '$product.id$',
                ],
                'catalog_id' => '1',
            ]
        )
    ]
    public function testLoadForDefaultSharedCatalogCustomerGroup()
    {
        /** @var SharedCatalogManagementInterface $sharedCatalogManagement */
        $sharedCatalogManagement = Bootstrap::getObjectManager()->get(SharedCatalogManagementInterface::class);

        /** @var Product $product00 */
        $product = DataFixtureStorageManager::getStorage()->get('product');

        $publicCatalog = $sharedCatalogManagement->getPublicCatalog();
        $items = $this->loadItems($publicCatalog->getCustomerGroupId());

        $this->assertContains($product->getSku(), array_map(function ($product) {
            return $product->getSku();
        }, $items, []));
    }

    /**
     * @return void
     */
    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/sharedcatalog_active', 1),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(CustomerGroup::class, as: 'customer_group'),
        DataFixture(
            Company::class,
            [
                'super_user_id' => '$company_admin.id$',
                'customer_group_id' => '$customer_group.id$'
            ],
            'company'
        ),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        ),
    ]
    public function testLoadForCustomSharedCatalogCustomerGroup()
    {
        /** @var GroupInterface $customerGroup */
        $customerGroup = DataFixtureStorageManager::getStorage()->get('customer_group');

        $items = $this->loadItems($customerGroup->getId());
        $this->assertCount(0, $items);
    }

    /**
     * @return void
     */
    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/sharedcatalog_active', 1),

        DataFixture(CustomerGroup::class, as: 'customer_group'),
        DataFixture(ProductFixture::class, as: 'product_00'),
        DataFixture(ProductFixture::class, as: 'product_01'),
    ]
    public function testLoadForNonSharedCatalogCustomerGroup()
    {
        /** @var GroupInterface $customerGroup */
        $customerGroup = DataFixtureStorageManager::getStorage()->get('customer_group');

        /** @var Product $product00 */
        $product00 = DataFixtureStorageManager::getStorage()->get('product_00');

        /** @var Product $product01 */
        $product01 = DataFixtureStorageManager::getStorage()->get('product_01');

        $customerGroupProducts = $this->loadItems($customerGroup->getId());
        $customerGroupSKUs = array_map(function ($product) {
            return $product->getSku();
        }, $customerGroupProducts, []);

        $this->assertEquals(
            [
                $product00->getSku(),
                $product01->getSku()
            ],
            $customerGroupSKUs
        );
    }

    /**
     * @param int $customerGroupId
     * @return Product[]
     */
    private function loadItems(int $customerGroupId): array
    {
        Bootstrap::getObjectManager()->get(HttpContext::class)
            ->setValue(CustomerContext::CONTEXT_GROUP, $customerGroupId, null);

        /** @var ProductCollection $productCollection */
        $productCollection = Bootstrap::getObjectManager()->create(ProductCollection::class);
        $productCollection->addPriceData($customerGroupId);
        $productCollection->load();

        return $productCollection->getItems();
    }
}
