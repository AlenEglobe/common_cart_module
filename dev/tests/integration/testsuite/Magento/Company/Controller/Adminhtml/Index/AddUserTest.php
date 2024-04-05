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
class AddUserTest extends AbstractBackendController
{
    /**
     * @inheritDoc
     */
    protected $resource = AddUser::ADMIN_RESOURCE;

    /**
     * @inheritDoc
     */
    protected $uri = 'backend/company/index/addUser';

    /**
     * @inheritDoc
     */
    protected $httpMethod = HttpRequest::METHOD_GET;

    /**
     * Test backoffice admin has access to /company/index/addUser when granted Magento_Backend::admin permission
     *
     * Given a backoffice admin
     * When Magento_Backend::admin ACL permission is allowed for the admin's role
     * And a GET request is made to check the existence of a customer when changing company admin email or website
     * Then the HTTP response code is 200
     */
    public function testAdminCanAccessCustomerExistenceWhenBackendAdminACLEnabled()
    {
        return parent::testAclHasAccess();
    }

    /**
     * Test backoffice admin does not have access to /company/index/addUser when denied Magento_Backend::admin
     * permission
     *
     * Given a backoffice admin
     * When Magento_Backend::admin ACL permission is denied for the admin's role
     * And a GET request is made to check the existence of a customer when changing company admin email or website
     * Then the HTTP response code is 403
     */
    public function testAdminCanotAccessCustomerExistenceWhenBackendAdminACLDisabled()
    {
        return parent::testAclNoAccess();
    }
}
