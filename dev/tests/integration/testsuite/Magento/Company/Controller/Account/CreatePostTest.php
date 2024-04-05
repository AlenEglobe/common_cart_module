<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Company\Controller\Account;

use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Message\MessageInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Test for CreatePost controller.
 *
 * @see \Magento\Company\Controller\Account\CreatePost
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 */
class CreatePostTest extends AbstractController
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
     * Test storefront company creation as a guest
     *
     * Given a storefront guest is on the company registration form page
     * When the guest submits the form with valid data
     * Then a success message is saved in the guest's session
     *
     * @return void
     */
    public function testStorefrontCreateCompanyAsGuest(): void
    {
        $data = $this->getPostData();
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST)->setPostValue($data);
        $this->dispatch('company/account/createPost');

        $this->assertSessionMessages(
            $this->equalTo(['Thank you! We&#039;re reviewing your request and will contact you soon']),
            MessageInterface::TYPE_SUCCESS
        );
    }

    /**
     * Test storefront company creation as a guest with form data that is missing an email field
     *
     * Given a storefront guest is on the company registration form page
     * When the guest submits the form without a company email
     * Then an error message is saved in the guest's session
     *
     * @return void
     */
    public function testCreatePostWithoutRequiredCompanyEmailAttribute(): void
    {
        $data = $this->getPostData();
        unset($data['company']['company_email']);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST)->setPostValue($data);
        $this->dispatch('company/account/createPost');

        $this->assertSessionMessages(
            $this->equalTo(['&quot;company_email&quot; is required. Enter and try again.']),
            MessageInterface::TYPE_ERROR
        );
    }

    /**
     * Test storefront company creation as a guest with form data that is missing a customer lastname field
     *
     * Given a storefront guest is on the company registration form page
     * When the guest submits the form without a customer lastname
     * Then an error message is saved in the guest's session
     *
     * @return void
     */
    public function testCreatePostWithoutRequiredCustomerLastNameAttribute(): void
    {
        $data = $this->getPostData();
        unset($data['customer']['lastname']);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST)->setPostValue($data);
        $this->dispatch('company/account/createPost');

        $this->assertSessionMessages(
            $this->equalTo(['&quot;Last Name&quot; is a required value.']),
            MessageInterface::TYPE_ERROR
        );
    }

    /**
     * Try to post company data in order to create company as logged company admin customer
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testCreatePostByCompanyCustomer(): void
    {
        $adminCustomer = $this->customerRepository->get('john.doe@example.com');

        $this->session->loginById($adminCustomer->getId());
        try {
            $data = $this->getPostData();
            $this->getRequest()->setMethod(HttpRequest::METHOD_POST)->setPostValue($data);
            $this->dispatch('company/account/createPost');

            $this->assertEquals('403', $this->getResponse()->getHttpResponseCode());
        } finally {
            $this->session->logout();
        }
    }

    /**
     * Return test data.
     *
     * @return array
     */
    private function getPostData(): array
    {
        return [
            'company' => [
                'company_name' => 'TSG',
                'legal_name' => 'TSG Company',
                'company_email' => 'tsg@example.com',
                'country_id' => 'VA',
                'region' => 'Kyiv region',
                'city' => 'Kyiv',
                'street' => [
                    0 => 'Somewhere',
                ],
                'postcode' => '01001',
                'telephone' => '+1255555555',
                'job_title' => 'Owner',
            ],
            'customer' => [
                'firstname' => 'John',
                'lastname' => 'Doe',
                'email' => 'john.doetsg@example.com',
            ],
        ];
    }
}
