<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Company\Controller\Adminhtml\Index;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Model\Company;
use Magento\CompanyCredit\Controller\Adminhtml\Index\Reimburse;
use Magento\Framework\Acl;
use Magento\Framework\Acl\Builder as AclBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * @magentoAppArea adminhtml
 */
class EditTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = Edit::ADMIN_RESOURCE;

    /**
     * @var string
     */
    protected $uri = 'backend/company/index/edit';

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
     * Test that a backoffice admin can see the Save button, Delete button, and Reimburse Balance button on the Edit
     * Company page when the Magento_Company::manage, Magento_Company::delete, and Magento_Company::reimburse
     * permissions are allowed, respectively, for the role
     *
     * Given a backoffice admin
     * When the ACL is updated to allow Magento_Company::manage for default role
     * And the admin visits the company edit page in the backoffice
     * Then the Save button is present in the response body
     * When the ACL is updated to allow Magento_Company::delete for default role
     * And the admin visits the company edit page in the backoffice
     * Then the Delete button is present in the response body
     * When the ACL is updated to allow Magento_Company::reimburse_balance for default role
     * And the admin visits the company edit page in the backoffice
     * Then the Reimburse button is present in the response body
     *
     * @magentoDataFixture Magento/Company/_files/company.php
     * @magentoAppIsolation enabled
     * @dataProvider expectedButtonsDataProvider
     * @param string $aclResourceForButton
     * @param string $expectedStringForButton
     */
    public function testButtonIsVisibleWhenACLPermissionsAreEnabled(
        string $aclResourceForButton,
        string $expectedStringForButton
    ) {
        $this->acl->allow(Bootstrap::ADMIN_ROLE_ID, $aclResourceForButton);

        $uriForCompanyEditPage = $this->buildUriForCompanyEditPage();
        $this->dispatch($uriForCompanyEditPage);
        $responseHtml = $this->getResponse()->getContent();

        $this->assertStringContainsString($expectedStringForButton, $responseHtml);
    }

    /**
     * Test that a backoffice admin cannot see the Save button, Delete button, and Reimburse Balance button on the Edit
     * Company page when the Magento_Company::manage, Magento_Company::delete, and Magento_Company::reimburse
     * permissions are separately denied, respectively, for the role
     *
     * Given a backoffice admin
     * When the ACL is updated to deny Magento_Company::manage for default role
     * And the admin visits the company edit page in the backoffice
     * Then the Save button is absent in the response body
     * When the ACL is updated to deny Magento_Company::delete for default role
     * And the admin visits the company edit page in the backoffice
     * Then the Delete button is absent in the response body
     * When the ACL is updated to deny Magento_Company::reimburse_balance for default role
     * And the admin visits the company edit page in the backoffice
     * Then the Reimburse button is absent in the response body
     *
     * @magentoDataFixture Magento/Company/_files/company.php
     * @magentoAppIsolation enabled
     * @dataProvider expectedButtonsDataProvider
     * @param string $aclResourceForButton
     * @param string $expectedStringForButton
     */
    public function testButtonIsNotVisibleWhenACLPermissionsAreDisabled(
        string $aclResourceForButton,
        string $expectedStringForButton
    ) {
        $this->acl->deny(Bootstrap::ADMIN_ROLE_ID, $aclResourceForButton);

        $uriForCompanyEditPage = $this->buildUriForCompanyEditPage();
        $this->dispatch($uriForCompanyEditPage);
        $responseHtml = $this->getResponse()->getContent();

        $this->assertStringNotContainsString($expectedStringForButton, $responseHtml);
    }

    /**
     * @return array
     */
    public function expectedButtonsDataProvider()
    {
        return [
            'Save Button' => [Save::ADMIN_RESOURCE, 'save-button'],
            'Delete Button' => [Delete::ADMIN_RESOURCE, 'company-edit-delete-button'],
            'Reimburse Button' => [Reimburse::ADMIN_RESOURCE, 'company-edit-reimburse-button']
        ];
    }

    /**
     * Generates the edit page uri for the company created by the data fixture.
     * Takes the base uri and appends the company id.
     *
     * @return string
     */
    private function buildUriForCompanyEditPage()
    {
        return $this->uri . '/id/' . $this->getCompanyCreatedByFixture()->getId();
    }

    /**
     * Gets the company created by the data fixture.
     *
     * @return Company
     */
    private function getCompanyCreatedByFixture()
    {
        $searchCriteriaBuilder = $this->_objectManager->get(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->addFilter('company_name', 'Magento');
        $searchCriteria = $searchCriteriaBuilder->create();

        $companyRepository = $this->_objectManager->get(CompanyRepositoryInterface::class);
        $companySearchResults = $companyRepository->getList($searchCriteria);
        $items = $companySearchResults->getItems();

        /** @var Company $company */
        $company = reset($items);

        return $company;
    }

    /**
     * Test backoffice admin has access to /company/index/edit when granted Magento_Company::index permission
     *
     * Given a backoffice admin
     * When Magento_Company::index ACL permission is allowed for the admin's role
     * And a GET request is made to go to the company edit page
     * Then the HTTP response code is 200
     */
    public function testAdminCanAccessCompanyEditPageWhenCompanyIndexACLEnabled()
    {
        return parent::testAclHasAccess();
    }

    /**
     * Test that a backoffice admin does not have access to /company/index/edit when denied Magento_Company::index
     * permission
     *
     * Given a backoffice admin
     * When Magento_Company::index ACL permission is denied for the admin's role
     * And a GET request is made to go to the company edit page
     * Then the HTTP response code is 403
     */
    public function testAdminCannotAccessCompanyEditPageWhenCompanyIndexACLDisabled()
    {
        return parent::testAclNoAccess();
    }
}
