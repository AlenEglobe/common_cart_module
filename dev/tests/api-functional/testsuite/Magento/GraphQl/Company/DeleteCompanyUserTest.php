<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\GraphQl\Company;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\Framework\ObjectManagerInterface;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Create company user test
 */
class DeleteCompanyUserTest extends GraphQlAbstract
{
    /**
     * @var GetCustomerAuthenticationHeader
     */
    private $getCustomerAuthenticationHeader;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Uid
     */
    private $idEncoder;

    /**
     * Setup
     */
    public function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->getCustomerAuthenticationHeader = $this->objectManager->get(GetCustomerAuthenticationHeader::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->idEncoder = $this->objectManager->get(Uid::class);
    }

    /**
     * Test unauthorized access
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testUnauthorizedCustomer()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Customer is not a company user.');

        $userId = $this->idEncoder->encode((string)$this->getUserIdByEmail('veronica.costello@example.com'));

        $mutation = <<<MUTATION
mutation {
  deleteCompanyUser(
    id: "{$userId}"
  ) {
    success
  }
}
MUTATION;

        $this->graphQlMutation($mutation);
    }

    /**
     * Test company user deleting
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testDelete()
    {
        $userId = $this->idEncoder->encode((string)$this->getUserIdByEmail('veronica.costello@example.com'));

        $mutation = <<<MUTATION
mutation {
  deleteCompanyUser(
    id: "{$userId}"
  ) {
    success
  }
}
MUTATION;

        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('john.doe@example.com', 'password')
        );

        self::assertEquals(true, $response['deleteCompanyUser']['success']);
    }

    /**
     * Test unauthorized access
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testDeleteWithoutPermissions()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Customer is not a company user.');

        $userId = $this->idEncoder->encode((string)$this->getUserIdByEmail('veronica.costello@example.com'));

        $mutation = <<<MUTATION
mutation {
  deleteCompanyUser(
    id: "{$userId}"
  ) {
    success
  }
}
MUTATION;

        $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('test@example.com', 'password')
        );
    }

    /**
     * Test company user deleting admin
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testDeleteMyself()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You cannot delete yourself.');

        $userId = $this->idEncoder->encode((string)$this->getUserIdByEmail('john.doe@example.com'));

        $mutation = <<<MUTATION
mutation {
  deleteCompanyUser(
    id: "{$userId}"
  ) {
    success
  }
}
MUTATION;

        $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('john.doe@example.com', 'password')
        );
    }

    /**
     * Test deleting not company user
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testDeleteWrongUser()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You do not have authorization to perform this action.');

        $userId = $this->idEncoder->encode((string)$this->getUserIdByEmail('test@example.com'));

        $mutation = <<<MUTATION
mutation {
  deleteCompanyUser(
    id: "{$userId}"
  ) {
    success
  }
}
MUTATION;

        $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('john.doe@example.com', 'password')
        );
    }

    /**
     * Test deleting not company user
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testDeleteUserWithNotEncodedId()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Value of uid "1000000" is incorrect.');

        $mutation = <<<MUTATION
mutation {
  deleteCompanyUser(
    id: 1000000
  ) {
    success
  }
}
MUTATION;

        $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('john.doe@example.com', 'password')
        );
    }

    /**
     * Get user id by email
     *
     * @param string $email
     * @return int|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getUserIdByEmail(string $email)
    {
        return $this->customerRepository->get($email)->getId();
    }
}
