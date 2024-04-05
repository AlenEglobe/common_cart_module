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
class InlineEditTest extends AbstractBackendController
{
    /**
     * @inheritDoc
     */
    protected $resource = InlineEdit::ADMIN_RESOURCE;

    /**
     * @inheritDoc
     */
    protected $uri = 'backend/company/index/inlineEdit';

    /**
     * @inheritDoc
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * Test backoffice admin has access to /company/index/inlineEdit when granted Magento_Company::index permission
     *
     * Given a backoffice admin
     * When Magento_Company::index ACL permission is allowed for the admin's role
     * And a POST request is made to edit a company directly from the company listing page
     * Then the HTTP response code is 200
     */
    public function testAdminCanEditCompanyFromCompanyListingWhenCompanyIndexACLEnabled()
    {
        return parent::testAclHasAccess();
    }

    /**
     * Test backoffice admin does not have access to /company/index/inlineEdit when denied Magento_Company::index
     * permission
     *
     * Given a backoffice admin
     * When Magento_Company::index ACL permission is denied for the admin's role
     * And a POST request is made to edit a company directly from the company listing page
     * Then the HTTP response code is 403
     */
    public function testAdminCannotEditCompanyFromCompanyListingWhenCompanyIndexACLDisabled()
    {
        return parent::testAclNoAccess();
    }
}
