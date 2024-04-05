<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CompanyCredit\Service\V1;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Test invoice of order placed with Payment on Account method.
 */
class PaymentOnAccountInvoiceTest extends WebapiAbstract
{
    const RESOURCE_PATH = '/V1/order/:orderId/invoice';

    const SERVICE_READ_NAME = 'salesInvoiceOrderV1';

    const SERVICE_VERSION = 'V1';

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * Test partial invoice of order placed with Payment on Account method.
     *
     * @return void
     * @magentoApiDataFixture Magento/CompanyCredit/_files/order_new_paid_with_companycredit.php
     */
    public function testPartialInvoice(): void
    {
        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $this->objectManager->get(OrderRepositoryInterface::class);
        /** @var Collection $orderCollection */
        $orderCollection = $this->objectManager->get(Collection::class);
        /** @var InvoiceRepositoryInterface $invoiceRepository */
        $invoiceRepository = $this->objectManager->get(InvoiceRepositoryInterface::class);
        /** @var Order $order */
        $order = $orderCollection->getFirstItem();

        $serviceInfo = [
            'rest' => [
                'resourcePath' => strtr(self::RESOURCE_PATH, [':orderId' => $order->getId()]),
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'execute',
            ],
        ];

        $item = $order->getAllItems()[0];
        $this->assertEquals(2, $item->getQtyOrdered());
        $requestData = [
            'orderId' => $order->getId(),
            'items' => [
                [
                    'order_item_id' => $item->getItemId(),
                    'qty' => 1,
                ]
            ]
        ];
        $result = $this->_webApiCall(
            $serviceInfo,
            $requestData
        );

        $this->assertNotEmpty($result);

        try {
            $invoice = $invoiceRepository->get($result);
        } catch (NoSuchEntityException $e) {
            $this->fail('Failed asserting that Invoice was created');
        }

        $invoiceItems = $invoice->getItems();
        $this->assertCount(1, $invoiceItems);
        $invoiceItem = reset($invoiceItems);
        $this->assertEquals(1, $invoiceItem->getQty());

        /** @var Order $updatedOrder */
        $updatedOrder = $orderRepository->get($order->getId());

        $this->assertNotEquals(
            $order->getStatus(),
            $updatedOrder->getStatus(),
            'Failed asserting that Order status was changed'
        );
    }
}
