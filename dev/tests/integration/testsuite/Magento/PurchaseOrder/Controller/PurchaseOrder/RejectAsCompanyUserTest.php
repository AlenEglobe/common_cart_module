<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderLogInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderLogRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * Controller test class for rejecting purchase order as company user
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\Reject

 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class RejectAsCompanyUserTest extends PurchaseOrderAbstract
{
    /**
     * Url to dispatch.
     */
    private const URI = 'purchaseorder/purchaseorder/reject';

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var ObjectManager $objectManager */
        $this->objectManager = Bootstrap::getObjectManager();
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->session = $this->objectManager->get(Session::class);
    }

    /**
     * @param string $currentUserEmail
     * @param string $createdByUserEmail
     * @param int $expectedHttpResponseCode
     * @param string $expectedRedirect
     * @param string $expectedStatus
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @dataProvider rejectActionAsCompanyUserDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testRejectActionAsCompanyUser(
        $currentUserEmail,
        $createdByUserEmail,
        $expectedHttpResponseCode,
        $expectedRedirect,
        $expectedStatus = PurchaseOrderInterface::STATUS_PENDING
    ) {
        // Log in as the current user
        $currentUser = $this->objectManager->get(CustomerRepositoryInterface::class)->get($currentUserEmail);
        $this->session->loginById($currentUser->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer($createdByUserEmail);
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->save($purchaseOrder);
        $purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $purchaseOrder = $purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());

        // Dispatch the request
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        $this->assertEquals($expectedHttpResponseCode, $this->getResponse()->getHttpResponseCode());
        $this->assertRedirect($this->stringContains($expectedRedirect));
        $postPurchaseOrder = $purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals($expectedStatus, $postPurchaseOrder->getStatus());

        // Verify no reject message in the log
        $rejected = $this->objectManager->get(PurchaseOrderLogRepositoryInterface::class)->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $purchaseOrder->getEntityId())
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'reject')
                ->create()
        );
        $this->assertEquals(0, $rejected->getTotalCount());

        $this->session->logout();
    }

    /**
     * Data provider for various reject action scenarios for company users.
     *
     * @return array
     */
    public function rejectActionAsCompanyUserDataProvider()
    {
        return [
            'reject_my_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'veronica.costello@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied'
            ],
            'reject_subordinate_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'alex.smith@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied'
            ],
            'reject_superior_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'john.doe@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied'
            ]
        ];
    }
}
