<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Service\V1;

use Magento\Authorization\Test\Fixture\Role as RoleFixture;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\Store\Api\Data\GroupInterface as StoreGroupInterface;
use Magento\Store\Test\Fixture\Group;
use Magento\Store\Test\Fixture\Store;
use Magento\Store\Test\Fixture\Website;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\User\Test\Fixture\User;

/**
 * Test for update shared catalog.
 */
class UpdateSharedCatalogTest extends AbstractSharedCatalogTest
{
    private const SERVICE_READ_NAME = 'sharedCatalogSharedCatalogRepositoryV1';
    private const SERVICE_VERSION = 'V1';
    private const RESOURCE_PATH = '/V1/sharedCatalog/%d';

    /**
     * Test for update shared catalog.
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
        DataFixture(SharedCatalog::class, as: 'shared_catalog'),
        DataFixture(Group::class, as: 'store_group'),
        DataFixture(Store::class, as: 'store'),
        DataFixture(Website::class, as: 'website'),
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
    ]
    public function testInvoke()
    {
        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');
        /** @var StoreGroupInterface $storeGroup */
        $storeGroup = DataFixtureStorageManager::getStorage()->get('store_group');

        $initialStoreId = $sharedCatalog->getStoreId();
        $newSharedCatalogData = [
            'id' => $sharedCatalog->getId(),
            'name' => 'Name_' . time(),
            'description' => 'Description_' . time(),
            'store_id' => $storeGroup->getId(),
            'customer_group_id' => $sharedCatalog->getCustomerGroupId(),
            'created_at' => $sharedCatalog->getCreatedAt(),
            'created_by' => $sharedCatalog->getCreatedBy(),
            'type' => $sharedCatalog->getType(),
            'tax_class_id' => $sharedCatalog->getTaxClassId()
        ];

        $this->assertNotEquals(
            $initialStoreId,
            $newSharedCatalogData['store_id'],
            'Different store group ids should be used for the test.'
        );

        $serviceInfo = [
            'rest' => [
                'resourcePath' => sprintf(self::RESOURCE_PATH, $sharedCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_PUT,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'save',
            ],
        ];

        $updatedSharedCatalogId = $this->_webApiCall($serviceInfo, ['sharedCatalog' => $newSharedCatalogData]);
        $this->assertEquals($updatedSharedCatalogId, $sharedCatalog->getId(), 'Could not update shared catalog.');

        $updatedSharedCatalog = $this->objectManager->create(
            \Magento\SharedCatalog\Api\SharedCatalogRepositoryInterface::class
        )->get($sharedCatalog->getId());

        $this->assertEquals(
            $newSharedCatalogData['name'],
            $updatedSharedCatalog->getName(),
            'Could not update shared catalog name.'
        );
        $this->assertEquals(
            $newSharedCatalogData['description'],
            $updatedSharedCatalog->getDescription(),
            'Could not update shared catalog description.'
        );
        $this->assertEquals(
            $newSharedCatalogData['store_id'],
            $updatedSharedCatalog->getStoreId(),
            'Could not update shared catalog store id.'
        );
    }
}
