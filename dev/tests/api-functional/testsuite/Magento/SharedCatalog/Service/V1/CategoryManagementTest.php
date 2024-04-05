<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\SharedCatalog\Service\V1;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Test\Fixture\Category;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;

/**
 * Tests for shared catalog categories actions (assign, unassign, getting).
 */
#[
    Config('catalog/magento_catalogpermissions/enabled', 1),
    DataFixture(SharedCatalog::class, as: 'shared_catalog'),
    DataFixture(Category::class, as: 'category'),
]
class CategoryManagementTest extends AbstractSharedCatalogTest
{
    private const SERVICE_READ_NAME = 'sharedCatalogCategoryManagementV1';
    private const SERVICE_VERSION = 'V1';

    /**
     * Test flows is: assign category, double-check with get categories, unassign category, and double-check again.
     *
     * @return void
     * @throws LocalizedException
     */
    public function testInvoke()
    {
        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');
        /** @var CategoryInterface $sharedCatalog */
        $category = DataFixtureStorageManager::getStorage()->get('category');

        $assignCategoriesServiceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/sharedCatalog/%d/assignCategories', $sharedCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'assignCategories',
            ],
        ];

        $categoriesParams = [
            [
                'id' => $category->getId(),
                'name' => $category->getName()
            ]
        ];

        $this->_webApiCall(
            $assignCategoriesServiceInfo,
            ['id' => $sharedCatalog->getId(), 'categories' => $categoriesParams]
        );

        $getCategoriesServiceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/sharedCatalog/%d/categories', $sharedCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'getCategories',
            ],
        ];

        $respCategoryIds = $this->_webApiCall($getCategoriesServiceInfo, ['id' => $sharedCatalog->getId()]);

        $expectedResult = [
            0 => $category->getId()
        ];
        $this->assertIsArray($respCategoryIds, 'List of categories is not an array.');
        $this->assertEquals($expectedResult, $respCategoryIds, 'List of categories is wrong.');

        $unassignCategoriesServiceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/sharedCatalog/%d/unassignCategories', $sharedCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'unassignCategories',
            ],
        ];

        $resp = $this->_webApiCall(
            $unassignCategoriesServiceInfo,
            ['id' => $sharedCatalog->getId(), 'categories' => $categoriesParams]
        );
        $this->assertTrue($resp, 'Unassign categories did not work.');

        $respCategoryIds = $this->_webApiCall($getCategoriesServiceInfo, ['id' => $sharedCatalog->getId()]);
        $this->assertIsArray($respCategoryIds, 'List of categories is not an array.');
        $this->assertEmpty($respCategoryIds, 'List of categories is wrong.');
    }
}
