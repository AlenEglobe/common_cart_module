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
class MassDeleteTest extends AbstractBackendController
{
    /**
     * @inheritDoc
     */
    protected $resource = MassDelete::ADMIN_RESOURCE;

    /**
     * @inheritDoc
     */
    protected $uri = 'backend/company/index/massDelete';

    /**
     * @inheritDoc
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * Test backoffice admin has access to /company/index/massDelete when granted Magento_Company::delete permission
     *
     * Given a backoffice admin
     * When Magento_Company::delete ACL permission is allowed for the admin's role
     * And a POST request is made to delete multiple companies directly from the company listing page
     * Then the HTTP response code is 200
     */
    public function testAdminCanBulkDeleteCompaniesWhenCompanyDeleteACLEnabled()
    {
        return parent::testAclHasAccess();
    }

    /**
     * Test backoffice admin does not have access to /company/index/massDelete when denied Magento_Company::delete
     * permission
     *
     * Given a backoffice admin
     * When Magento_Company::delete ACL permission is denied for the admin's role
     * And a POST request is made to delete multiple companies directly from the company listing page
     * Then the HTTP response code is 403
     */
    public function testAdminCannotBulkDeleteCompaniesWhenCompanyDeleteACLDisabled()
    {
        return parent::testAclNoAccess();
    }
}
