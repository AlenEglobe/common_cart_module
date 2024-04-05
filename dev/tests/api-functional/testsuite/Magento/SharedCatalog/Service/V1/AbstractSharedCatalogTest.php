<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\SharedCatalog\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Abstract class for Shared Catalog tests.
 */
abstract class AbstractSharedCatalogTest extends WebapiAbstract
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\SharedCatalog\Api\SharedCatalogRepositoryInterface
     */
    protected $sharedCatalogRepository;

    /**
     * Set up.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->sharedCatalogRepository = $this->objectManager->get(
            \Magento\SharedCatalog\Api\SharedCatalogRepositoryInterface::class
        );
    }

    /**
     * Get public catalog.
     *
     * @return \Magento\SharedCatalog\Api\Data\SharedCatalogInterface
     */
    protected function getPublicCatalog()
    {
        /** @var \Magento\SharedCatalog\Api\SharedCatalogManagementInterface $sharedCatalogManagement */
        $sharedCatalogManagement = $this->objectManager->get(
            \Magento\SharedCatalog\Api\SharedCatalogManagementInterface::class
        );
        return $sharedCatalogManagement->getPublicCatalog();
    }

    /**
     * Get shared catalog.
     *
     * @return \Magento\SharedCatalog\Api\Data\SharedCatalogInterface
     */
    protected function getSharedCatalog()
    {
        /** @var \Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection $sharedCatalogCollection */
        $sharedCatalogCollection = $this->objectManager->get(
            \Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection::class
        );
        return $sharedCatalogCollection->getLastItem();
    }

    /**
     * Prepare assign items for Web API call.
     *
     * @param array $items
     * @param string $field [optional]
     * @return array
     */
    protected function prepareItems(array $items, $field = 'id')
    {
        $itemIds = [];
        foreach ($items as $item) {
            $itemIds[] = [$field => $item->getData($field == 'id' ? 'entity_id' : $field)];
        }

        return $itemIds;
    }

    /**
     * Compare catalogs.
     *
     * @param \Magento\SharedCatalog\Model\SharedCatalog $expectedCatalog
     * @param array $respCatalog
     * @return void
     */
    protected function compareCatalogs(
        \Magento\SharedCatalog\Model\SharedCatalog $expectedCatalog,
        array $respCatalog
    ) {
        $exclude = ['customer_group_id'];
        foreach ($respCatalog as $key => $value) {
            if ($key === 'id') {
                $key = 'entity_' . $key;
            }
            if (in_array($key, $exclude)) {
                continue;
            }
            $this->assertEquals($value, $expectedCatalog[$key], sprintf('Shared catalog has wrong "%s".', $key));
        }
    }
}
