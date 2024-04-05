<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Company\Controller\Customer;

use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Message\MessageInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 */
class DeleteTest extends AbstractController
{
    private const XML_PATH_COMPANY_ACTIVE = 'btob/website_configuration/company_active';

    /**
     * @var Session
     */
    private $session;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->_objectManager->get(MutableScopeConfigInterface::class);
        $config->setValue(self::XML_PATH_COMPANY_ACTIVE, 1, ScopeInterface::SCOPE_WEBSITE);
        $this->session = $this->_objectManager->get(Session::class);
        $this->customerRepository = $this->_objectManager->get(CustomerRepository::class);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $config = $this->_objectManager->get(MutableScopeConfigInterface::class);
        $config->setValue(self::XML_PATH_COMPANY_ACTIVE, 0, ScopeInterface::SCOPE_WEBSITE);
        $this->session = null;
        $this->customerRepository = null;
        parent::tearDown();
    }

    /**
     * Test company admin can inactivate a company user within their company
     *
     * Given a company with a company admin and a company user belonging to the same company
     * When the company admin sends a request to delete (inactivate) the company customer
     * Then the company customer is successfully inactivated
     *
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     */
    public function testCompanyAdminCanInactivateCompanyUserInTheirCompany()
    {
        $adminCustomer = $this->customerRepository->get('john.doe@example.com');

        $this->session->loginById($adminCustomer->getId());
        try {
            $customerToDeactivate = $this->customerRepository->get('veronica.costello@example.com');

            $dataToPost = [
                'customer_id' => $customerToDeactivate->getId(),
            ];

            $this->getRequest()->setPostValue($dataToPost);
            $this->getRequest()->setMethod(HttpRequest::METHOD_POST);

            $this->dispatch('company/customer/delete');

            $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());

            $expectedMessage = (string)__(
                "%1&#039;s account has been set to Inactive.",
                $customerToDeactivate->getFirstname() . ' ' . $customerToDeactivate->getLastname()
            );

            $this->assertSessionMessages(
                $this->equalTo([$expectedMessage]),
                MessageInterface::TYPE_SUCCESS
            );
        } finally {
            $this->session->logout();
        }
    }
}
