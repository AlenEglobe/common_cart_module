<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Service\V1;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\SharedCatalog\Api\SharedCatalogRepositoryInterface;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;

/**
 * Test for removing shared catalog.
 */
class DeleteSharedCatalogTest extends AbstractSharedCatalogTest
{
    private const SERVICE_READ_NAME = 'sharedCatalogSharedCatalogRepositoryV1';
    private const SERVICE_VERSION = 'V1';
    private const RESOURCE_PATH = '/V1/sharedCatalog/%d';

    /**
     * Test for removing shared catalog.
     *
     * @return void
     * @throws LocalizedException
     */
    #[
        DataFixture(SharedCatalog::class, as: 'shared_catalog'),
    ]
    public function testInvoke()
    {
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Requested Shared Catalog is not found');

        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');
        $serviceInfo = [
            'rest' => [
                'resourcePath' => sprintf(self::RESOURCE_PATH, $sharedCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_DELETE,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'deleteById',
            ],
        ];

        $respSharedCatalogData = $this->_webApiCall($serviceInfo, ['sharedCatalogId' => $sharedCatalog->getId()]);
        $this->assertTrue($respSharedCatalogData, 'Shared Catalog could not be deleted.');
        $this->objectManager->create(SharedCatalogRepositoryInterface::class)->get($sharedCatalog->getId());
    }
}
