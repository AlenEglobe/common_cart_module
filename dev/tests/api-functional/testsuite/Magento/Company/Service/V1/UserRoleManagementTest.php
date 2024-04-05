<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Company\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Test UserRoleManagement operations.
 */
class UserRoleManagementTest extends WebapiAbstract
{
    const SERVICE_READ_NAME = 'companyAclV1';

    const SERVICE_VERSION = 'V1';

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \Magento\Company\Api\RoleManagementInterface
     */
    private $roleManagement;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var \Magento\Company\Api\CompanyManagementInterface
     */
    private $companyManagement;

    /**
     * @var \Magento\Company\Api\AclInterface
     */
    private $userRoleManagement;

    /**
     * @var \Magento\Framework\Reflection\DataObjectProcessor
     */
    private $dataObjectProcessor;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->roleManagement = $this->objectManager->get(
            \Magento\Company\Api\RoleManagementInterface::class
        );
        $this->customerRepository = $this->objectManager->get(
            \Magento\Customer\Api\CustomerRepositoryInterface::class
        );
        $this->companyManagement = $this->objectManager->get(
            \Magento\Company\Api\CompanyManagementInterface::class
        );
        $this->userRoleManagement = $this->objectManager->get(
            \Magento\Company\Api\AclInterface::class
        );
        $this->dataObjectProcessor = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Framework\Reflection\DataObjectProcessor::class
        );
    }

    /**
     * Test assignRoles via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer.php
     */
    public function testAssignRoles()
    {
        $adminCompany = $this->customerRepository->get('email@companyquote.com');
        $customer = $this->customerRepository->get('customercompany22@example.com');
        $company = $this->companyManagement->getByCustomerId($adminCompany->getId());
        $roleFactory = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create(\Magento\Company\Api\Data\RoleInterfaceFactory::class);
        /** @var \Magento\Company\Api\RoleRepositoryInterface $roleRepository */
        $roleRepository = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create(\Magento\Company\Api\RoleRepositoryInterface::class);
        $role = $roleFactory->create();
        $role->setRoleName('New Role');
        $role->setCompanyId($company->getId());
        $roleRepository->save($role);
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/company/assignRoles/',
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'assignRoles',
            ],
        ];
        $requestData = ['userId' => $customer->getId(), 'roles' => $this->prepareRolesData([$role])];
        $this->_webApiCall($serviceInfo, $requestData);
        $usersRole = $this->userRoleManagement->getUsersByRoleId($role->getId());
        $user = $usersRole[0];
        $this->assertEquals($user->getId(), $customer->getId());
    }

    /**
     * Test getUsersByRoleId via WebAPI.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer.php
     * @return void
     */
    public function testGetUsersByRoleId()
    {
        $adminCompany = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($adminCompany->getId());
        $role = $this->roleManagement->getCompanyDefaultRole($company->getId());

        $serviceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/company/role/%d/users', $role->getId()),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'getUsersByRoleId',
            ],
        ];
        $respUserIds = [];
        $respData = $this->_webApiCall($serviceInfo, ['roleId' => $role->getId()]);
        foreach ($respData as $user) {
            $respUserIds[] = $user['id'];
        }
        $expectedUserIds = [];
        $usersRole = $this->userRoleManagement->getUsersByRoleId($role->getId());
        foreach ($usersRole as $user) {
            $expectedUserIds[] = $user->getId();
        }

        $this->assertEquals($expectedUserIds, $respUserIds);
    }

    /**
     * Prepare roles for Web API call.
     *
     * @param \Magento\Company\Api\Data\RoleInterface[] $roles
     * @return array
     */
    private function prepareRolesData(array $roles)
    {
        $rolesParam = [];
        foreach ($roles as $item) {
            $role = [
                'id' => $item->getId(),
                'permissions' => []
            ];
            $rolesParam[] = $role;
        }
        return $rolesParam;
    }
}
