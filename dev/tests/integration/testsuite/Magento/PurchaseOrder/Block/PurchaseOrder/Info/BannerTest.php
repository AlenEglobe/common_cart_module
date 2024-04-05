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
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Block test class for purchase order banner
 *
 * @see \Magento\PurchaseOrder\Block\PurchaseOrder\Info\Banner
 *
 * @magentoAppArea frontend
 */
class BannerTest extends TestCase
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
     * @var Banner
     */
    private $bannerBlock;

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
        $this->bannerBlock = $objectManager->get(Banner::class);
        $this->customerSession = $objectManager->get(CustomerSession::class);
    }

    /**
     * Test that the purchase order can be ordered by the current customer
     * therefore the banner can be shown
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testCanOrder()
    {
        $customer = $this->customerRepository->get('customer@example.com');
        $purchaseOrder = $this->getPurchaseOrderByIncrementId(900000001);
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT);
        $purchaseOrder->setPaymentMethod(Config::METHOD_BILLING_AGREEMENT);
        $this->purchaseOrderRepository->save($purchaseOrder);
        $this->bannerBlock->setPurchaseOrderById($purchaseOrder->getId());
        $this->customerSession->loginById($customer->getId());
        $this->assertTrue($this->bannerBlock->canOrder());
    }

    /**
     * Test that the purchase order cannot be ordered by the current customer
     * therefore the banner with the link cannon be shown.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testCannotOrder()
    {
        $customer = $this->customerRepository->get('customer@example.com');
        $purchaseOrder = $this->getPurchaseOrderByIncrementId(900000001);
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT);
        $purchaseOrder->setPaymentMethod('checkmo');
        $this->purchaseOrderRepository->save($purchaseOrder);
        $this->bannerBlock->setPurchaseOrderById($purchaseOrder->getId());
        $this->customerSession->loginById($customer->getId());
        $this->assertFalse($this->bannerBlock->canOrder());
    }

    /**
     * Test that the banner without payment link can be viewed by the current customer
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testCanView()
    {
        $customer = $this->customerRepository->get('customer@example.com');
        $purchaseOrder = $this->getPurchaseOrderByIncrementId(900000001);
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT);
        $purchaseOrder->setPaymentMethod(Config::METHOD_BILLING_AGREEMENT);
        $this->purchaseOrderRepository->save($purchaseOrder);
        $this->bannerBlock->setPurchaseOrderById($purchaseOrder->getId());
        $this->customerSession->loginById($customer->getId());
        $this->assertTrue($this->bannerBlock->canView());
    }

    /**
     * Test that the banner without payment link cannot be viewed by the current customer
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testCannotView()
    {
        $customer = $this->customerRepository->get('admin@magento.com');
        $purchaseOrder = $this->getPurchaseOrderByIncrementId(900000001);
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT);
        $purchaseOrder->setPaymentMethod('checkmo');
        $this->purchaseOrderRepository->save($purchaseOrder);
        $this->bannerBlock->setPurchaseOrderById($purchaseOrder->getId());
        $this->customerSession->loginById($customer->getId());
        $this->assertFalse($this->bannerBlock->canView());
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
