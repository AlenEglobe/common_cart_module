<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\FunctionalTestingFramework\ObjectManagerInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderManagementInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderManagement;
use Magento\PurchaseOrder\Model\PurchaseOrderPaymentInformationManagement as Subject;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PurchaseOrderPaymentInformationManagementTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|PurchaseOrderManagementInterface
     */
    private $purchaseOrderManagement;

    /**
     * @var Subject
     */
    private $purchaseOrderPaymentInfoManagement;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();
        $this->purchaseOrderManagement = $this->getMockBuilder(PurchaseOrderManagementInterface::class)
            ->disableOriginalConstructor()->getMockForAbstractClass();
        $this->objectManager->addSharedInstance(
            $this->purchaseOrderManagement,
            PurchaseOrderManagement::class
        );

        $this->purchaseOrderPaymentInfoManagement = $this->objectManager->get(Subject::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
    }

    /**
     * Verify that if a company has at least one rule that the order is not approved
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_single_approver.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \ReflectionException
     */
    public function testDetermineStatusDoesNotTakeActionWithRules()
    {
        $this->purchaseOrderManagement->expects($this->never())
            ->method('approvePurchaseOrder');

        $this->purchaseOrderManagement->expects($this->never())
            ->method('setApprovalRequired');

        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');

        $reflection = new \ReflectionClass($this->purchaseOrderPaymentInfoManagement);
        $method = $reflection->getMethod('determineStatus');
        $method->setAccessible(true);

        $method->invokeArgs($this->purchaseOrderPaymentInfoManagement, [$purchaseOrder]);

        // Verify the order is placed into pending for validation to occur
        $this->assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \ReflectionException
     */
    public function testDetermineStatusApprovesPurchaseOrderWithNoRules()
    {
        $this->purchaseOrderManagement->expects($this->once())
            ->method('approvePurchaseOrder');

        $this->purchaseOrderManagement->expects($this->never())
            ->method('setApprovalRequired');

        $purchaseOrder = $this->getPurchaseOrderForCustomer('veronica.costello@example.com');

        $reflection = new \ReflectionClass($this->purchaseOrderPaymentInfoManagement);
        $method = $reflection->getMethod('determineStatus');
        $method->setAccessible(true);

        $method->invokeArgs($this->purchaseOrderPaymentInfoManagement, [$purchaseOrder]);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \ReflectionException
     */
    public function testDetermineStatusTakesNoActionIfPurchaseOrderApproved()
    {
        $this->purchaseOrderManagement->expects($this->never())
            ->method('approvePurchaseOrder');

        $this->purchaseOrderManagement->expects($this->never())
            ->method('setApprovalRequired');

        $purchaseOrder = $this->getPurchaseOrderForCustomer('veronica.costello@example.com');
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED);
        $this->purchaseOrderRepository->save($purchaseOrder);

        $reflection = new \ReflectionClass($this->purchaseOrderPaymentInfoManagement);
        $method = $reflection->getMethod('determineStatus');
        $method->setAccessible(true);

        $method->invokeArgs($this->purchaseOrderPaymentInfoManagement, [$purchaseOrder]);

        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVED, $purchaseOrder->getStatus());
    }

    /**
     * @param string $customerEmail
     * @return PurchaseOrderInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
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
