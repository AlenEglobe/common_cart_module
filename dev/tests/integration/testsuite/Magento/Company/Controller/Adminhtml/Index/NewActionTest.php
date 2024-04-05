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
class NewActionTest extends AbstractBackendController
{
    /**
     * @inheritDoc
     */
    protected $resource = NewAction::ADMIN_RESOURCE;

    /**
     * @inheritDoc
     */
    protected $uri = 'backend/company/index/new';

    /**
     * @inheritDoc
     */
    protected $httpMethod = HttpRequest::METHOD_GET;

    /**
     * Test backoffice admin has access to /company/index/new when granted Magento_Company::add permission
     *
     * Given a backoffice admin
     * When Magento_Company::add ACL permission is allowed for the admin's role
     * And a GET request is made to visit the add new company page in backoffice
     * Then the HTTP response code is 200
     */
    public function testAdminCanAccessCreateCompanyPageWhenCompanyAddACLEnabled()
    {
        return parent::testAclHasAccess();
    }

    /**
     * Test backoffice admin does not have access to /company/index/new when denied Magento_Company::add permission
     *
     * Given a backoffice admin
     * When Magento_Company::add ACL permission is denied for the admin's role
     * And a GET request is made to visit the add new company page in backoffice
     * Then the HTTP response code is 403
     */
    public function testAdminCannotAccessCreateCompanyPageWhenCompanyAddACLDisabled()
    {
        return parent::testAclNoAccess();
    }
}
