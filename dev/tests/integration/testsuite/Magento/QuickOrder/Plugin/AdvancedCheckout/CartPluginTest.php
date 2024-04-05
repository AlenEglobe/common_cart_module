<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\QuickOrder\Plugin\AdvancedCheckout;

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\SharedCatalog\Api\SharedCatalogManagementInterface;
use Magento\SharedCatalog\Api\ProductManagementInterface;
use Magento\SharedCatalog\Model\Config as SharedCatalogConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Test for CartPlugin class.
 *
 * @magentoDataFixture Magento/Catalog/_files/multiple_products.php
 * @magentoDataFixture Magento/Catalog/_files/simple_products_not_visible_individually.php
 * @magentoAppIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CartPluginTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \Magento\AdvancedCheckout\Model\Cart
     */
    private $cart;

    /**
     * Set up.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->cart = $this->objectManager->create(\Magento\AdvancedCheckout\Model\Cart::class);

        $configResource = $this->objectManager->get(\Magento\Config\Model\ResourceModel\Config::class);
        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $appConfig = $this->objectManager->get(\Magento\Framework\App\Config::class);

        $configResource->saveConfig(
            'btob/website_configuration/quickorder_active',
            1,
            'default',
            $storeManager->getDefaultStoreView()->getId()
        );
        $appConfig->clean();
    }

    /**
     * Test that QuickOrder\Plugin\AdvancedCheckout\SetQuantityForQuickOrderItemsPlugin::beforeCheckItems sets minimum
     * quantity allowed in cart when quantity requested is absent, and that AdvancedCheckout\Model\Cart::checkItem
     * verifies quantity and SKU requested
     *
     * Given simple products A,B,C with a visibility/status/qty assignment of both/enabled/100,
     * catalog/disabled/140, and not visible/enabled/30, respectively
     * When product A is requested to be validated by the cart via QuickOrder with a quantity value of an empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a success code to the validation result
     * When product A is requested to be validated by the cart via QuickOrder with a quantity value of 101
     * Then the cart assigns a quantity of 101 to the validation result
     * And the cart assigns a failure code related to the qty requested to the validation result
     * When product B is requested to be validated by the cart via QuickOrder with a quantity value of an empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a failure code related to the SKU lookup failing to the validation result
     * When a nonexistent product is requested to be validated by the cart via QuickOrder with a qty of empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a failure code related to the SKU lookup failing to the validation result
     * When product C is requested to be validated by the cart via QuickOrder with a qty of empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a failure code related to the SKU lookup failing to the validation result
     *
     * @dataProvider checkItemDataProvider
     * @param array $passedData
     * @param array $expectedItem
     * @return void
     */
    public function testQuickOrderQtyAndSkuValidationAndMinimumQtyAssignmentForGuest(
        array $passedData,
        array $expectedItem
    ): void {
        $this->checkItems($passedData, $expectedItem);
    }

    /**
     * Test that QuickOrder\Plugin\AdvancedCheckout\SetQuantityForQuickOrderItemsPlugin::beforeCheckItems sets minimum
     * quantity allowed in cart when quantity requested is absent, and that AdvancedCheckout\Model\Cart::checkItem
     * verifies quantity and SKU requested for products in public shared catalog
     *
     * Given simple products A,B,C with a visibility/status/qty assignment of both/enabled/100,
     * catalog/disabled/140, and not visible/enabled/30, respectively
     * And General > B2B Features > Enable Shared Catalog is set to Yes for the current website
     * When product A is assigned to the public shared catalog
     * And product A is requested to be validated by the cart via QuickOrder with a quantity value of an empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a success code to the validation result
     * When product A is assigned to the public shared catalog
     * And product A is requested to be validated by the cart via QuickOrder with a quantity value of 101
     * Then the cart assigns a quantity of 101 to the validation result
     * And the cart assigns a failure code related to the qty requested to the validation result
     * When product B is assigned to the public shared catalog
     * And product B is requested to be validated by the cart via QuickOrder with a quantity value of an empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a failure code related to the SKU lookup failing to the validation result
     * When a nonexistent product is attempted to be assigned to the public shared catalog
     * And nonexistent product is requested to be validated by the cart via QuickOrder with a qty of empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a failure code related to the SKU lookup failing to the validation result
     * When product C is assigned to the public shared catalog
     * And product C is requested to be validated by the cart via QuickOrder with a qty of empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a failure code related to the SKU lookup failing to the validation result
     *
     * @param array $passedData
     * @param array $expectedItem
     * @return void
     * @dataProvider checkItemDataProvider
     * @see \Magento\SharedCatalog\Plugin\AdvancedCheckout\Model\HideProductsAbsentInSharedCatalogPlugin::afterCheckItem
     */
    public function testQuickOrderQtyAndSkuValidationAndMinimumQtyAssignmentForGuestWithProductsInSharedCatalog(
        array $passedData,
        array $expectedItem
    ): void {
        $this->checkItemsWithSharedCatalog($passedData, $expectedItem);
    }

    /**
     * Test that QuickOrder\Plugin\AdvancedCheckout\SetQuantityForQuickOrderItemsPlugin::beforeCheckItems sets minimum
     * quantity allowed in cart when quantity requested is absent, and that AdvancedCheckout\Model\Cart::checkItem
     * verifies quantity and SKU requested for products in public shared catalog in a cart specifically assigned to a
     * company admin
     *
     * Given simple products A,B,C with a visibility/status/qty assignment of both/enabled/100,
     * catalog/disabled/140, and not visible/enabled/30, respectively
     * And a company and a company admin in General customer group
     * And the company is assigned to the public shared catalog
     * And General > B2B Features > Enable Shared Catalog is set to Yes for the current website
     * And Customers > Customer Configuration > Create New Account Options > Enable Automatic Assignment
     * to Customer Group is set to Yes for the current store
     * And Customers > Customer Configuration > Create New Account Options > Default Group is set
     * to Wholesale for the current store
     * When the company admin is assigned to the cart
     * And product A is assigned to the public shared catalog
     * And product A is requested to be validated by the cart via QuickOrder with a quantity value of an empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a success code to the validation result
     * When the company admin is assigned to the cart
     * And product A is assigned to the public shared catalog
     * And product A is requested to be validated by the cart via QuickOrder with a quantity value of 101
     * Then the cart assigns a quantity of 101 to the validation result
     * And the cart assigns a failure code related to the qty requested to the validation result
     * When the company admin is assigned to the cart
     * And product B is assigned to the public shared catalog
     * And product B is requested to be validated by the cart via QuickOrder with a quantity value of an empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a failure code related to the SKU lookup failing to the validation result
     * When the company admin is assigned to the cart
     * And a nonexistent product is attempted to be assigned to the public shared catalog
     * And nonexistent product is requested to be validated by the cart via QuickOrder with a qty of empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a failure code related to the SKU lookup failing to the validation result
     * When the company admin is assigned to the cart
     * And product C is assigned to the public shared catalog
     * And product C is requested to be validated by the cart via QuickOrder with a qty of empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a failure code related to the SKU lookup failing to the validation result
     *
     * @param array $passedData
     * @param array $expectedItem
     * @return void
     * @dataProvider checkItemDataProvider
     * @see \Magento\SharedCatalog\Plugin\AdvancedCheckout\Model\HideProductsAbsentInSharedCatalogPlugin::afterCheckItem
     * @magentoConfigFixture current_store customer/create_account/auto_group_assign 1
     * @magentoConfigFixture current_store customer/create_account/default_group 2
     * @magentoDataFixture Magento/Catalog/_files/multiple_products.php
     * @magentoDataFixture Magento/SharedCatalog/_files/assigned_company.php
     */
    public function testQuickOrderQtyAndSkuValidationAndMinimumQtyAssignmentForCompanyAdminWithProductsInSharedCatalog(
        array $passedData,
        array $expectedItem
    ): void {
        $customerRegistry = $this->objectManager->get(CustomerRegistry::class);
        $customer = $customerRegistry->retrieveByEmail('email1@companyquote.com');
        $this->cart->setCustomer($customer);
        $expectedItem['code'] = $expectedItem['sc_code'];

        $this->checkItemsWithSharedCatalog($passedData, $expectedItem);
    }

    /**
     * Test that QuickOrder\Plugin\AdvancedCheckout\SetQuantityForQuickOrderItemsPlugin::beforeCheckItems sets minimum
     * quantity allowed in cart when quantity requested is absent, and that AdvancedCheckout\Model\Cart::checkItem
     * verifies quantity and SKU requested for products in public shared catalog in a cart specifically assigned to a
     * company admin in Retailer customer group
     *
     * Given simple products A,B,C with a visibility/status/qty assignment of both/enabled/100,
     * catalog/disabled/140, and not visible/enabled/30, respectively
     * And a company and a company admin
     * And the company is assigned to the public shared catalog
     * And General > B2B Features > Enable Shared Catalog is set to Yes for the current website
     * And Customers > Customer Configuration > Create New Account Options > Enable Automatic Assignment
     * to Customer Group is set to Yes for the current store
     * And Customers > Customer Configuration > Create New Account Options > Default Group is set to Wholesale
     * for the current store
     * When the company admin is assigned to the cart
     * And the company admin is assigned to the Retailer customer group
     * And product A is assigned to the public shared catalog
     * And product A is requested to be validated by the cart via QuickOrder with a quantity value of an empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a success code to the validation result
     * When the company admin is assigned to the cart
     * And the company admin is assigned to the Retailer customer group
     * And product A is assigned to the public shared catalog
     * And product A is requested to be validated by the cart via QuickOrder with a quantity value of 101
     * Then the cart assigns a quantity of 101 to the validation result
     * And the cart assigns a failure code related to the qty requested to the validation result
     * When the company admin is assigned to the cart
     * And the company admin is assigned to the Retailer customer group
     * And product B is assigned to the public shared catalog
     * And product B is requested to be validated by the cart via QuickOrder with a quantity value of an empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a failure code related to the SKU lookup failing to the validation result
     * When the company admin is assigned to the cart
     * And the company admin is assigned to the Retailer customer group
     * And a nonexistent product is attempted to be assigned to the public shared catalog
     * And nonexistent product is requested to be validated by the cart via QuickOrder with a qty of empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a failure code related to the SKU lookup failing to the validation result
     * When the company admin is assigned to the cart
     * And the company admin is assigned to the Retailer customer group
     * And product C is assigned to the public shared catalog
     * And product C is requested to be validated by the cart via QuickOrder with a qty of empty string
     * Then the cart assigns a quantity of 1 to the validation result
     * And the cart assigns a failure code related to the SKU lookup failing to the validation result
     *
     * @param array $passedData
     * @param array $expectedItem
     * @return void
     * @dataProvider checkItemDataProvider
     * @see \Magento\SharedCatalog\Plugin\AdvancedCheckout\Model\HideProductsAbsentInSharedCatalogPlugin::afterCheckItem
     * @magentoConfigFixture current_store customer/create_account/auto_group_assign 1
     * @magentoConfigFixture current_store customer/create_account/default_group 2
     * @magentoDataFixture Magento/Catalog/_files/multiple_products.php
     * @magentoDataFixture Magento/SharedCatalog/_files/assigned_company.php
     * phpcs:disable Generic.Files.LineLength.TooLong
     */
    public function testQuickOrderQtyAndSkuValidationAndMinimumQtyAssignmentForCompanyAdminInRetailerCustomerGroupWithProductsInSharedCatalog(
        array $passedData,
        array $expectedItem
    ): void {
        $customerRegistry = $this->objectManager->get(CustomerRegistry::class);
        $customer = $customerRegistry->retrieveByEmail('email1@companyquote.com');
        $customer->setGroupId(3);
        $this->cart->setCustomer($customer);
        $expectedItem['code'] = $expectedItem['sc_code'];
        $this->checkItemsWithSharedCatalog($passedData, $expectedItem);
    }

    /**
     * @return array
     */
    public function checkItemDataProvider(): array
    {
        return [
            [
                [
                    'sku' => 'simple1',
                    'qty' => ''
                ],
                [
                    'qty' => (float)1,
                    'sku' => 'simple1',
                    'code' => \Magento\AdvancedCheckout\Helper\Data::ADD_ITEM_STATUS_SUCCESS,
                    'sc_code' => \Magento\AdvancedCheckout\Helper\Data::ADD_ITEM_STATUS_SUCCESS,
                ]
            ],
            [
                [
                    'sku' => 'simple1',
                    'qty' => (float)101,
                ],
                [
                    'qty' => (float)101,
                    'sku' => 'simple1',
                    'code' => \Magento\AdvancedCheckout\Helper\Data::ADD_ITEM_STATUS_FAILED_QTY_ALLOWED,
                    'sc_code' => \Magento\AdvancedCheckout\Helper\Data::ADD_ITEM_STATUS_FAILED_QTY_ALLOWED,
                ]
            ],
            [
                [
                    'sku' => 'simple3',
                    'qty' => ''
                ],
                [
                    'qty' => (float)1,
                    'sku' => 'simple3',
                    'code' => \Magento\AdvancedCheckout\Helper\Data::ADD_ITEM_STATUS_FAILED_SKU,
                    'sc_code' => \Magento\AdvancedCheckout\Helper\Data::ADD_ITEM_STATUS_FAILED_SKU,
                ]
            ],
            [
                [
                    'sku' => 'not_existing_product',
                    'qty' => ''
                ],
                [
                    'qty' => (float)1,
                    'sku' => 'not_existing_product',
                    'code' => \Magento\AdvancedCheckout\Helper\Data::ADD_ITEM_STATUS_FAILED_SKU,
                    'sc_code' => \Magento\AdvancedCheckout\Helper\Data::ADD_ITEM_STATUS_FAILED_SKU,
                ]
            ],
            [
                [
                    'sku' => 'simple_not_visible_1',
                    'qty' => '',
                ],
                [
                    'qty' => (float)1,
                    'sku' => 'simple_not_visible_1',
                    'code' => \Magento\AdvancedCheckout\Helper\Data::ADD_ITEM_STATUS_SUCCESS,
                    'sc_code' => \Magento\AdvancedCheckout\Helper\Data::ADD_ITEM_STATUS_FAILED_SKU,
                ],
            ],
            [
                [
                    'sku' => 'SIMPLE1',
                    'qty' => ''
                ],
                [
                    'qty' => (float)1,
                    'sku' => 'SIMPLE1',
                    'code' => \Magento\AdvancedCheckout\Helper\Data::ADD_ITEM_STATUS_SUCCESS,
                    'sc_code' => \Magento\AdvancedCheckout\Helper\Data::ADD_ITEM_STATUS_SUCCESS,
                    'price' => 5.99,
                    'name' => 'Simple Product1'
                ]
            ],
        ];
    }

    /**
     * Call \Magento\AdvancedCheckout\Model\Cart::checkItems on $passedData and assert return value is $expectedItem
     *
     * @param array $passedData
     * @param array $expectedItem
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function checkItems(array $passedData, array $expectedItem)
    {
        unset($expectedItem['sc_code']);
        $this->cart->setContext(\Magento\AdvancedCheckout\Model\Cart::CONTEXT_FRONTEND);
        $result = $this->cart->checkItems([$passedData]);
        foreach ($result as $resultItem) {
            foreach ($expectedItem as $itemKey => $itemValue) {
                $this->assertEquals($itemValue, $resultItem[$itemKey]);
            }
        }
    }

    /**
     * Assign product in $passedData to shared public catalog and call checkItems
     *
     * @param array $passedData
     * @param array $expectedItem
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function checkItemsWithSharedCatalog(array $passedData, array $expectedItem)
    {
        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $website = $storeManager->getWebsite();
        $mutableConfig = $this->objectManager->get(MutableScopeConfigInterface::class);
        $mutableConfig->setValue(
            SharedCatalogConfig::CONFIG_SHARED_CATALOG,
            1,
            ScopeInterface::SCOPE_WEBSITE,
            $website->getCode()
        );

        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        try {
            $product = $productRepository->get($passedData['sku']);

            $sharedCatalogManagement = $this->objectManager->get(SharedCatalogManagementInterface::class);
            $sharedCatalog = $sharedCatalogManagement->getPublicCatalog();
            $productManagement = $this->objectManager->get(ProductManagementInterface::class);
            $productManagement->assignProducts($sharedCatalog->getId(), [$product]);
        } catch (NoSuchEntityException $e) {
        }

        $this->checkItems($passedData, $expectedItem);
    }
}
