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
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Test for CreatePost controller.
 *
 * @see \Magento\Company\Controller\Account\CreatePost
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 */
class CreateTest extends AbstractController
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
     * Try to open company create form when logged as company admin customer
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testCreate(): void
    {
        $adminCustomer = $this->customerRepository->get('john.doe@example.com');

        $this->session->loginById($adminCustomer->getId());
        try {
            $this->dispatch('company/account/create');

            $this->assertEquals('403', $this->getResponse()->getHttpResponseCode());
        } finally {
            $this->session->logout();
        }
    }
}
