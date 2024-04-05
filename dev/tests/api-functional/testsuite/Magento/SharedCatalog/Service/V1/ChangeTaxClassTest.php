<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Service\V1;

use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\GroupRegistry;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\Tax\Api\Data\TaxClassInterface;
use Magento\Tax\Test\Fixture\CustomerTaxClass;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Covering implementation \Magento\SharedCatalog\Model\CustomerGroupManagement::updateCustomerGroup method
 *
 * Shared catalog customer group tax class should be updated during save of shared catalog
 */
class ChangeTaxClassTest extends WebapiAbstract
{
    private const RESOURCE_PATH = '/V1/sharedCatalog';
    private const SERVICE_READ_NAME = 'sharedCatalogSharedCatalogRepositoryV1';
    private const SERVICE_VERSION = 'V1';

    #[
        DataFixture(SharedCatalog::class, as: 'shared_catalog'),
        DataFixture(CustomerTaxClass::class, as: 'customer_tax_class'),
    ]
    public function testInvoke()
    {
        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');
        /** @var TaxClassInterface $customerTaxClass */
        $customerTaxClass = DataFixtureStorageManager::getStorage()->get('customer_tax_class');
        $initialTaxClassId = $sharedCatalog->getTaxClassId();
        $sharedCatalogId = $sharedCatalog->getId();
        $anotherTaxClassId = $customerTaxClass->getClassId();
        $customerGroupId = $sharedCatalog->getCustomerGroupId();

        $this->assertNotEquals(
            $initialTaxClassId,
            $anotherTaxClassId,
            'Different tax class ids should be used for the test'
        );

        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH . '/' . $sharedCatalogId,
                'httpMethod' => Request::HTTP_METHOD_PUT,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Save',
            ],
        ];

        $this->_webApiCall(
            $serviceInfo,
            [
                'sharedCatalog' => [
                    'id' => $sharedCatalogId,
                    'name' => $sharedCatalog->getName(),
                    'description' => $sharedCatalog->getDescription(),
                    'customerGroupId' => $sharedCatalog->getCustomerGroupId(),
                    'type' => $sharedCatalog->getType(),
                    'createdAt' => $sharedCatalog->getCreatedAt(),
                    'createdBy' => $sharedCatalog->getCreatedBy(),
                    'storeId' => $sharedCatalog->getStoreId(),
                    'tax_class_id' => $anotherTaxClassId
                ]
            ]
        );

        $this->assertEquals(
            $anotherTaxClassId,
            $this->getGroup($customerGroupId)->getTaxClassId(),
            'Tax class id was not updated for the customer group associated with the shared catalog.'
        );

        $this->_webApiCall(
            $serviceInfo,
            [
                'sharedCatalog' => [
                    'id' => $sharedCatalogId,
                    'name' => $sharedCatalog->getName(),
                    'description' => $sharedCatalog->getDescription(),
                    'customerGroupId' => $sharedCatalog->getCustomerGroupId(),
                    'type' => $sharedCatalog->getType(),
                    'createdAt' => $sharedCatalog->getCreatedAt(),
                    'createdBy' => $sharedCatalog->getCreatedBy(),
                    'storeId' => $sharedCatalog->getStoreId(),
                    'tax_class_id' => $initialTaxClassId
                ]
            ]
        );

        $this->assertEquals(
            $initialTaxClassId,
            $this->getGroup($customerGroupId)->getTaxClassId(),
            'Tax class id was not updated for the customer group associated with the shared catalog.'
        );
    }

    /**
     * Retrieve customer group bypassing registry cache
     *
     * @param int $id
     * @return GroupInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getGroup(int $id): GroupInterface
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(GroupRegistry::class)->remove($id);
        return $objectManager->get(GroupRepositoryInterface::class)->getById($id);
    }
}
