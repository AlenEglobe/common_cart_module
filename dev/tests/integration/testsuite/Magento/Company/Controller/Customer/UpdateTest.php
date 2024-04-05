<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Company\Controller\Customer;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoDataFixture Magento/Company/_files/company.php
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class UpdateTest extends AbstractController
{
    private const XML_PATH_COMPANY_ACTIVE = 'btob/website_configuration/company_active';
    private const UPDATE_URI = 'customer/account/editPost';

    /**
     * @var FormKey
     */
    private $formKey;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var AccountManagementInterface
     */
    private $accountManagement;

    /**
     * @var CustomerInterface
     */
    private $customer;

    /**
     * @var MutableScopeConfigInterface
     */
    private $appConfig;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->formKey = $this->_objectManager->create(FormKey::class);
        $this->session = $this->_objectManager->get(Session::class);
        $this->accountManagement = $this->_objectManager->get(AccountManagementInterface::class);
        $this->appConfig = $this->_objectManager->get(MutableScopeConfigInterface::class);
        $this->customerRepository = $this->_objectManager->get(CustomerRepository::class);
        $this->customer = $this->customerRepository->get('admin@magento.com');
        $this->session->loginById($this->customer->getId());
        $this->appConfig->setValue(self::XML_PATH_COMPANY_ACTIVE, 1, ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->appConfig->setValue(self::XML_PATH_COMPANY_ACTIVE, 0, ScopeInterface::SCOPE_WEBSITE);
        parent::tearDown();
    }

    /**
     * Test company admin can update their account information with valid form data
     *
     * Given an active storefront company admin
     * When the company admin sends a request to change their account information with valid form data
     * Then the company admin's account information is updated successfully
     *
     * @param $dataBundle
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @dataProvider userAccountDataProvider
     */
    public function testCompanyAdminCanUpdateTheirAccountInformation($dataBundle): void
    {
        foreach ($dataBundle as $data) {
            $data['form_key'] = $this->formKey->getFormKey();

            $this->editUser($data);
            $customer = $this->customerRepository->get('admin@magento.com');

            $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());

            $this->assertEquals(
                $data["extension_attributes"]["company_attributes"]["job_title"],
                $customer->getExtensionAttributes()->getCompanyAttributes()->getJobTitle()
            );
            $this->assertEquals($data["firstname"], $customer->getFirstname());
            $this->assertEquals($data["lastname"], $customer->getLastname());
        }
    }

    public function userAccountDataProvider()
    {
        return [
            [[
                [
                    'firstname' => 'Company',
                    'lastname' => 'Admin',
                    'email' => 'admin@magento.com',
                    'extension_attributes' => [
                        'company_attributes' => [
                            'job_title' => 'Software Developer'
                        ]
                    ],
                    'custom_attributes' => [
                        'test_attribute' => [
                            'attribute_code' => 'test_attribute',
                            'value' => 1
                        ]
                    ]
                ],
                [
                    'firstname' => 'John',
                    'lastname' => 'Doe',
                    'email' => 'admin@magento.com',
                    'extension_attributes' => [
                        'company_attributes' => [
                            'job_title' => ''
                        ]
                    ],
                    'custom_attributes' => [
                        'test_attribute' => [
                            'attribute_code' => 'test_attribute',
                            'value' => 1
                        ]
                    ]
                ],
                [
                    'firstname' => 'Company',
                    'lastname' => 'Admin',
                    'email' => 'admin@magento.com',
                    'extension_attributes' => [
                        'company_attributes' => [
                            'job_title' => 'CEO'
                        ]
                    ],
                    'custom_attributes' => [
                        'test_attribute' => [
                            'attribute_code' => 'test_attribute',
                            'value' => 0
                        ]
                    ]
                ]
            ]]
        ];
    }

    /**
     * Update logged in user with provided data
     *
     * @param $data
     */
    private function editUser($data): void
    {
        $this->getRequest()
            ->setMethod(HttpRequest::METHOD_POST)
            ->setParam('isAjax', true)
            ->setParam('customer', $data)
            ->setPostValue($data);
        $this->dispatch(self::UPDATE_URI);
    }
}
