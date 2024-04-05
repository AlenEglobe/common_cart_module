<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\SharedCatalog\Model\ResourceModel\ProductItem\Price;

use Magento\Framework\MessageQueue\UseCase\QueueTestCaseAbstract;
use Magento\AsynchronousOperations\Api\Data\OperationInterface;

/**
 * Tests for Shared Catalog price consumer.
 */
class ConsumerTest extends QueueTestCaseAbstract
{
    /**
     * {@inheritdoc}
     */
    protected $consumers = [
        'sharedCatalogUpdatePrice'
    ];

    /**
     * @var \Magento\AsynchronousOperations\Api\Data\OperationInterface
     */
    private $msgObject;

    /**
     * Test for price update scheduling.
     *
     * @magentoDataFixture Magento/SharedCatalog/_files/catalogs_for_search.php
     * @magentoDbIsolation enabled
     * @return void
     */
    public function testSchedulePriceUpdate()
    {
        /** @var  \Magento\Framework\DataObject\IdentityGeneratorInterface $identityService */
        $identityService =  $this->objectManager->create(
            \Magento\Framework\DataObject\IdentityGeneratorInterface::class
        );
        /** @var  \Magento\AsynchronousOperations\Api\Data\OperationInterface $msgObject */
        $this->msgObject = $this->objectManager->create(
            \Magento\AsynchronousOperations\Api\Data\OperationInterface::class
        );
        /** @var  \Magento\Framework\Bulk\BulkManagementInterface $bulkManagement */
        $bulkManagement = $this->objectManager->create(
            \Magento\Framework\Bulk\BulkManagementInterface::class
        );
        $bulkUuid = $identityService->generateId();
        /** @var  \Magento\SharedCatalog\Api\SharedCatalogManagementInterface $sharedCatalogManagement */
        $sharedCatalogManagement = $this->objectManager->create(
            \Magento\SharedCatalog\Api\SharedCatalogManagementInterface::class
        );
        $publicCatalog = $sharedCatalogManagement->getPublicCatalog();

        $dataToEncode = [
            'shared_catalog_id' => $publicCatalog->getId(),
            'entity_id' => 1,
            'entity_link' => 'http://example.com',
            'meta_information' => 'SKU: sku',
            'price' => 10
        ];
        $this->msgObject->setBulkUuid($bulkUuid);
        $this->msgObject->setTopicName('shared.catalog.product.price.updated');
        $this->msgObject->setSerializedData(json_encode($dataToEncode));
        $this->msgObject->setStatus(OperationInterface::STATUS_TYPE_OPEN);
        $this->msgObject->setTextFilePath($this->logFilePath);
        $bulkManagement->scheduleBulk(
            $bulkUuid,
            [$this->msgObject],
            __('Assign custom prices to selected products'),
            1
        );
        /** @var \Magento\Framework\Bulk\BulkStatusInterface $bulkStatus */
        $bulkStatus = $this->objectManager->create(\Magento\Framework\Bulk\BulkStatusInterface::class);
        /** @var \Magento\Framework\Bulk\BulkSummaryInterface $bulk */
        $bulks = $bulkStatus->getBulksByUser(1);
        $this->assertNotEmpty($bulks);
        $this->assertCount(1, $bulks);
    }
}
