<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\SharedCatalog\Service\V1;

/**
 * Test for shared catalog creation.
 */
class CreateSharedCatalogTest extends AbstractSharedCatalogTest
{
    const SERVICE_READ_NAME = 'sharedCatalogSharedCatalogRepositoryV1';
    const SERVICE_VERSION = 'V1';
    const RESOURCE_PATH = '/V1/sharedCatalog';

    /**
     * @var \Magento\SharedCatalog\Api\Data\SharedCatalogInterface|null
     */
    private $currentSharedCatalog;

    /**
     * @var \Magento\SharedCatalog\Api\Data\SharedCatalogInterface|null
     */
    private $publicSharedCatalog;

    /**
     * Clear temporary data.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->currentSharedCatalog) {
            $customerGroupManagement = $this->objectManager
                ->get(\Magento\SharedCatalog\Model\CustomerGroupManagement::class);
            $sharedCatalog = $this->currentSharedCatalog;
            $this->sharedCatalogRepository->delete($this->currentSharedCatalog);
            $this->currentSharedCatalog = null;
            if ($sharedCatalog->getCustomerGroupId() > 3) {
                $customerGroupManagement->deleteCustomerGroupById($sharedCatalog);
            }
        }
        if ($this->publicSharedCatalog) {
            $this->publicSharedCatalog->setType(1);
            $this->sharedCatalogRepository->save($this->publicSharedCatalog);
        }
    }

    /**
     * Custom shared catalog creation test.
     *
     * @return void
     */
    public function testCreateCustomSharedCatalog()
    {
        $defaultData = $this->getCustomSharedCatalogData();
        $sharedCatalogId = $this->createSharedCatalogWebApiCall($defaultData);
        $this->assertNotEmpty($sharedCatalogId);
        $sharedCatalog = $this->sharedCatalogRepository->get($sharedCatalogId);
        $this->compareCatalogs($sharedCatalog, $defaultData);
    }

    /**
     * Public shared catalog creation test.
     *
     * @return void
     */
    public function testCreatePublicSharedCatalog()
    {
        $this->publicSharedCatalog = $this->getPublicCatalog();
        $defaultData = $this->getPublicSharedCatalogData();
        $sharedCatalogId = $this->createSharedCatalogWebApiCall($defaultData);
        $this->assertNotEmpty($sharedCatalogId);
        $sharedCatalog = $this->sharedCatalogRepository->get($sharedCatalogId);
        $this->compareCatalogs($sharedCatalog, $defaultData);
    }

    /**
     * Perform Web API call to the system under test.
     *
     * @param array $defaultData
     * @return string
     */
    private function createSharedCatalogWebApiCall(array $defaultData)
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Save',
            ],
        ];
        $sharedCatalogId = $this->_webApiCall(
            $serviceInfo,
            ['sharedCatalog' => $defaultData]
        );
        return $sharedCatalogId;
    }

    /**
     * Get shared catalog test data.
     *
     * @return array
     */
    private function getCustomSharedCatalogData()
    {
        return [
            'name' => 'test catalog' . time(),
            'description' => 'test catalog description',
            'type' => 0,
            'created_by' => null,
            'store_id' => 1,
            'tax_class_id' => 3,
            'created_at' => date('Y-m-d H:i:s', time()),
            'customer_group_id' => null
        ];
    }

    /**
     * Get public catalog test data.
     *
     * @return array
     */
    private function getPublicSharedCatalogData()
    {
        return [
            'name' => 'test public catalog' . time(),
            'description' => 'test catalog description',
            'type' => 1,
            'created_by' => null,
            'store_id' => 1,
            'tax_class_id' => 3,
            'created_at' => date('Y-m-d H:i:s', time()),
            'customer_group_id' => null
        ];
    }
}
