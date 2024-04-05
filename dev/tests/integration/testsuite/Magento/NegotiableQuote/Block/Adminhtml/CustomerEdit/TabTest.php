<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\NegotiableQuote\Block\Adminhtml\CustomerEdit;

use Magento\Framework\Acl\Builder as AclBuilder;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 */
class TabTest extends AbstractBackendController
{
    /**
     * @inheritDoc
     *
     * @throws \Magento\Framework\Exception\AuthenticationException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->uri = 'backend/customer/index/edit';
        $this->resource = 'Magento_Customer::manage';
    }

    /**
     * Verify customer edit page contains quotes tab when admin has view quotes permission
     *
     * @magentoDataFixture Magento/Customer/_files/customer_sample.php
     */
    public function testEditAction()
    {
        $this->getRequest()->setParam('id', 1);
        $this->dispatch('backend/customer/index/edit');
        $body = $this->getResponse()->getBody();

        // verify
        $this->assertStringContainsString('"quotes_content":{"type":"tab"', $body);
    }

    /**
     * Verify customer edit page doesn't contain quotes tab when admin doesn't have view quotes permission
     *
     * @magentoDataFixture Magento/Customer/_files/customer_sample.php
     */
    public function testEditActionNoAccess()
    {
        $resource = 'Magento_NegotiableQuote::view_quotes';
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(AclBuilder::class)
            ->getAcl()
            ->deny(\Magento\TestFramework\Bootstrap::ADMIN_ROLE_ID, $resource);
        $this->getRequest()->setParam('id', 1);
        $this->dispatch('backend/customer/index/edit');
        $body = $this->getResponse()->getBody();

        // verify
        $this->assertStringNotContainsString('"quotes_content":{"type":"tab"', $body);
    }
}
