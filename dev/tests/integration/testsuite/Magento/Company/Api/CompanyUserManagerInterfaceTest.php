<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\Company\Api;

use Magento\Company\Api\Data\CompanyCustomerInterface;
use Magento\Company\Api\Data\CompanyCustomerInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use PHPUnit\Framework\TestCase;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Test the service.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CompanyUserManagerInterfaceTest extends TestCase
{
    /**
     * @var CompanyUserManagerInterface
     */
    private $manager;

    /**
     * @var CompanyCustomerInterfaceFactory
     */
    private $companyAttributesFactory;

    /**
     * @var TransportBuilderMock
     */
    private $transportBuilderMock;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepo;

    /**
     * @var SearchCriteriaBuilder
     */
    private $criteriaBuilder;

    /**
     * @var AclInterface
     */
    private $acl;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepo;

    /**
     * @var RoleRepositoryInterface
     */
    private $roleRepo;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->manager = $objectManager->get(CompanyUserManagerInterface::class);
        $this->companyAttributesFactory = $objectManager->get(CompanyCustomerInterfaceFactory::class);
        $this->transportBuilderMock = $objectManager->get(TransportBuilderMock::class);
        $this->customerRepo = $objectManager->get(CustomerRepositoryInterface::class);
        $this->criteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->acl = $objectManager->get(AclInterface::class);
        $this->companyRepo = $objectManager->get(CompanyRepositoryInterface::class);
        $this->roleRepo = $objectManager->get(RoleRepositoryInterface::class);
    }

    /**
     * Test that a customer is created and assigned to company in a custom role after accepting an email invitation
     *
     * Given an approved company and a custom role within that company
     * When an email invitation is sent to a potential customer using that custom role
     * And the customer accepts the invitation using the code in the email body
     * Then the customer is created and assigned to that company and the custom role within that company
     *
     * @magentoDataFixture Magento/Company/_files/company_with_custom_role.php
     * @magentoDataFixture Magento/Company/_files/customer.php
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     */
    public function testCustomerCreatedAndAssignedToCustomRoleAfterAcceptingInvitation()
    {
        $companies = $this->companyRepo->getList(
            $this->criteriaBuilder->addFilter('company_name', 'Company with role')->create()
        )->getItems();
        $company = array_pop($companies);
        $customer = $this->customerRepo->get('company_related@company.com');
        $roles = $this->roleRepo->getList(
            $this->criteriaBuilder->addFilter('role_name', 'custom company role')->create()
        )->getItems();
        $role = array_pop($roles);

        /** @var CompanyCustomerInterface $attributes */
        $attributes = $this->companyAttributesFactory->create();
        $attributes->setCompanyId($company->getId());
        $attributes->setCustomerId($customer->getId());
        $attributes->setJobTitle('job title');
        $attributes->setStatus(CompanyCustomerInterface::STATUS_ACTIVE);
        $attributes->setTelephone('11111112');

        $this->manager->sendInvitation($attributes, (string)$role->getId());
        $message = $this->transportBuilderMock->getSentMessage();
        $this->assertNotEmpty($message);
        preg_match('/code=(.+?)\&/i', $message->getBody()->getParts()[0]->getRawContent(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $this->assertNotEmpty($matches[1]);
        $code = urldecode($matches[1]);

        $this->manager->acceptInvitation($code, $attributes, (string)$role->getId());

        //Checking that customer is actually assigned and has the role.
        $foundCustomers = $this->customerRepo->getList(
            $this->criteriaBuilder
                ->addFilter('entity_id', $customer->getId())
                ->create()
        )->getItems();
        $foundCustomer = array_pop($foundCustomers);
        /** @var CompanyCustomerInterface $foundAttributes */
        $foundAttributes = $foundCustomer->getExtensionAttributes()->getCompanyAttributes();
        $this->assertNotEmpty($foundAttributes);
        $this->assertEquals($company->getId(), $foundAttributes->getCompanyId());
        $this->assertEquals($attributes->getJobTitle(), $foundAttributes->getJobTitle());
        $this->assertEquals($attributes->getTelephone(), $foundAttributes->getTelephone());
        $this->assertEquals($attributes->getStatus(), $foundAttributes->getStatus());
        $foundRoles = $this->acl->getRolesByUserId($foundCustomer->getId());
        $foundRole = false;
        foreach ($foundRoles as $someRole) {
            if ($someRole->getId() == $role->getId()) {
                $foundRole = true;
            }
        }
        $this->assertTrue($foundRole);
    }
}
