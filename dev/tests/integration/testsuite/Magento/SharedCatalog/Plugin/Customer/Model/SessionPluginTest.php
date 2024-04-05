<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Plugin\Customer\Model;

use Magento\CatalogPermissions\Model\Indexer\Category as CategoryPermissionsIndexer;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;
use Magento\CatalogPermissions\Model\Permission;
use Magento\Framework\App\Area;

/**
 * Integration test for \Magento\SharedCatalog\Plugin\Customer\Model\SessionPlugin.php
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class SessionPluginTest extends AbstractController
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Session
     */
    private $session;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $objectManager = $this->_objectManager;

        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->session = $objectManager->get(Session::class);
    }

    /**
     * @magentoConfigFixture current_store catalog/magento_catalogpermissions/enabled 1
     * @magentoConfigFixture current_store catalog/magento_catalogpermissions/grant_catalog_category_view 1
     * @magentoConfigFixture current_store catalog/magento_catalogpermissions/grant_catalog_product_price 1
     * @magentoConfigFixture current_store catalog/magento_catalogpermissions/grant_checkout_items 1
     * @magentoDataFixture Magento/CatalogPermissions/_files/reindex_permissions.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/Catalog/_files/categories.php
     * @magentoDataFixture Magento/CatalogPermissions/_files/enable_permissions_for_specific_customer_group.php
     * @magentoDataFixture Magento/CatalogPermissions/_files/permission.php
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testCustomerDoesNotSeeProductInDifferentSharedCatalogAfterSwitchingGroupId()
    {
        $this->reindexCategories();

        $customer = $this->customerRepository->get('customer@example.com');
        $categoryId = 6;
        $productName = "Simple Product Two Permission Test";

        $this->loginAsCustomer($customer);
        $this->dispatch("catalog/category/view/id/{$categoryId}");
        $response = $this->getResponse();
        $this->assertEquals(302, $response->getHttpResponseCode());
        $this->assertStringNotContainsString($productName, $response->getBody());

        /** @var $permission Permission */
        $permission = $this->_objectManager->create(\Magento\CatalogPermissions\Model\Permission::class);
        $websiteId = $this->_objectManager->get(
            \Magento\Store\Model\StoreManagerInterface::class
        )->getWebsite()->getId();

        $permission->setWebsiteId($websiteId)
            ->setCategoryId($categoryId)
            ->setCustomerGroupId(3)
            ->setGrantCatalogCategoryView(Permission::PERMISSION_ALLOW)
            ->save();

        $customer->setGroupId(3);
        $this->customerRepository->save($customer);

        $this->reindexCategories();

        Bootstrap::getInstance()->getBootstrap()->getApplication()->reinitialize();
        $this->resetRequest();
        $this->resetResponse();

        $this->dispatch("catalog/category/view/id/{$categoryId}");
        $response = $this->getResponse();
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertStringContainsString($productName, $response->getBody());
    }

    /**
     * Reset shared response object instance and set property to null (to be reinitialized after subsequent request)
     */
    private function resetResponse()
    {
        $this->_objectManager->removeSharedInstance(ResponseInterface::class);
        $this->_response = null;
    }

    /**
     * Login as a customer.
     *
     * @param CustomerInterface $customer
     * @return void
     */
    private function loginAsCustomer(CustomerInterface $customer)
    {
        $this->session->setCustomerDataAsLoggedIn($customer);
    }

    /**
     * Reindex categories.
     *
     * @return void
     */
    private function reindexCategories()
    {
        Bootstrap::getInstance()->reinitialize();
        Bootstrap::getInstance()->loadArea(Area::AREA_ADMINHTML);
        $indexerRegistry = Bootstrap::getObjectManager()->create(IndexerRegistry::class);
        $indexerRegistry->get(CategoryPermissionsIndexer::INDEXER_ID)->reindexAll();
        $indexer = $this->_objectManager->create(\Magento\Indexer\Model\Indexer::class);
        $indexer->load(\Magento\CatalogPermissions\Model\Indexer\Category::INDEXER_ID);
        $indexer->reindexAll();
    }
}
