<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\SharedCatalog\Service\V1;

use Magento\Customer\Test\Fixture\Customer;
use Magento\Company\Test\Fixture\CustomerGroup;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Webapi\Rest\Request;
use Magento\SharedCatalog\Api\SharedCatalogRepositoryInterface;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;

/**
 * Test for shared catalog, getting list of shared catalogs and basic properties for each catalog.
 */
class GetListSharedCatalogTest extends AbstractSharedCatalogTest
{
    private const SERVICE_READ_NAME = 'sharedCatalogSharedCatalogRepositoryV1';
    private const SERVICE_VERSION = 'V1';
    private const RESOURCE_PATH = '/V1/sharedCatalog';

    /**
     * Test for shared catalog, getting list of shared catalogs and basic properties for each catalog.
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
        /** @var $searchCriteriaBuilder  SearchCriteriaBuilder */
        $searchCriteriaBuilder = $this->objectManager->create(
            SearchCriteriaBuilder::class
        );
        $searchCriteriaBuilder->setPageSize(2);
        $searchData = $searchCriteriaBuilder->create();

        $requestData = ['searchCriteria' => $searchData->__toArray()];
        /** @var SharedCatalogRepositoryInterface $sharedCatalogRepository */
        $sharedCatalogRepository = $this->objectManager
            ->get(SharedCatalogRepositoryInterface::class);
        $expectedListSharedCatalog = $sharedCatalogRepository->getList($searchData)->getItems();

        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH . '?' . http_build_query($requestData),
                'httpMethod' => Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'getList',
            ],
        ];
        $searchResults = $this->_webApiCall($serviceInfo, $requestData);

        $searchResultsCatalogs = $searchResults['items'];
        foreach ($searchResultsCatalogs as $catalog) {
            $this->compareCatalogs($expectedListSharedCatalog[$catalog['id']], $catalog);
        }
    }
}
