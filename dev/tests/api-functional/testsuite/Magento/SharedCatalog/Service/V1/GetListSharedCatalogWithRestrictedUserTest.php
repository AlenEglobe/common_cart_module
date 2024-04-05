<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Service\V1;

use Magento\Authorization\Test\Fixture\Role as RoleFixture;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\SharedCatalog\Service\V1\AbstractSharedCatalogTest;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Integration\Api\AdminTokenServiceInterface;
use Magento\SharedCatalog\Api\SharedCatalogRepositoryInterface;
use Magento\Authorization\Model\RoleFactory;
use Magento\Authorization\Model\RulesFactory;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog as SharedCatalogFixture;
use Magento\TestFramework\Bootstrap;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\User\Test\Fixture\User as UserFixture;

/**
 * Test for shared catalog, getting list of shared catalogs and basic properties for each catalog.
 */
class GetListSharedCatalogWithRestrictedUserTest extends AbstractSharedCatalogTest
{
    private const SERVICE_READ_NAME = 'sharedCatalogSharedCatalogRepositoryV1';
    private const SERVICE_VERSION = 'V1';
    private const RESOURCE_PATH = '/V1/sharedCatalog';

    /**
     * @var AdminTokenServiceInterface|null
     */
    private $adminTokens;

    /**
     * Test for shared catalog, getting list of shared catalogs and basic properties for each catalog.
     *
     * @return void
     * @throws AuthenticationException
     * @throws InputException
     * @throws LocalizedException
     */
    #[
        DataFixture(RoleFixture::class, as: 'restrictedRole'),
        DataFixture(UserFixture::class, ['role_id' => '$restrictedRole.id$'], 'restrictedUser'),
        DataFixture(SharedCatalogFixture::class),
    ]
    public function testInvoke()
    {
        $fixtures = DataFixtureStorageManager::getStorage();
        $restrictedUser = $fixtures->get('restrictedUser');

        $this->adminTokens = $this->objectManager->get(AdminTokenServiceInterface::class);

        //Using the admin user with restricted role.
        $accessToken = $this->adminTokens->createAdminAccessToken(
            $restrictedUser->getData('username'),
            Bootstrap::ADMIN_PASSWORD
        );

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
                'token' => $accessToken
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'getList',
                'token' => $accessToken
            ],
        ];
        $searchResults = $this->_webApiCall($serviceInfo, $requestData);

        $searchResultsCatalogs = $searchResults['items'];
        foreach ($searchResultsCatalogs as $catalog) {
            $this->compareCatalogs($expectedListSharedCatalog[$catalog['id']], $catalog);
        }
    }
}
