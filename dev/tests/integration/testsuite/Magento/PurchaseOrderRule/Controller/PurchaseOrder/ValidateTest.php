<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Controller\PurchaseOrder;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\Comment;
use Magento\PurchaseOrder\Model\CommentManagement;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Controller test class for validating purchase order..
 *
 * @see \Magento\PurchaseOrderRule\Controller\Validate\Index
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class ValidateTest extends AbstractController
{
    /**
     * Url to dispatch.
     */
    private const URI = 'purchaseorderrule/purchaseorder/validate';

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var CommentManagement
     */
    private $commentManagement;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->session = $objectManager->get(Session::class);
        $this->commentManagement = $objectManager->get(CommentManagement::class);

        // Enable company functionality at the system level
        $scopeConfig = $objectManager->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue('btob/website_configuration/company_active', '1', ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testValidateActionGetRequest()
    {
        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $this->assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());

        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());
        $this->assert404NotFound();
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals(PurchaseOrderInterface::STATUS_PENDING, $postPurchaseOrder->getStatus());
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testValidateActionAsGuestUser()
    {
        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $this->assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $this->assertRedirect($this->stringContains('customer/account/login'));
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals(PurchaseOrderInterface::STATUS_PENDING, $postPurchaseOrder->getStatus());
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testValidateActionAsNonCompanyUser()
    {
        $nonCompanyUser = $this->customerRepository->get('customer@example.com');
        $this->session->loginById($nonCompanyUser->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $this->assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $this->assertRedirect($this->stringContains('noroute'));
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals(PurchaseOrderInterface::STATUS_PENDING, $postPurchaseOrder->getStatus());

        $this->session->logout();
    }

    /**
     * @param string $currentUserEmail
     * @param string $createdByUserEmail
     * @param int $expectedHttpResponseCode
     * @param string $expectedRedirect
     * @param string $expectedStatus
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @dataProvider validateActionAsCompanyUserDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/simple_approval_rule.php
     */
    public function testValidateActionAsCompanyUser(
        $currentUserEmail,
        $createdByUserEmail,
        $expectedHttpResponseCode,
        $expectedRedirect,
        $expectedStatus = PurchaseOrderInterface::STATUS_PENDING
    ) {
        // Log in as the current user
        $currentUser = $this->customerRepository->get($currentUserEmail);
        $this->session->loginById($currentUser->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer($createdByUserEmail);
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);
        $purchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());

        // Dispatch the request
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        $this->assertEquals($expectedHttpResponseCode, $this->getResponse()->getHttpResponseCode());
        if (isset($expectedRedirect)) {
            $this->assertRedirect($this->stringContains($expectedRedirect));
        }
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals($expectedStatus, $postPurchaseOrder->getStatus());

        $this->session->logout();
    }

    /**
     * Data provider for various validate action scenarios for company users.
     *
     * @return array
     */
    public function validateActionAsCompanyUserDataProvider()
    {
        return [
            'validate_my_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'veronica.costello@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied'
            ],
            'validate_subordinate_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'alex.smith@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => '',
                'expected_status' => PurchaseOrderInterface::STATUS_PENDING
            ],
            'validate_superior_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'john.doe@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied'
            ],
            'as_admin_validate_subordinate_purchase_order' => [
                'current_customer' => 'john.doe@example.com',
                'created_by_customer' => 'alex.smith@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => null,
                'expected_status' => PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED
            ]
        ];
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     */
    public function testApproveActionAsOtherCompanyAdmin()
    {
        $nonCompanyUser = $this->customerRepository->get('company-admin@example.com');
        $this->session->loginById($nonCompanyUser->getId());
        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $this->assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $this->assertRedirect($this->stringContains('company/accessdenied'));
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals(PurchaseOrderInterface::STATUS_PENDING, $postPurchaseOrder->getStatus());

        $this->session->logout();
    }

    /**
     * Verify a company admin validating a purchase with a comment
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testValidateActionAsCompanyAdminWithCommentPurchaseOrder()
    {
        $companyAdmin = $this->customerRepository->get('admin@magento.com');
        $this->session->loginById($companyAdmin->getId());

        $purchaserEmail = 'customer@example.com';
        $purchaseOrder = $this->getPurchaseOrderForCustomer($purchaserEmail);
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Validate the purchase order
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->getRequest()->setParams([
            'comment' => 'Our validation comment'
        ]);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Assert the Purchase Order is now approved
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVED, $postPurchaseOrder->getStatus());

        // Verify the comment was added to the Purchase Order
        $comments = $this->commentManagement->getPurchaseOrderComments($purchaseOrder->getEntityId());
        $this->assertEquals(1, $comments->getSize());
        /** @var Comment $comment */
        $comment = $comments->getFirstItem();
        $this->assertEquals('Our validation comment', $comment->getComment());
        $this->assertEquals($companyAdmin->getId(), $comment->getCreatorId());

        $this->session->logout();
    }

    /**
     * Get purchase order for the given customer.
     *
     * @param string $customerEmail
     * @return \Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface
     */
    private function getPurchaseOrderForCustomer(string $customerEmail)
    {
        $customer = $this->customerRepository->get($customerEmail);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(PurchaseOrderInterface::CREATOR_ID, $customer->getId())
            ->create();
        $purchaseOrders = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();
        return array_shift($purchaseOrders);
    }
}
