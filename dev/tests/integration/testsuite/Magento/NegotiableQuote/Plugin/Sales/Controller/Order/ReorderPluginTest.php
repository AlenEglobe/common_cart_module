<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote\Plugin\Sales\Controller\Order;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Controller\Order\Reorder;
use Magento\TestFramework\Interception\PluginList;
use Magento\TestFramework\Request;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Test for around plugin to reorder controller.
 *
 * @see \Magento\NegotiableQuote\Plugin\Sales\Controller\Order\ReorderPlugin
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ReorderPluginTest extends AbstractController
{
    /** @var CheckoutSession */
    private $checkoutSession;

    /** @var OrderInterfaceFactory */
    private $orderFactory;

    /** @var Session */
    private $customerSession;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var CartRepositoryInterface */
    private $quoteRepository;

    /** @var CartInterface */
    private $quote;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->checkoutSession = $this->_objectManager->get(CheckoutSession::class);
        $this->orderFactory = $this->_objectManager->get(OrderInterfaceFactory::class);
        $this->customerSession = $this->_objectManager->get(Session::class);
        $this->productRepository = $this->_objectManager->get(ProductRepositoryInterface::class);
        $this->productRepository->cleanCache();
        $this->quoteRepository = $this->_objectManager->get(CartRepositoryInterface::class);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        if ($this->quote instanceof CartInterface) {
            $this->quoteRepository->delete($this->quote);
        }
        $this->customerSession->setCustomerId(null);

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testPluginIsRegistered(): void
    {
        $pluginInfo = $this->_objectManager->get(PluginList::class)->get(Reorder::class);
        $this->assertSame(ReorderPlugin::class, $pluginInfo['replaceQuoteItems']['instance']);
    }

    /**
     * @magentoDataFixture Magento/Sales/_files/customer_order_with_taxable_product.php
     *
     * @return void
     */
    public function testReorder(): void
    {
        $order = $this->orderFactory->create()->loadByIncrementId('test_order_with_taxable_product');
        $this->customerSession->setCustomerId($order->getCustomerId());
        $this->dispatchReorderRequest(['order_id' => (int)$order->getId()]);
        $this->assertRedirect($this->stringContains('checkout/cart'));
        $this->quote = $this->checkoutSession->getQuote();
        $this->assertCount(1, $this->quote->getItemsCollection());
        $this->assertQuoteAddress($order->getShippingAddress(), $this->quote->getShippingAddress());
        $this->assertQuoteAddress($order->getBillingAddress(), $this->quote->getBillingAddress());
        $this->assertEquals($order->getPayment()->getMethod(), $this->quote->getPayment()->getMethod());
        $this->assertEquals($order->getShippingMethod(), $this->quote->getShippingAddress()->getShippingMethod());
    }

    /**
     * @magentoDataFixture Magento/Sales/_files/customer_order_with_two_items.php
     *
     * @return void
     */
    public function testReorderDisabledProduct(): void
    {
        $order = $this->orderFactory->create()->loadByIncrementId('100000555');
        $this->customerSession->setCustomerId($order->getCustomerId());
        $product = $this->productRepository->get('simple-1');
        $product->setStatus(Status::STATUS_DISABLED);
        $this->productRepository->save($product);
        $this->dispatchReorderRequest(['order_id' => (int)$order->getId()]);
        $this->assert404NotFound();
    }

    /**
     * Check that custom options are added to new Quote Item after reorder.
     *
     * @return void
     * @magentoDataFixture Magento/Sales/_files/customer_order_item_with_product_and_custom_options.php
     */
    public function testReorderWithCustomOptions(): void
    {
        $order = $this->orderFactory->create()->loadByIncrementId('100000001');
        $this->customerSession->setCustomerId($order->getCustomerId());
        $this->dispatchReorderRequest(['order_id' => (int)$order->getId()]);
        $this->assertRedirect($this->stringContains('checkout/cart'));

        $this->quote = $this->checkoutSession->getQuote();
        $itemsCollection = $this->quote->getItemsCollection();
        $this->assertCount(1, $itemsCollection);
        $options = $itemsCollection->getFirstItem()->getOptions();
        $this->assertCount(6, $options);
    }

    /**
     * Dispatch reorder request.
     *
     * @param array $params
     * @return void
     */
    private function dispatchReorderRequest(array $params): void
    {
        $this->getRequest()->setMethod(Request::METHOD_POST);
        $this->getRequest()->setParams($params);
        $this->dispatch('sales/order/reorder/');
    }

    /**
     * Assert that address was cloned from order to new customer quote.
     *
     * @param OrderAddressInterface $orderAddress
     * @param AddressInterface $quoteAddress
     * @return void
     */
    private function assertQuoteAddress(OrderAddressInterface $orderAddress, AddressInterface $quoteAddress): void
    {
        $addressFieldsForCheck = [
            'firstname', 'lastname', 'company', 'street', 'city', 'country_id', 'region_id', 'postcode', 'telephone',
        ];
        foreach ($addressFieldsForCheck as $field) {
            $this->assertEquals($orderAddress->getData($field), $quoteAddress->getData($field));
        }
    }
}
