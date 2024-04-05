<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Company\Controller\Adminhtml\Index;

use Magento\Framework\Acl;
use Magento\Framework\Acl\Builder as AclBuilder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * @magentoAppArea adminhtml
 */
class IndexTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = Index::ADMIN_RESOURCE;

    /**
     * @var string
     */
    protected $uri = 'backend/company/index/index';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_GET;

    /**
     * @var Acl
     */
    private $acl;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->acl = $this->_objectManager->get(AclBuilder::class)->getAcl();
    }

    /**
     * Test that a backoffice admin can see the Add New
     * Company button when the Magento_Company::add
     * permission is enabled for the role
     *
     * Given a backoffice admin
     * When the ACL is updated to allow Magento_Company::add for default role
     * And the admin visits the company listing page
     * Then the Add New Company button is present in the response body
     *
     * @magentoAppIsolation enabled
     * @dataProvider expectedButtonsDataProvider
     * @param string $aclResourceForButton
     * @param string $expectedStringForButton
     */
    public function testAddCompanyButtonIsVisibleWhenCompanyAddACLEnabled(
        string $aclResourceForButton,
        string $expectedStringForButton
    ) {
        $this->acl->allow(Bootstrap::ADMIN_ROLE_ID, $aclResourceForButton);
        $this->dispatch($this->uri);
        $responseHtml = $this->getResponse()->getContent();

        $this->assertStringContainsString($expectedStringForButton, $responseHtml);
    }

    /**
     * Test that a backoffice admin cannot see the Add New Company button when the Magento_Company::add permission is
     * disabled for the role
     *
     * Given a backoffice admin
     * When the ACL is updated to deny Magento_Company::add for default role
     * And the admin visits the company listing page
     * Then the Add New Company button is absent in the response body
     *
     * @magentoAppIsolation enabled
     * @dataProvider expectedButtonsDataProvider
     * @param string $aclResourceForButton
     * @param string $expectedStringForButton
     */
    public function testAddCompanyButtonIsNotVisibleWhenCompanyAddACLDisabled(
        string $aclResourceForButton,
        string $expectedStringForButton
    ) {
        $this->acl->deny(Bootstrap::ADMIN_ROLE_ID, $aclResourceForButton);
        $this->dispatch($this->uri);
        $responseHtml = $this->getResponse()->getContent();

        $this->assertStringNotContainsString($expectedStringForButton, $responseHtml);
    }

    /**
     * @return array
     */
    public function expectedButtonsDataProvider()
    {
        return [
            'Add Button' => [NewAction::ADMIN_RESOURCE, 'add-button']
        ];
    }

    /**
     * Test backoffice admin has access to /company/index/index when granted Magento_Company::index permission
     *
     * Given a backoffice admin
     * When Magento_Company::index ACL permission is allowed for the admin's role
     * And a GET request is made to go to the company listing page
     * Then the HTTP response code is 200
     */
    public function testAdminCanAccessCompanyListingPageWhenCompanyIndexACLEnabled()
    {
        return parent::testAclHasAccess();
    }

    /**
     * Test backoffice admin does not have access to /company/index/index when denied Magento_Company::index permission
     *
     * Given a backoffice admin
     * When Magento_Company::index ACL permission is denied for the admin's role
     * And a GET request is made to go to the company listing page
     * Then the HTTP response code is 403
     */
    public function testAdminCanAccessCompanyListingPageWhenCompanyIndexACLDisabled()
    {
        return parent::testAclNoAccess();
    }
}
