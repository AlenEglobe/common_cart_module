<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Company\Controller\Adminhtml\Index;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * @magentoAppArea adminhtml
 */
class MassEnableTest extends AbstractBackendController
{
    /**
     * @inheritDoc
     */
    protected $resource = MassEnable::ADMIN_RESOURCE;

    /**
     * @inheritDoc
     */
    protected $uri = 'backend/company/index/massEnable';

    /**
     * @inheritDoc
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * Test backoffice admin has access to /company/index/massEnable when granted Magento_Company::manage permission
     *
     * Given a backoffice admin
     * When Magento_Company::manage ACL permission is allowed for the admin's role
     * And a POST request is made to set multiple companies' statuses to APPROVED directly from the company listing page
     * Then the HTTP response code is 200
     */
    public function testAdminCanBulkEditCompanyStatusesWhenCompanyManageACLEnabled()
    {
        return parent::testAclHasAccess();
    }

    /**
     * Test backoffice admin does not have access to /company/index/massEnable when denied Magento_Company::manage
     * permission
     *
     * Given a backoffice admin
     * When Magento_Company::manage ACL permission is denied for the admin's role
     * And a POST request is made to set multiple companies' statuses to APPROVED directly from the company listing page
     * Then the HTTP response code is 403
     */
    public function testAdminCannotBulkEditCompanyStatusesWhenCompanyManageACLDisabled()
    {
        return parent::testAclNoAccess();
    }
}
