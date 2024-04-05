<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\PurchaseOrder\Block\PurchaseOrder\Info;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Paypal\Model\Config;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Block\PurchaseOrder\Info\Buttons;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Block test class for purchase order buttons
 *
 * @see \Magento\PurchaseOrder\Block\PurchaseOrder\Info\Buttons
 *
 * @magentoAppArea frontend
 */
class ButtonsTest extends TestCase
{
    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var Buttons
     */
    private $buttonsBlock;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @inheriDoc
     */
    protected function setUp(): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $this->purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->buttonsBlock = $objectManager->get(Buttons::class);
        $this->customerSession = $objectManager->get(CustomerSession::class);
    }

    /**
     * Test that the purchase order requires payment from current customer if online payment method selected
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testPaymentRequired()
    {
        $customer = $this->customerRepository->get('customer@example.com');
        $purchaseOrder = $this->getPurchaseOrderByIncrementId(900000001);
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT);
        $purchaseOrder->setPaymentMethod(Config::METHOD_BILLING_AGREEMENT);
        $this->purchaseOrderRepository->save($purchaseOrder);
        $this->buttonsBlock->setPurchaseOrderById($purchaseOrder->getId());
        $this->customerSession->loginById($customer->getId());
        $this->assertTrue($this->buttonsBlock->paymentRequired());
    }

    /**
     * Get purchase order by increment id
     *
     * @param int $incrementId
     * @return mixed
     */
    private function getPurchaseOrderByIncrementId(int $incrementId)
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', $incrementId)->create();
        return current($this->purchaseOrderRepository->getList($searchCriteria)->getItems());
    }
}
