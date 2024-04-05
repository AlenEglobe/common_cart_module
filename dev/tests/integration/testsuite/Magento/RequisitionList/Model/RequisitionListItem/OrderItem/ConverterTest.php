<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\RequisitionList\Model\RequisitionListItem\OrderItem;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Integration Test for Class ConverterTest
 *
 * @magentoAppArea frontend
 */
class ConverterTest extends TestCase
{
    /**
     * @var Order
     */
    private $orderFactory;

    /**
     * @var Converter
     */
    private $requisitionListItemConverter;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $this->orderFactory = $objectManager->create(OrderInterfaceFactory::class);
        $this->requisitionListItemConverter = $objectManager->create(Converter::class);
    }

    /**
     * Test adding product ID to info buy request array of requisition list item
     * if it's missing in order item
     *
     * @magentoDataFixture Magento/Sales/_files/order_item_with_configurable_for_reorder.php
     */
    public function testConvertWithoutProductId()
    {
        $order = $this->orderFactory->create()->loadByIncrementId(100001001);
        $orderItems = $order->getItems();

        foreach ($orderItems as $orderItem) {
            if ($orderItem->getParentItemId()) {
                continue;
            }
            $orderItemBuyRequest = $orderItem->getData()['product_options']['info_buyRequest'];
            $this->assertArrayNotHasKey('product', $orderItemBuyRequest);
            $requisitionListItem = $this->requisitionListItemConverter->convert($orderItem, $orderItem->getProductId());
            $requisitionListItemBuyRequest = $requisitionListItem->getOptions()['info_buyRequest'];
            $this->assertArrayHasKey('product', $requisitionListItemBuyRequest);
        }
    }

    /**
     * Test product ID isn't changed or removed from info buy request array of requisition list item
     * if it already exists in order item
     *
     * @magentoDataFixture Magento/Sales/_files/order_with_different_types_of_product.php
     */
    public function testConvertWithProductId()
    {
        $order = $this->orderFactory->create()->loadByIncrementId(100000001);
        $orderItems = $order->getItems();

        foreach ($orderItems as $orderItem) {
            if ($orderItem->getParentItemId() || $orderItem->getProductType() !== Configurable::TYPE_CODE) {
                continue;
            }
            $orderItemBuyRequest = $orderItem->getData()['product_options']['info_buyRequest'];
            $this->assertArrayHasKey('product', $orderItemBuyRequest);
            $requisitionListItem = $this->requisitionListItemConverter->convert($orderItem, $orderItem->getProductId());
            $requisitionListItemBuyRequest = $requisitionListItem->getOptions()['info_buyRequest'];
            $this->assertArrayHasKey('product', $requisitionListItemBuyRequest);
            $this->assertEquals($orderItemBuyRequest['product'], $requisitionListItemBuyRequest['product']);
        }
    }
}
