<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\NegotiableQuote\Controller\Adminhtml\System;

use Magento\TestFramework\Bootstrap;

/**
 * @magentoAppArea adminhtml
 */
class ConfigTest extends \Magento\TestFramework\TestCase\AbstractBackendController
{
    /**
     * The resource used to authorize action
     *
     * @var string
     */
    protected $resource = 'Magento_NegotiableQuote::config_quotes';

    /**
     * The uri at which to access the controller
     *
     * @var string
     */
    protected $uri = 'backend/admin/system_config/edit/section/quote';

    /**
     * Expected no access response
     *
     * @var int
     */
    protected $expectedNoAccessResponseCode = 302;

    public function testEditAction()
    {
        $this->dispatch('backend/admin/system_config/edit');
        $this->assertMatchesRegularExpression(
            '/class="admin__page-nav-link item-nav">\s+<span>Quotes<\/span>/',
            $this->getResponse()->getBody()
        );
    }

    public function testEditActionNoAccess()
    {
        $this->_objectManager->get(\Magento\Framework\Acl\Builder::class)
            ->getAcl()
            ->deny(Bootstrap::ADMIN_ROLE_ID, $this->resource);
        $this->dispatch('backend/admin/system_config/edit');
        $this->assertDoesNotMatchRegularExpression(
            '/class="admin__page-nav-link item-nav">\s*<span>Quotes<\/span>/',
            $this->getResponse()->getBody()
        );
    }

    /**
     * Test ACL actually denying access.
     */
    public function testAclNoAccess()
    {
        parent::testAclNoAccess();
        $this->assertRedirect($this->stringContains('admin/system_config/index'));
    }
}
