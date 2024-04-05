<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Company\Controller\Adminhtml\System;

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
    protected $resource = 'Magento_Company::config_company';

    /**
     * The uri at which to access the controller
     *
     * @var string
     */
    protected $uri = 'backend/admin/system_config/edit/section/company';

    /**
     * Expected no access response
     *
     * @var int
     */
    protected $expectedNoAccessResponseCode = 302;

    /**
     * Test backoffice admin can see Company Configuration button when Magento_Company::config_company is enabled for
     * their role
     *
     * Given a backoffice admin with Magento_Company::config_company permission allowed
     * When the admin visits the store configuration page in the backoffice
     * Then the Company Configuration button is present in the response body
     */
    public function testAdminCanSeeCompanyConfigurationButtonWhenCompanyConfigCompanyACLEnabled()
    {
        $this->dispatch('backend/admin/system_config/edit');
        $this->assertMatchesRegularExpression(
            '/class="admin__page-nav-link item-nav">\s*<span>Company Configuration<\/span>/',
            $this->getResponse()->getBody()
        );
    }

    /**
     * Test backoffice admin cannot see Company Configuration button when Magento_Company::config_company is disabled
     * for their role
     *
     * Given a backoffice admin with Magento_Company::config_company permission denied
     * When the admin visits the store configuration page in the backoffice
     * Then the Company Configuration button is absent in the response body
     */
    public function testAdminCanSeeCompanyConfigurationButtonWhenCompanyConfigCompanyACLDisabled()
    {
        $this->_objectManager->get(\Magento\Framework\Acl\Builder::class)
            ->getAcl()
            ->deny(Bootstrap::ADMIN_ROLE_ID, $this->resource);
        $this->dispatch('backend/admin/system_config/edit');
        $this->assertDoesNotMatchRegularExpression(
            '/class="admin__page-nav-link item-nav">\s+<span>Company Configuration<\/span>/',
            $this->getResponse()->getBody()
        );
    }

    /**
     * Test backoffice admin does not have access to /admin/system_config/edit/section/company when denied
     * Magento_Company::config_company permission
     *
     * Given a backoffice admin with Magento_Company::config_company permission denied
     * When the admin visits the company configuration page in the backoffice (Customers > Company Configuration)
     * Then the HTTP response code is 302
     * And the admin is redirected back to the main store configuration page
     */
    public function testAdminCannotAccessCompanyConfigurationWhenCompanyConfigCompanyACLDisabled()
    {
        parent::testAclNoAccess();
        $this->assertRedirect($this->stringContains('admin/system_config/index'));
    }

    /**
     * Test backoffice admin has access to /admin/system_config/edit/section/company when granted
     * Magento_Company::config_company permission
     *
     * Given a backoffice admin with Magento_Company::config_company permission allowed
     * When the admin visits the company configuration page in the backoffice (Customers > Company Configuration)
     * Then the HTTP response code is 200
     */
    public function testAdminCanAccessCompanyConfigurationWhenCompanyConfigCompanyACLEnabled()
    {
        return parent::testAclHasAccess();
    }
}
