<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\PurchaseOrder\Model\QueueConsumer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\MessageQueue\ConsumerFactory;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Model\HistoryManagementInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\MessageQueue\ClearQueueProcessor;

/**
 * Test class for PurchaseOrderToOrderConsumer
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PurchaseOrderToOrderConsumerTest extends \PHPUnit\Framework\TestCase
{
    private const CONSUMER_NAME = 'purchaseorder.toorder';

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var ConsumerFactory
     */
    private $consumerFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var HistoryManagementInterface
     */
    private $negotiableQuoteHistoryManagement;

    /**
     * @var ClearQueueProcessor
     */
    private $clearQueueProcessor;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->publisher = $this->objectManager->get(PublisherInterface::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->consumerFactory = $this->objectManager->get(ConsumerFactory::class);
        $this->orderRepository = $this->objectManager->get(OrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->negotiableQuoteHistoryManagement = $this->objectManager->get(HistoryManagementInterface::class);
        $this->clearQueueProcessor = $this->objectManager->get(ClearQueueProcessor::class);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testProcess()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);
        $this->assertNull($purchaseOrder->getOrderId());
        $this->assertNull($purchaseOrder->getOrderIncrementId());
        $this->publisher->publish('purchaseorder.toorder', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_PENDING, $postPurchaseOrder->getStatus());
        $this->assertNull($postPurchaseOrder->getOrderId());
        $this->assertNull($purchaseOrder->getOrderIncrementId());

        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_ORDER_IN_PROGRESS);
        $this->purchaseOrderRepository->save($purchaseOrder);
        $this->assertNull($purchaseOrder->getOrderId());
        $this->assertNull($purchaseOrder->getOrderIncrementId());
        $this->publisher->publish('purchaseorder.toorder', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_ORDER_IN_PROGRESS, $postPurchaseOrder->getStatus());
        $this->assertNull($postPurchaseOrder->getOrderId());
        $this->assertNull($postPurchaseOrder->getOrderIncrementId());

        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_ORDER_PLACED);
        $this->purchaseOrderRepository->save($purchaseOrder);
        $this->assertNull($purchaseOrder->getOrderId());
        $this->assertNull($purchaseOrder->getOrderIncrementId());
        $this->publisher->publish('purchaseorder.toorder', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_ORDER_PLACED, $postPurchaseOrder->getStatus());
        $this->assertNull($postPurchaseOrder->getOrderId());
        $this->assertNull($postPurchaseOrder->getOrderIncrementId());

        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED);
        $this->purchaseOrderRepository->save($purchaseOrder);
        $this->assertNull($purchaseOrder->getOrderId());
        $this->assertNull($purchaseOrder->getOrderIncrementId());
        $this->publisher->publish('purchaseorder.toorder', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_ORDER_PLACED, $postPurchaseOrder->getStatus());
        $this->assertNotNull($postPurchaseOrder->getOrderId());
        $this->assertNotNull($postPurchaseOrder->getOrderIncrementId());

        $order = $this->orderRepository->get($postPurchaseOrder->getOrderId());
        $this->assertEquals($order->getIncrementId(), $postPurchaseOrder->getOrderIncrementId());
    }

    /**
     * Test that converting a purchase order to a sales order also updates any associated negotiable quote.
     *
     * In particular, this should update the negotiable quote status and history.
     *
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_order_using_negotiable_quote.php
     */
    public function testNegotiableQuoteStatusIsUpdatedForSuccessfulOrderCreation()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        // Approve the purchase order so that it can be converted to an order
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the queue consumer to place the order
        $this->publisher->publish('purchaseorder.toorder', $purchaseOrder->getEntityId());
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Assert that the associated negotiable quote has been updated to reflect the order creation
        $quote = $this->quoteRepository->get($purchaseOrder->getQuoteId());
        /** @var NegotiableQuoteInterface $negotiableQuote */
        $negotiableQuote = $quote->getExtensionAttributes()->getNegotiableQuote();
        $this->assertEquals(NegotiableQuoteInterface::STATUS_ORDERED, $negotiableQuote->getStatus());

        // Assert that a negotiable quote history log has been added for the order creation
        $quoteHistory = $this->negotiableQuoteHistoryManagement->getQuoteHistory($negotiableQuote->getQuoteId());
        $lastHistoryEntry = array_pop($quoteHistory);
        $lastHistoryData = json_decode($lastHistoryEntry->getLogData(), true);

        $this->assertEquals(
            NegotiableQuoteInterface::STATUS_ORDERED,
            $lastHistoryData['status']['new_value']
        );

        $this->assertEquals(0, $lastHistoryEntry->getAuthorId());
    }

    /**
     * @param string $customerEmail
     * @return \Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
