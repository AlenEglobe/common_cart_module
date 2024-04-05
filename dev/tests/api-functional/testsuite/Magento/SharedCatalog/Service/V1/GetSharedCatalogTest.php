<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\SharedCatalog\Service\V1;

use Magento\Customer\Test\Fixture\Customer;
use Magento\Company\Test\Fixture\CustomerGroup;
use Magento\Framework\Webapi\Rest\Request;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;

/**
 * Test for shared catalog getting.
 */
class GetSharedCatalogTest extends AbstractSharedCatalogTest
{
    private const SERVICE_READ_NAME = 'sharedCatalogSharedCatalogRepositoryV1';
    private const SERVICE_VERSION = 'V1';
    private const RESOURCE_PATH = '/V1/sharedCatalog/%d';

    /**
     * Test for shared catalog getting.
     *
     * @return void
     */
    #[
        Config('btob/website_configuration/sharedcatalog_active', 1),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(CustomerGroup::class, as: 'customer_group'),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        )
    ]
    public function testInvoke()
    {
        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog = $this->getSharedCatalog();
        $sharedCatalogId = $sharedCatalog->getId();

        $serviceInfo = [
            'rest' => [
                'resourcePath' => sprintf(self::RESOURCE_PATH, $sharedCatalogId),
                'httpMethod' => Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'get',
            ],
        ];

        $respSharedCatalogData = $this->_webApiCall($serviceInfo, ['sharedCatalogId' => $sharedCatalogId]);

        $this->compareCatalogs($sharedCatalog, $respSharedCatalogData);
    }
}
