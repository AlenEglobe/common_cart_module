<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CompanyCredit\Service\V1;

use Magento\CompanyCredit\Model\HistoryRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Test partial refund of order placed with Payment on Account method.
 */
class PaymentOnAccountRefundTest extends WebapiAbstract
{
    const RESOURCE_PATH = '/V1/creditmemo/refund';

    const SERVICE_READ_NAME = 'salesCreditmemoManagementV1';

    const SERVICE_VERSION = 'V1';

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Set up.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
    }

    /**
     * Test partial refund of order placed with Payment on Account method.
     *
     * @return void
     * @magentoApiDataFixture Magento/CompanyCredit/_files/order_paid_with_companycredit.php
     */
    public function testInvoke()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $orderCollection = $this->objectManager->get(\Magento\Sales\Model\ResourceModel\Order\Collection::class);
        $order = $orderCollection->getFirstItem();

        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME.'refund',
            ],
        ];
        $result = $this->_webApiCall(
            $serviceInfo,
            ['creditmemo' => $this->getCreditmemoData($order), 'offline_requested' => true]
        );

        $this->assertNotEmpty($result);
        $order = $this->objectManager->get(OrderRepositoryInterface::class)->get($order->getId());
        $this->assertEquals(Order::STATE_PROCESSING, $order->getState());
        $creditData = $this->objectManager->get(\Magento\CompanyCredit\Model\CreditDataProvider::class)
            ->get($order->getPayment()->getAdditionalInformation('company_id'));
        $this->assertEquals(-40, $creditData->getBalance(), 'Outstanding Balance is incorrect');
        $this->assertEquals(60, $creditData->getAvailableLimit(), 'Available Credit is incorrect');
        $searchCriteriaBuilder = $this->objectManager->get(\Magento\Framework\Api\SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder
            ->addFilter(\Magento\CompanyCredit\Model\HistoryInterface::COMPANY_CREDIT_ID, $creditData->getId())
            ->create();
        $historyItems = $this->objectManager->get(HistoryRepositoryInterface::class)->getList($searchCriteria);
        $this->assertEquals(
            1,
            $historyItems->getTotalCount(),
            'Number of operations in Credit History grid is incorrect'
        );
        foreach ($historyItems as $history) {
            $this->assertEquals(
                \Magento\CompanyCredit\Model\HistoryInterface::TYPE_REFUNDED,
                $history->getType(),
                'Operation type is incorrect'
            );
            $this->assertEquals(10, $history->getAmount(), 'Amount of operation is incorrect');
        }
    }

    /**
     * Get data for creditmemo creation.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return array
     */
    public function getCreditmemoData(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        $items = [];
        /** @var \Magento\Sales\Model\Order\Item $orderItem */
        foreach ($order->getAllItems() as $orderItem) {
            $items[] = [
                'order_item_id' => $orderItem->getId(),
                'qty' => 1,
                'price' => $orderItem->getPrice(),
                'row_total' => $orderItem->getPrice(),
                'entity_id' => null,
                'baseCost' => null,
                'basePrice' => null,
            ];
        }

        return [
            'order_id' => $order->getId(),
            'subtotal' => $items[0]['price'],
            'grand_total' => $items[0]['price'],
            'base_grand_total' => $items[0]['price'],
            'base_shipping_amount' => $order->getBaseShippingAmount(),
            'shipping_address_id' => $order->getShippigAddressId(),
            'billing_address_id' => $order->getBillingAddressId(),
            'invoiceId' => $order->getInvoiceCollection()->getFirstItem()->getId(),
            'adjustment' => null,
            'adjustment_negative' => null,
            'adjustment_positive' => null,
            'base_adjustment' => null,
            'base_adjustment_negative' => null,
            'base_adjustment_positive' => null,
            'base_currency_code' => 'USD',
            'base_discount_amount' => null,
            'base_discount_tax_compensation_amount' => null,
            'base_shipping_discount_tax_compensation_amnt' => null,
            'base_shipping_incl_tax' => null,
            'base_shipping_tax_amount' => null,
            'base_subtotal' => null,
            'base_subtotal_incl_tax' => null,
            'base_tax_amount' => null,
            'base_to_global_rate' => null,
            'base_to_order_rate' => null,
            'created_at' => null,
            'creditmemo_status' => null,
            'discount_amount' => null,
            'discount_description' => null,
            'email_sent' => null,
            'entity_id' => null,
            'global_currency_code' => 'USD',
            'discount_tax_compensation_amount' => null,
            'increment_id' => null,
            'invoice_id' => null,
            'order_currency_code' => 'USD',
            'shipping_amount' => null,
            'shipping_discount_tax_compensation_amount' => null,
            'shipping_incl_tax' => null,
            'shipping_tax_amount' => null,
            'state' => null,
            'store_currency_code' => 'USD',
            'store_id' => null,
            'store_to_base_rate' => null,
            'store_to_order_rate' => null,
            'subtotal_incl_tax' => null,
            'tax_amount' => null,
            'transaction_id' => null,
            'updated_at' => null,
            'items' => $items,
        ];
    }
}
