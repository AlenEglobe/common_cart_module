<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Service\V1;

use Magento\Authorization\Test\Fixture\Role as RoleFixture;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\TierPriceInterface;
use Magento\Catalog\Api\TierPriceStorageInterface;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\CatalogPermissions\Model\Permission as PermissionModel;
use Magento\CatalogPermissions\Test\Fixture\Permission as PermissionFixture;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Integration\Api\AdminTokenServiceInterface;
use Magento\SharedCatalog\Test\Fixture\AssignCategory;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\SharedCatalog\Test\Fixture\AssignProducts;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog as SharedCatalogFixture;
use Magento\Store\Test\Fixture\Store;
use Magento\Store\Test\Fixture\Website;
use Magento\TestFramework\Bootstrap;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\User\Test\Fixture\User;

/**
 * Tests for assigning and resetting tier price for shared catalog product
 */
#[
    Config('catalog/magento_catalogpermissions/enabled', 1),
    DataFixture(Website::class, as: 'website'),
    DataFixture(Store::class, as: 'store'),
    DataFixture(Customer::class, as: 'company_admin'),
    DataFixture(RoleFixture::class, as: 'restrictedRole'),
    DataFixture(User::class, ['role_id' => '$restrictedRole.id$'], 'restrictedUser'),
    DataFixture(
        Company::class,
        [
            'sales_representative_id' => '$restrictedUser.id$',
            'super_user_id' => '$company_admin.id$'
        ],
        'company'
    ),
    DataFixture(SharedCatalogFixture::class, as: 'shared_catalog'),
    DataFixture(CategoryFixture::class, as: 'category'),
    DataFixture(
        PermissionFixture::class,
        [
            'category_id' => '$category.id$',
            'customer_group_id' => '$shared_catalog.customer_group_id$',
            'grant_catalog_category_view' => PermissionModel::PERMISSION_ALLOW,
            'grant_catalog_product_price' => PermissionModel::PERMISSION_ALLOW,
            'grant_checkout_items' => PermissionModel::PERMISSION_ALLOW,
        ]
    ),
    DataFixture(
        ProductFixture::class,
        [
            'price' => '10',
            'weight' => '18',
            'category_ids' => ['$category.id$']
        ],
        'simple'
    ),
    DataFixture(AssignCategory::class, ['catalog_id' => '$shared_catalog.id$', 'category' => '$category$']),
    DataFixture(AssignProducts::class, ['catalog_id' => '$shared_catalog.id$', 'product_ids' => ['$simple.id$']])
]
class TierPriceTest extends AbstractSharedCatalogTest
{
    private const SERVICE_NAME_ASSIGN_TIER_PRICE = 'sharedCatalogAssignTierPriceV1';
    private const SERVICE_NAME_RESET_TIER_PRICE = 'sharedCatalogResetTierPriceV1';
    private const SERVICE_VERSION = 'V1';
    private const TIER_PRICE = 123.13;
    private const DEFAULT_PRICE = 10;

    /**
     * @var SharedCatalogInterface
     */
    private $sharedCatalog;

    /**
     * @var ProductInterface
     */
    private $product;

    /**
     * @var AdminTokenServiceInterface|null
     */
    private $adminTokenService;

    /**
     * @var string
     */
    private $accessToken;

    /**
     * Set up.
     *
     * @return void
     * @throws AuthenticationException
     * @throws InputException
     * @throws LocalizedException
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var SharedCatalogInterface $sharedCatalog */
        $this->sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');

        /** @var ProductInterface $product */
        $this->product = DataFixtureStorageManager::getStorage()->get('simple');

        $this->adminTokenService = $this->objectManager->get(AdminTokenServiceInterface::class);
        $this->accessToken = $this->adminTokenService->createAdminAccessToken(
            DataFixtureStorageManager::getStorage()->get('restrictedUser')->getData('username'),
            Bootstrap::ADMIN_PASSWORD
        );
    }

    /**
     * Test assign tier price for products.
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testAssignTierPrice(): void
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/sharedCatalog/%d/assignTierPrices', $this->sharedCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_POST,
                'token' => $this->accessToken
            ],
            'soap' => [
                'service' => self::SERVICE_NAME_ASSIGN_TIER_PRICE,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_NAME_ASSIGN_TIER_PRICE . 'execute',
                'token' => $this->accessToken
            ],
        ];

        $params = [
            'sharedCatalogId' => $this->sharedCatalog->getId(),
            'prices' => [[
                'sku' => $this->product->getSku(),
                'price' => self::TIER_PRICE,
                'priceType' => 'fixed',
                'websiteId' => DataFixtureStorageManager::getStorage()->get('website')->getId(),
                'customerGroup' =>
                    DataFixtureStorageManager::getStorage()->get('shared_catalog')->getData('customer_group_id'),
                'quantity' => 1
            ]]
        ];

        $this->_webApiCall($serviceInfo, $params);

        $tierPrices = $this->objectManager->get(TierPriceStorageInterface::class)->get([$this->product->getSku()]);
        /** @var TierPriceInterface $tierPrice */
        $tierPrice = array_shift($tierPrices)->getPrice();
        $this->assertEquals(self::TIER_PRICE, $tierPrice);
    }

    public function testResetTierPrice(): void
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/sharedCatalog/%d/resetTierPrices', $this->sharedCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_POST,
                'token' => $this->accessToken
            ],
            'soap' => [
                'service' => self::SERVICE_NAME_RESET_TIER_PRICE,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_NAME_RESET_TIER_PRICE . 'execute',
                'token' => $this->accessToken
            ],
        ];

        $params = [
            'sharedCatalogId' => $this->sharedCatalog->getId(),
            'skus' => [
                'sku' => $this->product->getSku()
            ]
        ];

        $this->_webApiCall($serviceInfo, $params);

        $tierPrices = $this->objectManager->get(TierPriceStorageInterface::class)->get([$this->product->getSku()]);
        $this->assertEmpty($tierPrices);
        $this->assertEquals(self::DEFAULT_PRICE, $this->product->getPrice());
    }
}
